<?php

namespace NhanChauKP\LaraCart\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Events\CartItemAdded;
use NhanChauKP\LaraCart\Events\CartItemQuantityChanged;
use NhanChauKP\LaraCart\Events\CartItemRemoved;
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class DatabaseDriver implements CartDriver
{
    protected ?Cart $cart = null;

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
     * Get the guest cookie name.
     */
    protected function getCookieName(): string
    {
        return config('laracart.cookie.name', 'guest_cart_id');
    }

    /**
     * Get the guest cookie expiration in days.
     */
    protected function getCookieExpiresAfter(): int
    {
        return (int) config('laracart.cookie.expires_after', 30);
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

        if ($user) {
            $guestCartId = $this->getGuestCartId(createIfMissing: false);
            $userCart = $cartModel::query()
                ->with('items.itemable')
                ->where('user_id', $user->id)
                ->first();

            if ($guestCartId) {
                $guestCart = $cartModel::query()
                    ->with('items.itemable')
                    ->where('session_id', $guestCartId)
                    ->first();

                if ($guestCart && $userCart) {
                    $this->mergeSessionCartToUserCart($guestCart, $userCart);
                    $guestCart->delete();
                    $this->clearGuestCartCookie();
                    $this->cart = $userCart->load('items.itemable');
                } elseif ($guestCart) {
                    $guestCart->update([
                        'user_id' => $user->id,
                        'session_id' => null,
                    ]);
                    $this->clearGuestCartCookie();
                    $this->cart = $guestCart->load('items.itemable');
                } else {
                    $this->cart = $userCart ?? $cartModel::create(['user_id' => $user->id]);
                    $this->cart->loadMissing('items.itemable');
                }
            } else {
                $this->cart = $userCart ?? $cartModel::create(['user_id' => $user->id]);
                $this->cart->loadMissing('items.itemable');
            }
        } else {
            $guestCartId = $this->getGuestCartId(createIfMissing: false);

            if ($guestCartId) {
                $this->cart = $cartModel::firstOrCreate(['session_id' => $guestCartId]);
                $this->cart->loadMissing('items.itemable');
            } else {
                $cart = new $cartModel;
                $cart->setRelation('items', collect());
                $this->cart = $cart;
            }
        }

        return $this->cart;
    }

    /**
     * Merge guest cart items into user cart.
     */
    protected function mergeSessionCartToUserCart(Cart $guestCart, Cart $userCart): void
    {
        $guestItems = $guestCart->items;
        $userItems = $userCart->items->keyBy(fn ($i) => $i->itemable_type.'#'.$i->itemable_id);

        foreach ($guestItems as $guestItem) {
            $key = $guestItem->itemable_type.'#'.$guestItem->itemable_id;
            if (isset($userItems[$key])) {
                $oldQuantity = $userItems[$key]->quantity;
                $userItems[$key]->increment('quantity', $guestItem->quantity);
                event(new CartItemQuantityChanged($userCart, $userItems[$key], $oldQuantity, $userItems[$key]->quantity));
            } else {
                $newItem = $userCart->items()->create(
                    $guestItem->only(['itemable_id', 'itemable_type', 'quantity', 'price', 'options'])
                );
                $newItem->setRelation('itemable', $guestItem->itemable);
                $userCart->items->push($newItem);
                event(new CartItemAdded($userCart, $newItem));
            }
        }

        if (($guestCart->discount_percent ?? 0) > ($userCart->discount_percent ?? 0)) {
            $userCart->update(['discount_percent' => $guestCart->discount_percent]);
        }
    }

    /**
     * Reset cart cache.
     */
    public function resetCartCache(): void
    {
        $this->cart = null;
    }

    /**
     * Get guest cart identifier.
     */
    protected function getGuestCartId(bool $createIfMissing = true): ?string
    {
        $cookieName = $this->getCookieName();
        $guestCartId = Cookie::get($cookieName);

        if (! $guestCartId && Cookie::hasQueued($cookieName)) {
            $guestCartId = Cookie::queued($cookieName)?->getValue();
        }

        if (! $guestCartId && $createIfMissing) {
            $guestCartId = 'guest_'.Str::random(32);
            Cookie::queue($cookieName, $guestCartId, 60 * 24 * $this->getCookieExpiresAfter());
        }

        return $guestCartId;
    }

    /**
     * Clear guest cart cookie.
     */
    protected function clearGuestCartCookie(): void
    {
        Cookie::queue(Cookie::forget($this->getCookieName()));
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

        if (! $cart->exists) {
            $user = auth()->user();
            if ($user) {
                $cart->user_id = $user->id;
            } else {
                $cart->session_id = $this->getGuestCartId(createIfMissing: true);
            }
            $cart->save();
        }

        $item = $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type === get_class($itemable);
        });

        if ($item) {
            $oldQuantity = $item->quantity;
            $item->quantity += $quantity;
            $item->save();

            event(new CartItemQuantityChanged($cart, $item, $oldQuantity, $item->quantity));
        } else {
            $item = $cart->items()->create([
                'itemable_id' => $itemable->id,
                'itemable_type' => get_class($itemable),
                'quantity' => $quantity,
                'price' => $price < 0 ? $itemable->getCartItemPrice() : $price,
                'options' => $options,
            ]);

            $item->setRelation('itemable', $itemable);
            $cart->items->push($item);

            event(new CartItemAdded($cart, $item));
        }

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function getItem($itemable): ?CartItem
    {
        $cart = $this->getCart();

        return $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type === get_class($itemable);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function removeItem($itemable): Cart
    {
        $cart = $this->getCart();

        $item = $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type === get_class($itemable);
        });

        if ($item) {
            $item->delete();
            $cart->setRelation('items', $cart->items->reject(fn ($i) => $i->id === $item->id)->values());

            event(new CartItemRemoved($cart, $item));
        }

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function updateItemQuantity($itemable, int $quantity): Cart
    {
        $cart = $this->getCart();

        $item = $cart->items->first(function ($i) use ($itemable) {
            return $i->itemable_id == $itemable->id && $i->itemable_type === get_class($itemable);
        });

        if (! $item) {
            throw new \RuntimeException('The item not found');
        }

        if ($quantity < 1) {
            throw new \RuntimeException('The quantity must be greater than 0');
        }

        $oldQuantity = $item->quantity;
        $item->quantity = $quantity;
        $item->save();

        event(new CartItemQuantityChanged($cart, $item, $oldQuantity, $quantity));

        return $cart;
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
            return $i->itemable_id == $itemable->id && $i->itemable_type === get_class($itemable);
        });

        if (! $item) {
            throw new \RuntimeException('The item not found');
        }

        if ($quantity < 1) {
            throw new \RuntimeException('The quantity must be greater than 0');
        }

        $oldQuantity = $item->quantity;
        $item->quantity = max(1, $item->quantity - $quantity);
        $item->save();

        event(new CartItemQuantityChanged($cart, $item, $oldQuantity, $item->quantity));

        return $cart;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): Cart
    {
        $cart = $this->getCart();

        if ($cart->exists) {
            foreach ($cart->items as $item) {
                event(new CartItemRemoved($cart, $item));
            }

            $cart->items()->delete();
        }

        $cart->setRelation('items', collect());

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

        if (! $cart->exists) {
            $user = auth()->user();
            if ($user) {
                $cart->user_id = $user->id;
            } else {
                $cart->session_id = $this->getGuestCartId(createIfMissing: true);
            }
        }

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

        if ($cart->exists) {
            $cart->save();
        }

        return $cart;
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
