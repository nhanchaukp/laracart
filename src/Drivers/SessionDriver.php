<?php

namespace NhanChauKP\LaraCart\Drivers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class SessionDriver implements CartDriver
{
    protected $sessionKey;
    protected $guestSessionKey;

    public function __construct()
    {
        $this->sessionKey = config('laracart.session_key', 'laracart');
        $this->guestSessionKey = $this->sessionKey . '_guest';
    }

    /**
     * Get guest cart identifier (cookie-based)
     */
    protected function getGuestCartId(): string
    {
        $cookieName = 'guest_cart_session_id';
        $guestCartId = Cookie::get($cookieName);
        
        if (!$guestCartId) {
            $guestCartId = 'guest_session_' . Str::random(32);
            Cookie::queue($cookieName, $guestCartId, 60 * 24 * 30); // 30 days
        }
        
        return $guestCartId;
    }

    /**
     * Clear guest cart cookie after successful login
     */
    protected function clearGuestCartCookie(): void
    {
        Cookie::queue(Cookie::forget('guest_cart_session_id'));
    }

    /**
     * Merge guest cart items into user cart
     */
    protected function mergeGuestCartToUserCart(Cart $guestCart, Cart $userCart): Cart
    {
        foreach ($guestCart->items as $guestItem) {
            // Tìm item tương tự trong user cart
            $existingItem = $userCart->items->first(function ($item) use ($guestItem) {
                return $item->itemable_id == $guestItem->itemable_id 
                    && $item->itemable_type == $guestItem->itemable_type;
            });
            
            if ($existingItem) {
                // Nếu đã có item tương tự, cộng dồn quantity
                $existingItem->quantity += $guestItem->quantity;
            } else {
                // Nếu chưa có, thêm item mới vào user cart
                $userCart->items->push(new CartItem([
                    'itemable_id' => $guestItem->itemable_id,
                    'itemable_type' => $guestItem->itemable_type,
                    'quantity' => $guestItem->quantity,
                    'price' => $guestItem->price,
                    'options' => $guestItem->options,
                ]));
            }
        }
        
        // Merge discount nếu guest cart có discount cao hơn
        if (($guestCart->discount_percent ?? 0) > ($userCart->discount_percent ?? 0)) {
            $userCart->discount_percent = $guestCart->discount_percent;
        }
        
        return $userCart;
    }

    /**
     * {@inheritDoc}
     */
    public function getCart(): Cart
    {
        $user = auth()->user();
        
        if ($user) {
            $userCartData = Session::get($this->sessionKey, []);
            $userCart = new Cart($userCartData);
            $userCart->items = collect($userCartData['items'] ?? [])->map(function ($item) {
                return new CartItem($item);
            });
            
            $guestCartId = $this->getGuestCartId();
            $guestCartData = Session::get($this->guestSessionKey . '_' . $guestCartId, []);
            
            if (!empty($guestCartData['items'])) {
                $guestCart = new Cart($guestCartData);
                $guestCart->items = collect($guestCartData['items'])->map(function ($item) {
                    return new CartItem($item);
                });
                
                $userCart = $this->mergeGuestCartToUserCart($guestCart, $userCart);
                
                Session::forget($this->guestSessionKey . '_' . $guestCartId);
                $this->clearGuestCartCookie();
                
                $this->storeCart($userCart);
            }
            
            return $userCart;
        } else {
            $guestCartId = $this->getGuestCartId();
            $guestSessionKey = $this->guestSessionKey . '_' . $guestCartId;
            
            $data = Session::get($guestSessionKey, []);
            $cart = new Cart($data);
            $cart->items = collect($data['items'] ?? [])->map(function ($item) {
                return new CartItem($item);
            });

            return $cart;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function storeCart(Cart $cart): Cart
    {
        $data = $cart->toArray();
        $data['items'] = $cart->items->map->toArray()->all();
        
        $user = auth()->user();
        if ($user) {
            Session::put($this->sessionKey, $data);
        } else {
            $guestCartId = $this->getGuestCartId();
            $guestSessionKey = $this->guestSessionKey . '_' . $guestCartId;
            Session::put($guestSessionKey, $data);
        }

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function addItem($itemable, int $quantity = 1, float $price = -1, array $options = []): Cart
    {
        $cart = $this->getCart();
        $item = $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable);
        });
        if ($item) {
            $item->quantity += $quantity;
        } else {
            $cart->items->push(new CartItem([
                'itemable_id' => $itemable->id,
                'itemable_type' => get_class($itemable),
                'quantity' => $quantity,
                'price' => $itemable->getCartItemPrice(),
                'options' => $options,
            ]));
        }

        return $this->storeCart($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function getItem($itemable): ?CartItem
    {
        $cart = $this->getCart();

        return $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function removeItem($itemable): Cart
    {
        $cart = $this->getCart();
        $cart->items = $cart->items->reject(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable);
        })->values();

        return $this->storeCart($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity($itemable, int $quantity): Cart
    {
        $cart = $this->getCart();
        $cart->items->each(function ($i) use ($itemable, $quantity) {
            if ($i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable)) {
                $i->quantity = $quantity;
            }
        });

        return $this->storeCart($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function increaseQuantity($itemable, int $quantity = 1): Cart
    {
        return $this->addItem($itemable, $quantity);
    }

    /**
     * {@inheritDoc}
     */
    public function decreaseQuantity($itemable, int $quantity = 1): Cart
    {
        $cart = $this->getCart();
        $cart->items->each(function ($i) use ($itemable, $quantity) {
            if ($i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable)) {
                $i->quantity = max(1, $i->quantity - $quantity);
            }
        });

        return $this->storeCart($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): Cart
    {
        $user = auth()->user();
        if ($user) {
            Session::forget($this->sessionKey);
        } else {
            $guestCartId = $this->getGuestCartId();
            $guestSessionKey = $this->guestSessionKey . '_' . $guestCartId;
            Session::forget($guestSessionKey);
        }

        $cart = new Cart();
        $cart->items = collect();
        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return $this->getCart()->items->count();
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalQuantity(): int
    {
        return $this->getCart()->items->sum('quantity');
    }

    /**
     * {@inheritDoc}
     */
    public function total(): float
    {
        $cart = $this->getCart();
        $subtotal = $cart->items->sum(function ($item) {
            return $item->price * $item->quantity;
        });
        $discount = $cart->discount_percent ?? 0;

        return $subtotal * (1 - $discount / 100);
    }

    /**
     * {@inheritDoc}
     */
    public function setDiscount(float $percent): Cart
    {
        $cart = $this->getCart();
        $cart->discount_percent = $percent;

        return $this->storeCart($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function assignToUser(int $userId): Cart
    {
        // For session driver, this is a no-op. The cart will be merged with the user's cart on login in the main LaraCart logic.
        return $this->getCart();
    }

    /**
     * {@inheritDoc}
     */
    public function getItems(): \Illuminate\Support\Collection
    {
        return $this->getCart()->items;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return $this->getItems()->isEmpty();
    }
}
