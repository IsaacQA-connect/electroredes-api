<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'code',
        'channel',
        'status',
        'subtotal',
        'discount',
        'total',
        'order_date',
        'notes',
        // Campos de Facturación / SUNAT
        'invoice_type',
        'document_type',
        'document_number',
        'business_name',
        'invoice_number',
        'pdf_url',
        'xml_url',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'subtotal'   => 'decimal:2',
        'discount'   => 'decimal:2',
        'total'      => 'decimal:2',
        'status'     => OrderStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * Relación con la Factura / Comprobante SUNAT
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            // Genera el código usando el ID auto-incremental recién creado
            // Ejemplo: ORD-2026-00001
            $year = date('Y');
            $paddedId = str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);

            $order->code = "ORD-{$year}-{$paddedId}";
            $order->saveQuietly(); // Guarda sin re-disparar eventos
        });
    }
}