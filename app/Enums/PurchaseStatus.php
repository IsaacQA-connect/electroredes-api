<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case DRAFT = 'DRAFT';
    case RECEIVED = 'RECEIVED';
    case CANCELLED = 'CANCELLED';
}