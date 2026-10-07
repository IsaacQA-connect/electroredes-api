<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Series extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_type',
        'series',
        'current_number',
    ];

    protected $casts = [
        'current_number' => 'integer',
    ];
}