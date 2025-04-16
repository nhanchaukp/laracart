<?php

namespace NhanChauKP\LaraCart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a shopping cart.
 *
 * @property int|null $user_id The ID of the user associated with the cart.
 * @property int|null $session_id The session ID associated with the cart.
 * @property float $discount_percent The discount percentage applied to the cart.
 * @property float $total The total price of the cart.
 * @property CartItem[] $items The items in the cart.
 */
class Cart extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'discount_percent', 'total',
    ];

    protected $casts = [
        'discount_percent' => 'float',
        'total' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
