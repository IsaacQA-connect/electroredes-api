<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case YAPE = 'YAPE';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case ONLINE_PAYMENT = 'ONLINE_PAYMENT';
}