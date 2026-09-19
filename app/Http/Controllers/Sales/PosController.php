<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __invoke(): View
    {
        return view('pos.index', [
            'canDiscount' => auth()->user()->hasPermission('sales.discount'),
            'canOverrideMinimum' => auth()->user()->hasPermission('sales.override_min_price'),
        ]);
    }
}
