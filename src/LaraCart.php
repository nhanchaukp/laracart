<?php

namespace NhanChauKP\LaraCart;

use Illuminate\Support\Facades\App;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

/**
 * LaraCart is a cart management service that allows for flexible driver-based operations.
 */
class LaraCart
{
    /**
     * @var CartDriver The driver instance used for cart operations.
     */
    protected $driver;

    /**
     * Constructor to initialize the cart driver.
     */
    public function __construct()
    {
        $this->driver = App::make(CartDriver::class);
    }

    /**
     * Switch the cart driver on the fly.
     *
     * @param  string  $driver  The name of the driver to switch to.
     * @return $this
     */
    public function driver($driver)
    {
        $this->driver = App::makeWith(CartDriver::class, ['driver' => $driver]);

        return $this;
    }

    /**
     * Retrieve the current cart.
     *
     * @return Cart The cart instance.
     */
    public function getCart(): Cart
    {
        return $this->driver->getCart();
    }

    /**
     * Store the cart.
     *
     * @param  mixed  $cart  The cart instance to store.
     */
    public function storeCart(mixed $cart): Cart
    {
        return $this->driver->storeCart($cart);
    }

    /**
     * Add an item to the cart.
     *
     * @param  mixed  $itemable  The item to add (must implement Cartable).
     * @param  int  $quantity  The quantity of the item (default: 1).
     * @param  float  $price  The price of the item (default: -1).
     * @param  array  $options  Additional options for the item.
     */
    public function addItem(mixed $itemable, int $quantity = 1, float $price = -1, array $options = []): Cart
    {
        return $this->driver->addItem($itemable, $quantity, $price, $options);
    }

    /**
     * Retrieve a specific item from the cart.
     *
     * @param  mixed  $itemable  The item to retrieve.
     */
    public function getItem(mixed $itemable): ?CartItem
    {
        return $this->driver->getItem($itemable);
    }

    /**
     * Remove an item from the cart.
     *
     * @param  mixed  $itemable  The item to remove.
     */
    public function removeItem(mixed $itemable): Cart
    {
        return $this->driver->removeItem($itemable);
    }

    /**
     * Update the quantity of an item in the cart.
     *
     * @param  mixed  $itemable  The item to update.
     * @param  int  $quantity  The new quantity.
     * @return mixed
     */
    public function updateItemQuantity($itemable, $quantity)
    {
        return $this->driver->updateItemQuantity($itemable, $quantity);
    }

    /**
     * Increase the quantity of an item in the cart.
     *
     * @param  mixed  $itemable  The item to increase.
     * @param  int  $quantity  The amount to increase (default: 1).
     * @return mixed
     */
    public function increaseQuantity($itemable, $quantity = 1)
    {
        return $this->driver->increaseQuantity($itemable, $quantity);
    }

    /**
     * Decrease the quantity of an item in the cart.
     *
     * @param  mixed  $itemable  The item to decrease.
     * @param  int  $quantity  The amount to decrease (default: 1).
     * @return mixed
     */
    public function decreaseQuantity($itemable, $quantity = 1)
    {
        return $this->driver->decreaseQuantity($itemable, $quantity);
    }

    /**
     * Clear the cart.
     *
     * @return mixed
     */
    public function clear()
    {
        return $this->driver->clear();
    }

    /**
     * Get the count of items in the cart.
     *
     * @return int The number of items in the cart.
     */
    public function count()
    {
        return $this->driver->count();
    }

    /**
     * Get the total quantity of items in the cart.
     *
     * @return int The sum of quantities of all items.
     */
    public function quantitySum()
    {
        return $this->driver->quantitySum();
    }

    /**
     * Get the total price of the cart.
     *
     * @return float The total price.
     */
    public function total()
    {
        return $this->driver->total();
    }

    /**
     * Set a discount percentage for the cart.
     *
     * @param  float  $percent  The discount percentage.
     * @return mixed
     */
    public function setDiscount($percent)
    {
        return $this->driver->setDiscount($percent);
    }

    /**
     * Assign the cart to a specific user.
     *
     * @param  int  $userId  The ID of the user.
     * @return mixed
     */
    public function assignToUser($userId)
    {
        return $this->driver->assignToUser($userId);
    }

    /**
     * Get all items in the cart.
     *
     * @return array The list of items in the cart.
     */
    public function getItems()
    {
        return $this->driver->getItems();
    }

    /**
     * Check if the cart is empty.
     *
     * @return bool True if the cart is empty, false otherwise.
     */
    public function isEmpty()
    {
        return $this->driver->isEmpty();
    }
}
