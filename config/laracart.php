<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LaraCart Storage Driver
    |--------------------------------------------------------------------------
    |
    | You can specify the driver that should be used to store cart information.
    | Available options: 'database', 'session'
    |
    */
    'driver' => env('LARACART_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | LaraCart Session Key
    |--------------------------------------------------------------------------
    |
    | This value defines the session key that will be used to store
    | cart information when using the session driver.
    |
    */
    'session_key' => 'laracart',

    /*
    |--------------------------------------------------------------------------
    | LaraCart Default Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used to display the
    | cart total and item prices.
    |
    */
    'currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | LaraCart Models
    |--------------------------------------------------------------------------
    |
    | You can specify your own model classes here if you want to extend
    | the default cart and cart item models.
    |
    */
    'models' => [
        'cart' => \Nhanchaukp\LaraCart\Models\Cart::class,
        'cart_item' => \Nhanchaukp\LaraCart\Models\CartItem::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | LaraCart Cookie Configuration
    |--------------------------------------------------------------------------
    |
    | These settings are used when storing cart info in cookies for guest users.
    |
    */
    'cookie' => [
        'name' => 'laracart',
        'expires_after' => 30, // days
    ],
];
