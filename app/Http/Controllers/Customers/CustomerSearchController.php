<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'max:180'],
        ]);

        $term = trim((string) $request->input('q'));

        $customers = Customer::query()
            ->where('is_active', true)
            ->where(function ($query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('alternate_phone', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'available_credit' => max(
                    0,
                    (float) $customer->credit_limit - (float) $customer->current_balance
                ),
            ])
            ->values();

        return response()->json(['data' => $customers]);
    }
}
