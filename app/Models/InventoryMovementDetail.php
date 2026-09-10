<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovementDetail extends Model
{
    protected $fillable = [
        'inventory_movement_id',
        'product_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(
            InventoryMovement::class,
            'inventory_movement_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
