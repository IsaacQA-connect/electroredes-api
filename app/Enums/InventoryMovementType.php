<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case INITIAL_STOCK = 'INITIAL_STOCK';
    case PURCHASE_ENTRY = 'PURCHASE_ENTRY';
    case SALE_EXIT = 'SALE_EXIT';
    case SERVICE_EXIT = 'SERVICE_EXIT';
    case RETURN_ENTRY = 'RETURN_ENTRY';
    case ADJUSTMENT_ENTRY = 'ADJUSTMENT_ENTRY';
    case ADJUSTMENT_EXIT = 'ADJUSTMENT_EXIT';
}