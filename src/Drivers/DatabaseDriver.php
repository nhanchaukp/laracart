<?php

namespace NhanChauKP\LaraCart\Drivers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class DatabaseDriver implements CartDriver
{
    protected ?Cart $cart = null;

    /**
     * {@inheritDoc}
     */
    public function getCart(): Cart
    {
        if ($this->cart) {
            return $this->cart;
        }

        $user = auth()->user();
        $query = Cart::query()->with('items');

        if ($user) {
            $guestCartId = $this->getGuestCartId();
            $carts = Cart::with('items')
                ->whereIn('session_id', [$guestCartId])
                ->orWhere('user_id', $user->id)
                ->get()
                ->keyBy(fn($cart) => $cart->user_id ? 'user' : 'guest');

            $guestCart = $carts->get('guest');
            $userCart  = $carts->get('user');

            if ($guestCart && $userCart) {
                $this->mergeSessionCartToUserCart($guestCart, $userCart);
                $guestCart->delete();
                $this->clearGuestCartCookie();
                $this->cart = $userCart->load('items');
            } elseif ($guestCart) {
                $guestCart->update([
                    'user_id' => $user->id,
                    'session_id' => null,
                ]);
                $this->clearGuestCartCookie();
                $this->cart = $guestCart->load('items');
            } else {
                $this->cart = Cart::firstOrCreate(['user_id' => $user->id]);
            }
        } else {
            $guestCartId = $this->getGuestCartId();
            $this->cart = Cart::firstOrCreate(['session_id' => $guestCartId]);
            $this->cart->loadMissing('items');
        }

        return $this->cart;
    }

    /**
     * Merge guest cart items into user cart
     */
    protected function mergeSessionCartToUserCart(Cart $guestCart, Cart $userCart): void
    {
        $guestItems = $guestCart->items;
        $userItems  = $userCart->items->keyBy(fn($i) => $i->itemable_type . '#' . $i->itemable_id);

        foreach ($guestItems as $guestItem) {
            $key = $guestItem->itemable_type . '#' . $guestItem->itemable_id;
            if (isset($userItems[$key])) {
                $userItems[$key]->increment('quantity', $guestItem->quantity);
            } else {
                $userCart->items()->create($guestItem->only(['itemable_id', 'itemable_type', 'quantity', 'price', 'options']));
            }
        }

        if ($guestCart->discount_percent > $userCart->discount_percent) {
            $userCart->update(['discount_percent' => $guestCart->discount_percent]);
        }
    }


    /**
     * Reset cart cache
     */
    protected function resetCartCache(): void
    {
        $this->cart = null;
    }

    /**
     * Get guest cart identifier
     */
    protected function getGuestCartId(): string
    {
        $cookieName = 'guest_cart_id';
        $guestCartId = Cookie::get($cookieName);

        if (!$guestCartId) {
            $guestCartId = 'guest_' . Str::random(32);
            Cookie::queue($cookieName, $guestCartId, 60 * 24 * 30); // 30 days
        }

        return $guestCartId;
    }

    /**
     * Clear guest cart cookie
     */
    protected function clearGuestCartCookie(): void
    {
        Cookie::queue(Cookie::forget('guest_cart_id'));
    }

    /**
     * {@inheritDoc}
     */
    public function storeCart(Cart $cart): Cart
    {
        $cart->save();

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function addItem($itemable, int $quantity = 1, float $price = -1, array $options = []): Cart
    {
        $cart = $this->getCart();
        $item = $cart->items()->where('itemable_id', $itemable->id)->where('itemable_type', get_class($itemable))->first();
        if ($item) {
            $item->quantity += $quantity;
            $item->save();
        } else {
            $cart->items()->create([
                'itemable_id' => $itemable->id,
                'itemable_type' => get_class($itemable),
                'quantity' => $quantity,
                'price' => $price < 0 ? $itemable->getCartItemPrice() : $price,
                'options' => $options,
            ]);
        }

        $this->resetCartCache();
        return $this->getCart();
    }

    /**
     * {@inheritDoc}
     */
    public function getItem($itemable): ?CartItem
    {
        $cart = $this->getCart();

        return $cart->items()->where('itemable_id', $itemable->id)->where('itemable_type', get_class($itemable))->first();
    }

    /**
     * {@inheritDoc}
     */
    public function removeItem($itemable): Cart
    {
        $cart = $this->getCart();
        $cart->items()->where('itemable_id', $itemable->id)->where('itemable_type', get_class($itemable))->delete();

        $this->resetCartCache();
        return $this->getCart();
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity($itemable, int $quantity): Cart
    {
        $cart = $this->getCart();
        $item = $cart->items()->where('itemable_id', $itemable->id)->where('itemable_type', get_class($itemable))->first();

        if (! $item) {
            throw new \RuntimeException('The item not found');
        }
        if ($quantity < 1) {
            throw new \RuntimeException('The quantity must be greater than 0');
        }
        $item->quantity = $quantity;
        $item->save();

        $this->resetCartCache();
        return $this->getCart();
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
        $item = $cart->items()->where('itemable_id', $itemable->id)->where('itemable_type', get_class($itemable))->first();
        if (! $item) {
            throw new \RuntimeException('The item not found');
        }
        if ($quantity < 1) {
            throw new \RuntimeException('The quantity must be greater than 0');
        }
        $item->quantity = max(1, $item->quantity - $quantity);
        $item->save();

        $this->resetCartCache();
        return $this->getCart();
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): Cart
    {
        $cart = $this->getCart();
        $cart->items()->delete();

        $this->resetCartCache();
        return $this->getCart();
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
        $discount = $cart->discount_percent;

        return $subtotal * (1 - $discount / 100);
    }

    /**
     * {@inheritDoc}
     */
    public function setDiscount(float $percent): Cart
    {
        $cart = $this->getCart();
        $cart->discount_percent = $percent;
        $cart->save();

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function assignToUser(int $userId): Cart
    {
        $cart = $this->getCart();
        $cart->user_id = $userId;
        $cart->session_id = null;
        $cart->save();

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function getItems(): \Illuminate\Support\Collection
    {
        $cart = $this->getCart();

        return $cart->items;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return $this->getItems()->isEmpty();
    }
}
