<?php

namespace App\Enums;

enum PurchasePaymentMethod: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Other = 'other';
}
