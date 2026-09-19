<?php

namespace App\Enums;

enum PurchaseExpenseType: string
{
    case Transport = 'transport';
    case Loading = 'loading';
    case Unloading = 'unloading';
    case Freight = 'freight';
    case Other = 'other';
}
