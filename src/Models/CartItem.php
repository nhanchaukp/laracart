<?php

namespace NhanChauKP\LaraCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents an item in the shopping cart.
 *
 * @property int $cart_id The ID of the cart this item belongs to.
 * @property MorphTo $itemable The polymorphic relation to the itemable model.
 * @property int $quantity The quantity of the item in the cart.
 * @property float $price The price of the item.
 * @property array $options Additional options for the item.
 */
class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'itemable_id', 'itemable_type', 'quantity', 'price', 'options',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'options' => 'array',
    ];

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
