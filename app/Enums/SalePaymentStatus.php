<?php

namespace App\Enums;

enum SalePaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
}
