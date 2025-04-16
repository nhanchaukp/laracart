<?php 
namespace NhanChauKP\LaraCart\Contracts;

interface CartItemPrice
{
    /**
     * Calculate and return the price of the product.
     *
     * @return float
     */
    public function getCartItemPrice(): float;
}