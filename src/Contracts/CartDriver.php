<?php

namespace NhanChauKP\LaraCart\Contracts;

use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

interface CartDriver
{
    /**
     * Get the current cart.
     */
    public function getCart(): Cart;

    /**
     * Store the cart.
     */
    public function storeCart(Cart $cart): Cart;

    /**
     * Add an item to the cart.
     *
     * @param  mixed  $itemable  The item to add to the cart.
     * @param  int  $quantity  The quantity of the item to add (default is 1).
     * @param  float  $price  The price of the item (default is -1, indicating the item's default price).
     * @param  array  $options  Additional options for the item.
     * @return Cart The updated cart instance.
     */
    public function addItem($itemable, int $quantity = 1, float $price = -1, array $options = []): Cart;

    /**
     * Retrieve a specific item from the cart.
     *
     * @param  mixed  $itemable  The item to search for in the cart.
     * @return CartItem|null The CartItem instance if found, or null if the item does not exist in the cart.
     */
    public function getItem($itemable): ?CartItem;

    /**
     * Remove an item from the cart.
     *
     * @param  mixed  $itemable  The item to remove from the cart.
     * @return Cart The updated cart instance.
     */
    public function removeItem($itemable): Cart;

    /**
     * Update item quantity in the cart.
     *
     * @param  mixed  $itemable  The item to update in the cart.
     * @param  int  $quantity  The new quantity for the item.
     * @return Cart The updated cart instance.
     */
    public function updateItemQuantity($itemable, int $quantity): Cart;

    /**
     * Increase item quantity in the cart.
     *
     * @param  mixed  $itemable  The item to increase the quantity of.
     * @param  int  $quantity  The quantity to increase (default is 1).
     * @return Cart The updated cart instance.
     */
    public function increaseQuantity($itemable, int $quantity = 1): Cart;

    /**
     * Decrease item quantity in the cart.
     *
     * @param  mixed  $itemable  The item to decrease the quantity of.
     * @param  int  $quantity  The quantity to decrease (default is 1).
     * @return Cart The updated cart instance.
     */
    public function decreaseQuantity($itemable, int $quantity = 1): Cart;

    /**
     * Empty the cart by removing all items.
     *
     * @return Cart The updated cart instance after being emptied.
     */
    public function clear(): Cart;

    /**
     * Get the total number of unique items in the cart.
     *
     * @return int The count of unique items in the cart.
     */
    public function count(): int;

    /**
     * Get the sum of all item quantities in the cart.
     *
     * @return int The total quantity of all items in the cart.
     */
    public function getTotalQuantity(): int;

    /**
     * Get the total price of all items in the cart, including any applied discounts.
     *
     * @return float The total price of the cart.
     */
    public function total(): float;

    /**
     * Apply a discount to the cart based on a percentage.
     *
     * @param  float  $percent  The discount percentage to apply (e.g., 10 for 10%).
     * @return Cart The updated cart instance with the discount applied.
     */
    public function setDiscount(float $percent): Cart;

    /**
     * Assign the current cart to a specific user, typically transferring a guest cart to a logged-in user.
     *
     * @param  int  $userId  The ID of the user to assign the cart to.
     * @return Cart The updated cart instance after assignment.
     */
    public function assignToUser(int $userId): Cart;

    /**
     * Retrieve all items currently in the cart.
     *
     * @return \Illuminate\Support\Collection|CartItem[] A collection of CartItem instances in the cart.
     */
    public function getItems(): \Illuminate\Support\Collection;

    /**
     * Check if the cart is empty.
     *
     * @return bool True if the cart is empty, false otherwise.
     */
    public function isEmpty(): bool;
}
