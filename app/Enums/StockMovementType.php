<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OpeningStock = 'opening_stock';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case Damage = 'damage';
    case Expiry = 'expiry';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
}
