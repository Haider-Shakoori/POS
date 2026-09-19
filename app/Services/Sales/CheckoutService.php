<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly PaymentSettlementService $payments,
    ) {
    }

    public function checkout(array $data, User $actor): Sale
    {
        return DB::transaction(function () use ($data, $actor): Sale {
            $customer = null;

            if (! empty($data['customer_id'])) {
                $customer = Customer::query()
                    ->whereKey($data['customer_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $customer) {
                    throw new DomainException('The selected customer is unavailable.');
                }
            }

            $sale = $this->sales->complete($data, $actor);

            return $this->payments->finalizeCheckout(
                sale: $sale,
                payments: $data['payments'] ?? [],
                customer: $customer,
                actor: $actor,
            );
        });
    }
}
