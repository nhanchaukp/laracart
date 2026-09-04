<?php

namespace NhanChauKP\LaraCart\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Events\CartItemAdded;
use NhanChauKP\LaraCart\Events\CartItemQuantityChanged;
use NhanChauKP\LaraCart\Events\CartItemRemoved;
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class SessionDriver implements CartDriver
{
    protected ?Cart $cart = null;

    protected string $sessionKey;

    protected string $guestSessionKey;

    public function __construct()
    {
        $this->sessionKey = config('laracart.session_key', 'laracart');
        $this->guestSessionKey = $this->sessionKey.'_guest';
    }

    /**
     * Get the cart model class name.
     *
     * @return class-string<Cart>
     */
    protected function getCartModelClass(): string
    {
        return config('laracart.models.cart', Cart::class);
    }

    /**
     * Get the cart item model class name.
     *
     * @return class-string<CartItem>
     */
    protected function getCartItemModelClass(): string
    {
        return config('laracart.models.cart_item', CartItem::class);
    }

    /**
     * Get guest cookie name.
     */
    protected function getCookieName(): string
    {
        return config('laracart.cookie.name', 'guest_cart_session_id');
    }

    /**
     * Get guest cookie expiration in days.
     */
    protected function getCookieExpiresAfter(): int
    {
        return (int) config('laracart.cookie.expires_after', 30);
    }

    /**
     * Get guest cart identifier (cookie-based).
     */
    protected function getGuestCartId(bool $createIfMissing = true): ?string
    {
        $cookieName = $this->getCookieName();
        $guestCartId = Cookie::get($cookieName);

        if (! $guestCartId && Cookie::hasQueued($cookieName)) {
            $guestCartId = Cookie::queued($cookieName)?->getValue();
        }

        if (! $guestCartId && $createIfMissing) {
            $guestCartId = 'guest_session_'.Str::random(32);
            Cookie::queue($cookieName, $guestCartId, 60 * 24 * $this->getCookieExpiresAfter());
        }

        return $guestCartId;
    }

    /**
     * Clear guest cart cookie after successful login.
     */
    protected function clearGuestCartCookie(): void
    {
        Cookie::queue(Cookie::forget($this->getCookieName()));
    }

    /**
     * Merge guest cart items into user cart.
     */
    protected function mergeGuestCartToUserCart(Cart $guestCart, Cart $userCart): Cart
    {
        $cartItemModel = $this->getCartItemModelClass();

        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items->first(function ($item) use ($guestItem) {
                return $item->itemable_id == $guestItem->itemable_id
                    && $item->itemable_type == $guestItem->itemable_type;
            });

            if ($existingItem) {
                $oldQuantity = $existingItem->quantity;
                $existingItem->quantity += $guestItem->quantity;
                event(new CartItemQuantityChanged($userCart, $existingItem, $oldQuantity, $existingItem->quantity));
            } else {
                $newItem = new $cartItemModel([
                    'itemable_id' => $guestItem->itemable_id,
                    'itemable_type' => $guestItem->itemable_type,
                    'quantity' => $guestItem->quantity,
                    'price' => $guestItem->price,
                    'options' => $guestItem->options,
                ]);
                $newItem->setRelation('itemable', $guestItem->itemable);
                $userCart->items->push($newItem);
                event(new CartItemAdded($userCart, $newItem));
            }
        }

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
        if ($this->cart) {
            return $this->cart;
        }

        $user = auth()->user();
        $cartModel = $this->getCartModelClass();
        $cartItemModel = $this->getCartItemModelClass();

        if ($user) {
            $userCartData = Session::get($this->sessionKey, []);
            $userCart = new $cartModel($userCartData);
            $userCart->items = collect($userCartData['items'] ?? [])->map(function ($item) use ($cartItemModel) {
                return new $cartItemModel($item);
            });

            $guestCartId = $this->getGuestCartId(createIfMissing: false);

            if ($guestCartId) {
                $guestCartKey = $this->guestSessionKey.'_'.$guestCartId;
                $guestCartData = Session::get($guestCartKey, []);

                if (! empty($guestCartData['items'])) {
                    $guestCart = new $cartModel($guestCartData);
                    $guestCart->items = collect($guestCartData['items'])->map(function ($item) use ($cartItemModel) {
                        return new $cartItemModel($item);
                    });

                    $userCart = $this->mergeGuestCartToUserCart($guestCart, $userCart);

                    Session::forget($guestCartKey);
                    $this->clearGuestCartCookie();

                    $this->storeCart($userCart);
                }
            }

            $this->cart = $userCart;
        } else {
            $guestCartId = $this->getGuestCartId(createIfMissing: false);

            if ($guestCartId) {
                $guestSessionKey = $this->guestSessionKey.'_'.$guestCartId;
                $data = Session::get($guestSessionKey, []);
                $cart = new $cartModel($data);
                $cart->items = collect($data['items'] ?? [])->map(function ($item) use ($cartItemModel) {
                    return new $cartItemModel($item);
                });
            } else {
                $cart = new $cartModel;
                $cart->items = collect();
            }

            $this->cart = $cart;
        }

        return $this->cart;
    }

    /**
     * {@inheritDoc}
     */
    public function storeCart(Cart $cart): Cart
    {
        $this->cart = $cart;

        $data = $cart->toArray();
        $data['items'] = $cart->items->map->toArray()->all();

        $user = auth()->user();
        if ($user) {
            Session::put($this->sessionKey, $data);
        } else {
            $guestCartId = $this->getGuestCartId(createIfMissing: true);
            $guestSessionKey = $this->guestSessionKey.'_'.$guestCartId;
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
            $oldQuantity = $item->quantity;
            $item->quantity += $quantity;

            event(new CartItemQuantityChanged($cart, $item, $oldQuantity, $item->quantity));
        } else {
            $cartItemModel = $this->getCartItemModelClass();
            $newItem = new $cartItemModel([
                'itemable_id' => $itemable->id,
                'itemable_type' => get_class($itemable),
                'quantity' => $quantity,
                'price' => $price < 0 ? $itemable->getCartItemPrice() : $price,
                'options' => $options,
            ]);

            $newItem->setRelation('itemable', $itemable);
            $cart->items->push($newItem);

            event(new CartItemAdded($cart, $newItem));
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
        $item = $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable);
        });

        if ($item) {
            $cart->items = $cart->items->reject(function ($i) use ($itemable) {
                return $i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable);
            })->values();

            event(new CartItemRemoved($cart, $item));
        }

        return $this->storeCart($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity($itemable, int $quantity): Cart
    {
        $cart = $this->getCart();
        $item = $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable);
        });

        if (! $item) {
            throw new \RuntimeException('The item not found');
        }

        if ($quantity < 1) {
            throw new \RuntimeException('The quantity must be greater than 0');
        }

        $oldQuantity = $item->quantity;
        $item->quantity = $quantity;

        event(new CartItemQuantityChanged($cart, $item, $oldQuantity, $quantity));

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
        $item = $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type == get_class($itemable);
        });

        if (! $item) {
            throw new \RuntimeException('The item not found');
        }

        if ($quantity < 1) {
            throw new \RuntimeException('The quantity must be greater than 0');
        }

        $oldQuantity = $item->quantity;
        $item->quantity = max(1, $item->quantity - $quantity);

        event(new CartItemQuantityChanged($cart, $item, $oldQuantity, $item->quantity));

        return $this->storeCart($cart);
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): Cart
    {
        $currentCart = $this->getCart();
        foreach ($currentCart->items as $item) {
            event(new CartItemRemoved($currentCart, $item));
        }

        $user = auth()->user();
        if ($user) {
            Session::forget($this->sessionKey);
        } else {
            $guestCartId = $this->getGuestCartId(createIfMissing: false);
            if ($guestCartId) {
                Session::forget($this->guestSessionKey.'_'.$guestCartId);
            }
        }

        $cartModel = $this->getCartModelClass();
        $cart = new $cartModel;
        $cart->items = collect();

        $this->cart = $cart;

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
        return (int) $this->getCart()->items->sum('quantity');
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
        $discount = (float) ($cart->discount_percent ?? 0);

        return (float) ($subtotal * (1 - $discount / 100));
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
        return $this->getCart();
    }

    /**
     * {@inheritDoc}
     */
    public function getItems(): Collection
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
