<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Restock = 'restock';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Return = 'return';
}
