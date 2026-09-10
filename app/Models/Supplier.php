<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Supplier extends Model
{
    protected $fillable = [
        'document_type',
        'document_number',
        'business_name',
        'contact_name',
        'phone',
        'email',
        'address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
