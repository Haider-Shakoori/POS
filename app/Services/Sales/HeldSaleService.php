<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\HeldSale;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentNumberService;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class HeldSaleService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly SalesPricingService $pricing,
        private readonly AuditLogger $audit,
    ) {
    }

    public function hold(array $data, User $actor): HeldSale
    {
        return DB::transaction(function () use ($data, $actor): HeldSale {
            if (! $actor->hasPermission('sales.hold')) {
                throw new DomainException('The user is not allowed to hold sales.');
            }

            if ($existing = HeldSale::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ((int) $existing->cashier_user_id !== (int) $actor->id) {
                    throw new DomainException('The hold idempotency key belongs to another cashier.');
                }

                return $existing->load('items');
            }

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

            $saleDiscount = Decimal::normalize($data['sale_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($saleDiscount)) {
                throw new DomainException('Held sale discount cannot be negative.');
            }

            if (Decimal::isPositive($saleDiscount) && ! $actor->hasPermission('sales.discount')) {
                throw new DomainException('The user is not allowed to hold a discounted sale.');
            }

            $items = $this->prepareItems($data['items'] ?? [], $actor);

            $held = HeldSale::create([
                'number' => $this->numbers->next('held_sale', 'HLD'),
                'idempotency_key' => $data['idempotency_key'],
                'cashier_user_id' => $actor->id,
                'customer_id' => $customer?->id,
                'customer_name_snapshot' => $customer?->name ?? 'Walk-in Customer',
                'sale_discount_amount' => $saleDiscount,
                'status' => 'held',
                'notes' => $data['notes'] ?? null,
                'held_at' => now(),
            ]);

            foreach ($items as $item) {
                $held->items()->create($item);
            }

            $this->audit->record(
                'sales.held.created',
                model: $held,
                newValues: [
                    'number' => $held->number,
                    'customer_id' => $held->customer_id,
                    'item_count' => count($items),
                ],
                actor: $actor,
            );

            return $held->fresh(['customer', 'items.productUnit.product', 'items.productUnit.unit']);
        });
    }

    public function resume(HeldSale $held, User $actor): HeldSale
    {
        return DB::transaction(function () use ($held, $actor): HeldSale {
            $locked = HeldSale::query()->with('items')->lockForUpdate()->findOrFail($held->id);
            $this->authorizeOwnership($locked, $actor);

            if ($locked->status !== 'held') {
                throw new DomainException('Only an active held sale can be resumed.');
            }

            $locked->forceFill([
                'status' => 'resumed',
                'resumed_at' => now(),
            ])->save();

            $this->audit->record(
                'sales.held.resumed',
                model: $locked,
                newValues: ['status' => 'resumed'],
                actor: $actor,
            );

            return $locked->fresh(['customer', 'items.productUnit.product', 'items.productUnit.unit']);
        });
    }

    public function release(HeldSale $held, User $actor): HeldSale
    {
        return DB::transaction(function () use ($held, $actor): HeldSale {
            $locked = HeldSale::query()->lockForUpdate()->findOrFail($held->id);
            $this->authorizeOwnership($locked, $actor);

            if ($locked->status !== 'held') {
                throw new DomainException('Only an active held sale can be released.');
            }

            $locked->forceFill([
                'status' => 'released',
                'released_at' => now(),
            ])->save();

            $this->audit->record(
                'sales.held.released',
                model: $locked,
                newValues: ['status' => 'released'],
                actor: $actor,
            );

            return $locked->fresh();
        });
    }

    private function prepareItems(array $items, User $actor): array
    {
        if ($items === []) {
            throw new DomainException('A held sale requires at least one item.');
        }

        $prepared = [];
        $seen = [];

        foreach ($items as $item) {
            $productUnitId = (int) ($item['product_unit_id'] ?? 0);

            if ($productUnitId <= 0 || isset($seen[$productUnitId])) {
                throw new DomainException('Each held product unit must be unique.');
            }

            $seen[$productUnitId] = true;

            $productUnit = ProductUnit::query()
                ->with(['product', 'unit'])
                ->whereKey($productUnitId)
                ->where('can_sell', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->first();

            if (! $productUnit) {
                throw new DomainException('The selected held product unit is unavailable.');
            }

            $quantity = Decimal::normalize($item['quantity']);

            if (! Decimal::isPositive($quantity)) {
                throw new DomainException('Held sale quantity must be greater than zero.');
            }

            if (Decimal::fractionalDigits($item['quantity']) > $productUnit->unit->decimal_places) {
                throw new DomainException('Held sale quantity exceeds the selected unit precision.');
            }

            $lineDiscount = Decimal::normalize($item['line_discount_amount'] ?? '0', 2);

            if (Decimal::isNegative($lineDiscount)) {
                throw new DomainException('Held sale line discount cannot be negative.');
            }

            if (Decimal::isPositive($lineDiscount) && ! $actor->hasPermission('sales.discount')) {
                throw new DomainException('The user is not allowed to hold a discounted item.');
            }

            $prices = $this->pricing->resolve($productUnit);
            $lineSubtotal = Decimal::multiplyRounded($quantity, $prices['price'], 2);

            if (Decimal::compare($lineDiscount, $lineSubtotal) > 0) {
                throw new DomainException('Held sale line discount exceeds the line subtotal.');
            }

            $prepared[] = [
                'product_unit_id' => $productUnit->id,
                'product_name_snapshot' => $productUnit->product->localizedName(),
                'sku_snapshot' => $productUnit->product->sku,
                'unit_name_snapshot' => $productUnit->unit->localizedName(),
                'quantity' => $quantity,
                'line_discount_amount' => $lineDiscount,
                'unit_price_snapshot' => $prices['price'],
            ];
        }

        return $prepared;
    }

    private function authorizeOwnership(HeldSale $held, User $actor): void
    {
        if (! $actor->hasPermission('sales.hold')) {
            throw new DomainException('The user is not allowed to manage held sales.');
        }

        if ((int) $held->cashier_user_id !== (int) $actor->id && ! $actor->hasPermission('sales.void')) {
            throw new DomainException('This held sale belongs to another cashier.');
        }
    }
}
