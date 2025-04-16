# LaraCart

LaraCart is a Laravel package that provides a flexible and extensible shopping cart system. It supports multiple storage drivers (e.g., database, session) and offers a wide range of features for managing shopping carts in your Laravel application.

## Features

- Multiple storage drivers (Database, Session)
- Add, update, and remove items from the cart
- Support for polymorphic itemable models
- Discount management
- Assign carts to users
- Clear and count items in the cart
- Retrieve cart totals and quantities

## Installation

1. Install the package via Composer:

   ```bash
   composer require nhanchaukp/laracart
   ```

2. Publish the configuration and migration files:

   ```bash
   # config
   php artisan vendor:publish --provider="NhanChauKP\LaraCart\Providers\LaraCartServiceProvider" --tag=laracart-config

   #migrations
   php artisan vendor:publish --provider="NhanChauKP\LaraCart\Providers\LaraCartServiceProvider" --tag=laracart-migrations
   ```

3. Run the migrations:

   ```bash
   php artisan migrate
   ```

4. Configure the driver in `config/laracart.php` (default: `database`).

## Usage

### Basic Usage

#### Add an Item to the Cart

```php
use NhanChauKP\LaraCart\Facades\LaraCart;

$item = Product::find(1); // Example item
LaraCart::addItem($item, 2, 100.00, ['color' => 'red']);
```

#### Retrieve the Cart

```php
$cart = LaraCart::getCart();
```

#### Get All Items

```php
$items = LaraCart::getItems();
```

#### Remove an Item

```php
LaraCart::removeItem($item);
```

#### Clear the Cart

```php
LaraCart::clear();
```

### Advanced Features

#### Set a Discount

```php
LaraCart::setDiscount(10); // 10% discount
```

#### Assign Cart to a User

```php
LaraCart::assignToUser($userId);
```

#### Switch Drivers

```php
LaraCart::driver('session');
```

## Configuration

The configuration file `config/laracart.php` allows you to customize the following:

- `driver`: The storage driver (`database` or `session`).
- `session_key`: The session key for the session driver.
- `currency`: The default currency.
- `models`: Custom models for `Cart` and `CartItem`.
- `cookie`: Cookie settings for guest users.

## Models

### Cart

The `Cart` model represents a shopping cart and includes the following attributes:

- `user_id`: The ID of the user associated with the cart.
- `session_id`: The session ID for guest users.
- `discount_percent`: The discount percentage applied to the cart.
- `total`: The total price of the cart.

### CartItem

The `CartItem` model represents an item in the cart and includes the following attributes:

- `cart_id`: The ID of the cart.
- `itemable`: Polymorphic relation to the itemable model.
- `quantity`: The quantity of the item.
- `price`: The price of the item.
- `options`: Additional options for the item.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).