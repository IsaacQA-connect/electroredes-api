<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'code',
        'barcode',
        'name',
        'description',
        'unit',
        'cost',
        'sale_price',
        'minimum_stock',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock' => 'decimal:2',
            'minimum_stock' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
