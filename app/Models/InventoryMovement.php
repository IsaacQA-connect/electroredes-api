<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryMovement extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'reference_type',
        'reference_id',
        'movement_date',
        'notes',
    ];

    protected $casts = [
        'type' => InventoryMovementType::class,
        'movement_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(InventoryMovementDetail::class);
    }
}
