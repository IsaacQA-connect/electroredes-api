<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'user_id',
        'purchase_date',
        'document_type',
        'document_number',
        'subtotal',
        'tax',
        'total',
        'status',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'status' => PurchaseStatus::class,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PurchaseDetail::class);
    }
}
