<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'code',
        'barcode',
        'name',
        'description',
        'image_path',
        'unit',
        'cost',
        'sale_price',
        'stock',
        'minimum_stock',
        'status',
    ];

    /**
     * Atributos calculados que se incluyen automáticamente en las respuestas JSON
     */
    protected $appends = ['image_url'];

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

    /**
     * Genera automáticamente la URL de S3 o Local según la configuración en .env
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->image_path) {
                    return null; // O la ruta a un placeholder predeterminado
                }

                // Genera la URL pública dependiendo del disco activo (s3 o public)
                return Storage::disk(config('filesystems.default'))->url($this->image_path);
            }
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}