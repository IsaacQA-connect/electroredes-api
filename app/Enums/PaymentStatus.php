<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';
}