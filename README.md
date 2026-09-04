![LaraCart](https://socialify.git.ci/nhanchaukp/laracart/image?custom_description=Flexible%2C+high-performance+shopping+cart+package+for+Laravel+with+multi-driver+support.&description=1&logo=https%3A%2F%2Fupload.wikimedia.org%2Fwikipedia%2Fcommons%2Fthumb%2F9%2F9a%2FLaravel.svg%2F1200px-Laravel.svg.png&name=1&owner=1&pattern=Signal&theme=Light)

# LaraCart

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nhanchaukp/laracart.svg?style=flat-square)](https://packagist.org/packages/nhanchaukp/laracart)
[![Total Downloads](https://img.shields.io/packagist/dt/nhanchaukp/laracart.svg?style=flat-square)](https://packagist.org/packages/nhanchaukp/laracart)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/nhanchaukp/laracart.svg?style=flat-square)](https://packagist.org/packages/nhanchaukp/laracart)
[![Laravel Compatibility](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x%20%7C%2013.x-red.svg?style=flat-square)](https://laravel.com)

**LaraCart** is a modern, flexible, and high-performance shopping cart management package for **Laravel 10, 11, 12, and 13**. Built with polymorphic relationships, multi-driver storage (Database, Session), lazy guest initialization, automatic cart merging upon login, zero N+1 queries, and event-driven architecture.

---

## Key Features

- 🚀 **Multiple Storage Drivers**: Seamlessly switch between `database` and `session` storage drivers, or switch on-the-fly per request.
- ⚡ **Lazy Guest Cart**: Zero database writes or cookie spam. Reading cart count (`LaraCart::count()`) for visitors who haven't added items executes with 0 database queries.
- 🔗 **Automatic Cart Merging**: Guest carts are automatically merged into the user's account cart upon authentication.
- 🪶 **Zero N+1 Queries**: Automatically eager-loads polymorphic relations (`items.itemable`), ensuring fast iteration over cart items.
- 🧩 **Polymorphic Itemables**: Add any Eloquent model (`Product`, `Course`, `SubscriptionPlan`, etc.) to the cart by implementing `CartItemPrice` or `Cartable`.
- 🏷️ **Discounts & Custom Pricing**: Apply percentage-based discounts or override product prices per item.
- 🔔 **Event-Driven**: Dispatches Laravel events (`CartItemAdded`, `CartItemQuantityChanged`, `CartItemRemoved`) for analytics, inventory checks, or UI updates.
- ⚙️ **Configurable Models**: Extend or swap default `Cart` and `CartItem` Eloquent models through configuration.

---

## Requirements

- **PHP**: `^8.1 | ^8.2 | ^8.3 | ^8.4`
- **Laravel**: `^10.0 | ^11.0 | ^12.0 | ^13.0`

---

## Installation

### 1. Require via Composer

```bash
composer require nhanchaukp/laracart
```

### 2. Publish Configuration & Migrations

```bash
# Publish configuration
php artisan vendor:publish --tag=laracart-config

# Publish database migrations (for database driver)
php artisan vendor:publish --tag=laracart-migrations
```

### 3. Run Migrations

```bash
php artisan migrate
```

---

## Preparing Your Models

Any Eloquent model you want to add to the cart must implement the `CartItemPrice` or `Cartable` contract.

### Option A: Implementing `CartItemPrice` (Simplest)

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use NhanChauKP\LaraCart\Contracts\CartItemPrice;

class Product extends Model implements CartItemPrice
{
    /**
     * Return the base price of the item.
     */
    public function getCartItemPrice(): float
    {
        return (float) ($this->sale_price > 0 ? $this->sale_price : $this->price);
    }
}
```

### Option B: Implementing `Cartable` (Full Support)

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use NhanChauKP\LaraCart\Contracts\Cartable;

class Product extends Model implements Cartable
{
    public function getCartItemPrice(): float
    {
        return (float) $this->price;
    }

    public function getCartItemName(): string
    {
        return $this->name;
    }

    public function getCartItemOptions(): array
    {
        return [
            'sku' => $this->sku,
            'thumbnail' => $this->thumbnail_url,
        ];
    }
}
```

---

## Configuration

The published configuration file is located at `config/laracart.php`:

```php
use NhanChauKP\LaraCart\Models\Cart;
use NhanChauKP\LaraCart\Models\CartItem;

return [
    /*
    |--------------------------------------------------------------------------
    | LaraCart Storage Driver
    |--------------------------------------------------------------------------
    | Available options: 'database', 'session'
    */
    'driver' => env('LARACART_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Storage Key
    |--------------------------------------------------------------------------
    | Used when driver is set to 'session'.
    */
    'session_key' => 'laracart',

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('LARACART_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Customizable Models
    |--------------------------------------------------------------------------
    | You can extend and specify your custom Cart or CartItem models here.
    */
    'models' => [
        'cart' => Cart::class,
        'cart_item' => CartItem::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest Cookie Configuration
    |--------------------------------------------------------------------------
    */
    'cookie' => [
        'name' => 'laracart',
        'expires_after' => 30, // Expiration time in days
    ],
];
```

---

## Basic Usage

Import the `LaraCart` facade in your controllers, services, or Livewire components:

```php
use NhanChauKP\LaraCart\Facades\LaraCart;
```

### Adding Items to Cart

```php
$product = Product::find(1);

// Add item with default price from model (quantity defaults to 1)
LaraCart::addItem($product);

// Add item with specific quantity
LaraCart::addItem($product, quantity: 2);

// Add item with custom price override (e.g. promotional price, tier discount)
LaraCart::addItem($product, quantity: 1, price: 89.99);

// Add item with custom options (e.g. variant, color, size, notes)
LaraCart::addItem($product, quantity: 1, options: [
    'size' => 'XL',
    'color' => 'Navy Blue',
    'gift_wrapped' => true,
]);
```

> **Note**: If the item already exists in the cart, `addItem()` will increment its quantity and dispatch `CartItemQuantityChanged`.

---

### Retrieving Cart Information

```php
// Get the Cart model instance (eager-loads items.itemable)
$cart = LaraCart::getCart();

// Get the collection of CartItem models
$items = LaraCart::getItems();

foreach ($items as $item) {
    echo $item->id;
    echo $item->quantity;
    echo $item->price;
    echo $item->options['size'] ?? null;

    // Polymorphic relation is eager loaded (no N+1 queries):
    echo $item->itemable->name;
    echo $item->itemable->sku;
}

// Find a specific item by its product model
$cartItem = LaraCart::getItem($product);

// Count of unique products in cart
$uniqueCount = LaraCart::count(); // e.g. 3 products

// Total quantity of all items
$totalQuantity = LaraCart::totalQuantity(); // or LaraCart::getTotalQuantity()

// Total price calculation (takes discount into account)
$totalPrice = LaraCart::total();

// Check if cart is empty
if (LaraCart::isEmpty()) {
    // Cart has no items
}
```

---

### Updating & Modifying Quantities

```php
// Update directly to a specific quantity (must be >= 1)
LaraCart::updateItemQuantity($product, 5);

// Increase quantity by an increment (default: 1)
LaraCart::increaseQuantity($product);
LaraCart::increaseQuantity($product, 2);

// Decrease quantity (minimum boundary is 1)
LaraCart::decreaseQuantity($product);
LaraCart::decreaseQuantity($product, 2);
```

---

### Removing Items & Clearing Cart

```php
// Remove a single product from cart
LaraCart::removeItem($product);

// Remove all items from cart
LaraCart::clear();
```

---

### Managing Discounts

```php
// Apply a 15% discount across the cart
LaraCart::setDiscount(15);

// Cart total automatically applies discount:
// Total = Subtotal * (1 - discount / 100)
$discountedTotal = LaraCart::total();
```

---

### Guest Cart & Automatic User Merging

LaraCart seamlessly supports guest shoppers:

1. **Lazy Initialization**: Guests browsing your store do not receive unnecessary cookies or blank database rows when cart badges check `LaraCart::count()`.
2. **First Add**: When a guest adds their first item, a tracking cookie (`laracart`) is queued and their cart is persisted.
3. **Login Merge**: When the user logs in, LaraCart automatically detects the guest cart, merges all items into the authenticated user's cart (accumulating quantities for duplicates), transfers higher discounts, and clears the guest session cookie!
4. **Manual Assignment**: You can also manually reassign a cart to any user:

```php
LaraCart::assignToUser($user->id);
```

---

### Switching Storage Drivers On-the-Fly

You can switch between drivers dynamically:

```php
// Use database driver
$databaseCart = LaraCart::driver('database')->getCart();

// Use session driver
$sessionCart = LaraCart::driver('session')->getItems();
```

---

## Events

LaraCart dispatches standard Laravel events throughout the shopping lifecycle. You can listen to these events in your `EventServiceProvider` or listeners:

| Event | Dispatched When | Payload Properties |
| :--- | :--- | :--- |
| `NhanChauKP\LaraCart\Events\CartItemAdded` | A new item is added to the cart | `$event->cart`, `$event->cartItem` |
| `NhanChauKP\LaraCart\Events\CartItemQuantityChanged` | An item's quantity changes (via `addItem`, `updateItemQuantity`, `decreaseQuantity`, or login merge) | `$event->cart`, `$event->cartItem`, `$event->oldQuantity`, `$event->newQuantity` |
| `NhanChauKP\LaraCart\Events\CartItemRemoved` | An item is removed, or the cart is cleared | `$event->cart`, `$event->cartItem` |

### Listening to Events Example

```php
namespace App\Listeners;

use NhanChauKP\LaraCart\Events\CartItemAdded;
use Illuminate\Support\Facades\Log;

class LogCartActivity
{
    public function handle(CartItemAdded $event): void
    {
        Log::info("Item #{$event->cartItem->id} added to Cart #{$event->cart->id}");
    }
}
```

---

## Livewire 3 Integration Example

LaraCart works out-of-the-box with Livewire components:

### Header Cart Badge

```php
namespace App\Livewire\Partials;

use Livewire\Component;
use Livewire\Attributes\On;
use NhanChauKP\LaraCart\Facades\LaraCart;

class Header extends Component
{
    public int $cartCount = 0;

    #[On('cart-updated')]
    public function refreshCartCount(): void
    {
        $this->cartCount = LaraCart::count();
    }

    public function mount(): void
    {
        $this->cartCount = LaraCart::count();
    }

    public function render()
    {
        return view('livewire.partials.header');
    }
}
```

### Cart Manager Component

```php
namespace App\Livewire\Shop;

use App\Models\Product;
use Livewire\Component;
use NhanChauKP\LaraCart\Facades\LaraCart;

class CartPage extends Component
{
    public function increase(int $productId): void
    {
        $product = Product::findOrFail($productId);
        LaraCart::increaseQuantity($product);
        $this->dispatch('cart-updated');
    }

    public function decrease(int $productId): void
    {
        $product = Product::findOrFail($productId);
        LaraCart::decreaseQuantity($product);
        $this->dispatch('cart-updated');
    }

    public function remove(int $productId): void
    {
        $product = Product::findOrFail($productId);
        LaraCart::removeItem($product);
        $this->dispatch('cart-updated');
    }

    public function clear(): void
    {
        LaraCart::clear();
        $this->dispatch('cart-updated');
    }

    public function render()
    {
        return view('livewire.shop.cart-page', [
            'items' => LaraCart::getItems(),
            'total' => LaraCart::total(),
            'count' => LaraCart::count(),
            'totalQuantity' => LaraCart::totalQuantity(),
        ]);
    }
}
```

---

## Testing

Run tests using Pest or PHPUnit:

```bash
php artisan test --filter=LaraCartTest
```

Format code style using Laravel Pint:

```bash
vendor/bin/pint packages/laracart
```

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

---

## License

LaraCart is open-sourced software licensed under the [MIT license](LICENSE).