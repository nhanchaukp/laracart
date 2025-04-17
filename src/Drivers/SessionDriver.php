<?php

namespace NhanChauKP\LaraCart\Drivers;

use Illuminate\Support\Facades\Session;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class SessionDriver implements CartDriver
{
    protected $sessionKey;

    public function __construct()
    {
        $this->sessionKey = config('laracart.session_key', 'laracart');
    }

    /**
     * {@inheritDoc}
     */
    public function getCart(): Cart
    {
        $data = Session::get($this->sessionKey, []);
        $cart = new Cart($data);
        $cart->items = collect($data['items'] ?? [])->map(function ($item) {
            return new CartItem($item);
        });

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function storeCart(Cart $cart): Cart
    {
        $data = $cart->toArray();
        $data['items'] = $cart->items->map->toArray()->all();
        Session::put($this->sessionKey, $data);

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
        $cart = $this->getCart();
        $cart->items = collect();

        return $this->storeCart($cart);
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
