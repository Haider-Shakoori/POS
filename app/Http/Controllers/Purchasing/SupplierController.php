<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Suppliers\SupplierLedgerService;
use App\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount(['purchaseOrders', 'goodsReceipts'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('contact_person', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('purchasing.suppliers.index', compact('suppliers'));
    }

    public function store(
        StoreSupplierRequest $request,
        AuditLogger $audit,
        SupplierLedgerService $ledger,
    ): RedirectResponse {
        $openingBalance = Decimal::normalize($request->input('opening_balance', '0'), 2);

        $supplier = Supplier::create([
            ...$request->validated(),
            'opening_balance' => $openingBalance,
            'current_balance' => '0.00',
            'is_active' => true,
        ]);

        if (Decimal::isPositive($openingBalance)) {
            $ledger->credit(
                supplier: $supplier,
                amount: $openingBalance,
                entryType: 'opening_balance',
                referenceType: 'supplier',
                referenceId: $supplier->id,
                referenceNumber: null,
                actor: $request->user(),
                notes: 'Supplier opening payable balance.',
            );
        }

        $audit->record(
            'purchasing.supplier.created',
            $supplier,
            newValues: $supplier->only(['name', 'phone', 'opening_balance']),
            actor: $request->user(),
        );

        return redirect()
            ->route('purchasing.suppliers.show', $supplier)
            ->with('status', __('ui.supplier_created'));
    }

    public function show(Supplier $supplier): View
    {
        $supplier->loadCount(['purchaseOrders', 'goodsReceipts']);
        $supplier->load([
            'purchaseOrders' => fn ($query) => $query->latest('order_date')->limit(10),
            'goodsReceipts' => fn ($query) => $query->latest('received_at')->limit(10),
        ]);

        return view('purchasing.suppliers.show', compact('supplier'));
    }
}
