<?php

namespace NhanChauKP\LaraCart\Events;

use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

class CartItemRemoved
{
    public function __construct(
        public Cart $cart,
        public CartItem $cartItem
    ) {}
}