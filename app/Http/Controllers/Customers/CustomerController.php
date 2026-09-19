<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Services\Customers\CustomerService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('alternate_phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('customers.index', compact('customers', 'search'));
    }

    public function store(
        StoreCustomerRequest $request,
        CustomerService $customers,
    ): JsonResponse|RedirectResponse {
        try {
            $customer = $customers->create($request->validated(), $request->user());
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['customer' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.customer_created'),
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'credit_limit' => $customer->credit_limit,
                    'current_balance' => $customer->current_balance,
                ],
            ], 201);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('ui.customer_created'));
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'ledgerEntries' => fn ($query) => $query->latest('occurred_at')->limit(100),
            'sales' => fn ($query) => $query->latest('sold_at')->limit(25),
            'collections' => fn ($query) => $query->latest('collected_at')->limit(25),
        ]);

        return view('customers.show', [
            'customer' => $customer,
            'paymentMethods' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
