<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierPaymentRequest;
use App\Models\Supplier;
use App\Services\Suppliers\SupplierPaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class SupplierPaymentController extends Controller
{
    public function store(
        StoreSupplierPaymentRequest $request,
        Supplier $supplier,
        SupplierPaymentService $payments,
    ): RedirectResponse {
        try {
            $payment = $payments->record($supplier, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()
            ->route('purchasing.suppliers.show', $supplier)
            ->with('status', __('ui.supplier_payment_recorded', ['number' => $payment->number]));
    }
}
