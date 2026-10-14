<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'customer_id',
        'document_type',
        'series',
        'number',
        'client_doc_type',
        'client_doc_number',
        'client_name',
        'client_address',
        'currency',
        'op_taxed',
        'op_exonerated',
        'op_unaffected',
        'igv',
        'total',
        'payment_method',
        'sunat_status',
        'sunat_description',
        'sunat_response_code',
        'pdf_url',
        'pdf_path',
        'xml_url',
        'xml_path',
        'cdr_url',
        'cdr_path',
    ];

    protected $casts = [
        'op_taxed'      => 'decimal:2',
        'op_exonerated' => 'decimal:2',
        'op_unaffected' => 'decimal:2',
        'igv'           => 'decimal:2',
        'total'         => 'decimal:2',
        'number'        => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /**
     * Usuario/Cajero que emitió el comprobante.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Cliente asociado (mapeado a customer_id).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Orden de venta origen de este comprobante.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Detalle de ítems del comprobante.
     */
    public function details(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    /**
     * Scope para filtrar por tipo de documento ('01', '03', 'NV').
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Accessor para el número formateado completo (Ej: F001-00000001).
     */
    public function getFullNumberAttribute(): string
    {
        return $this->series . '-' . str_pad((string) $this->number, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Accessor para URL de PDF garantizando fallback al proxy
     */
    public function getPdfUrlAttribute($value): string
    {
        return $value ?? $this->pdf_path ?? url("/api/invoices/{$this->id}/pdf");
    }

    /**
     * Accessor para URL de XML garantizando fallback al proxy
     */
    public function getXmlUrlAttribute($value): string
    {
        return $value ?? $this->xml_path ?? url("/api/invoices/{$this->id}/xml");
    }
}