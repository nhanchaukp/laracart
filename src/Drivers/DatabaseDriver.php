<?php

namespace NhanChauKP\LaraCart\Drivers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class DatabaseDriver implements CartDriver
{
    protected ?Cart $cart;

    /**
     * {@inheritDoc}
     */
    public function getCart(): Cart
    {
        $user = Auth::user();
        if ($user) {
            $sessionId = Session::getId();
            $this->cart = Cart::where('session_id', $sessionId)->first();
            if ($this->cart) {
                $this->cart->user_id = $user->id;
                $this->cart->session_id = null;
                $this->cart->save();
            } else {
                $this->cart = Cart::firstOrCreate(['user_id' => $user->id]);
            }
        } else {
            $sessionId = Session::getId();
            $this->cart = Cart::firstOrCreate(['session_id' => $sessionId]);
        }

        return $this->cart->load('items');
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
        
        return $this->getCart();
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): Cart
    {
        $cart = $this->getCart();
        $cart->items()->delete();

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
