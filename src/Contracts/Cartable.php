<?php

namespace NhanChauKP\LaraCart\Contracts;

/**
 * Interface for defining a cartable item.
 *
 * A cartable item is an entity that can be added to a shopping cart.
 */
interface Cartable
{
    /**
     * Get the price of the cartable item.
     */
    public function getCartItemPrice(): float;

    /**
     * Get the name of the cartable item.
     */
    public function getCartItemName(): string;

    /**
     * Get any additional data for the cartable item.
     */
    public function getCartItemOptions(): array;
}
