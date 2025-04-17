<?php

namespace NhanChauKP\LaraCart\Contracts;

interface CartItemPrice
{
    /**
     * Calculate and return the price of the product.
     */
    public function getCartItemPrice(): float;
}
