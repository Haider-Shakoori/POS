<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCollectionRequest;
use App\Models\Customer;
use App\Services\Customers\CustomerCollectionService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class CustomerCollectionController extends Controller
{
    public function store(
        StoreCollectionRequest $request,
        Customer $customer,
        CustomerCollectionService $collections,
    ): RedirectResponse {
        try {
            $collections->collect($customer, $request->validated(), $request->user());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['collection' => $exception->getMessage()]);
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('ui.collection_recorded'));
    }
}
