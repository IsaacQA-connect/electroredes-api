<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'igv',
        'total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'igv'        => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    /**
     * Comprobante al que pertenece el ítem.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Producto relacionado (opcional, si existe en catálogo).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}