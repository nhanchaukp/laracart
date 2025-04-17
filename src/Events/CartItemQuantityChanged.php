<?php

namespace NhanChauKP\LaraCart\Events;

use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class CartItemQuantityChanged
{
    public function __construct(
        public Cart $cart,
        public CartItem $cartItem,
        public int $oldQuantity,
        public int $newQuantity
    ) {}
}