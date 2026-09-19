<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\InventoryCostLayer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\ShiftOpeningService;
use App\Services\Catalog\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseReturnService;
use App\Services\Sales\CheckoutService;
use App\Services\Suppliers\SupplierLedgerService;
use App\Services\Suppliers\SupplierPaymentService;
use App\Support\Decimal;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierPayablesReturnsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        app(ShiftOpeningService::class)->open([
            'idempotency_key' => (string) Str::uuid(),
            'terminal_id' => Terminal::query()->where('code', 'COUNTER-1')->firstOrFail()->id,
            'opening_cash' => '10000.00',
        ], $this->owner);

        $this->supplier = Supplier::create([
            'name' => 'Supplier Ledger Test',
            'phone' => '0700000099',
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_goods_receipt_and_initial_payment_reconcile_supplier_ledger(): void
    {
        [, $piece] = $this->makeProduct('LEDGER');

        $receipt = $this->receive(
            productUnitId: $piece->id,
            quantity: '10',
            unitCost: '10.0000',
            paidAmount: '30.00',
        );

        $this->assertSame('100.00', $receipt->net_total);
        $this->assertSame('30.00', $receipt->paid_amount);
        $this->assertSame('70.00', $receipt->balance_due);
        $this->assertSame('70.00', $this->supplier->fresh()->current_balance);

        $entries = SupplierLedgerEntry::query()
            ->where('supplier_id', $this->supplier->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame('goods_receipt', $entries[0]->entry_type);
        $this->assertSame('100.00', $entries[0]->credit);
        $this->assertSame('100.00', $entries[0]->balance_after);
        $this->assertSame('initial_purchase_payment', $entries[1]->entry_type);
        $this->assertSame('30.00', $entries[1]->debit);
        $this->assertSame('70.00', $entries[1]->balance_after);
    }

    public function test_later_supplier_payment_allocates_oldest_receipts_first(): void
    {
        [, $piece] = $this->makeProduct('ALLOC');

        $first = $this->receive($piece->id, '10', '10.0000', '0.00', receivedAt: '2026-09-19 08:00:00');
        $second = $this->receive($piece->id, '5', '10.0000', '0.00', receivedAt: '2026-09-19 09:00:00');

        $payment = app(SupplierPaymentService::class)->record($this->supplier, [
            'idempotency_key' => (string) Str::uuid(),
            'amount' => '120.00',
            'method' => 'cash',
            'reference' => 'PAY-120',
        ], $this->owner);

        $this->assertSame('30.00', $this->supplier->fresh()->current_balance);
        $this->assertSame('0.00', $first->fresh()->balance_due);
        $this->assertSame('100.00', $first->fresh()->paid_amount);
        $this->assertSame('30.00', $second->fresh()->balance_due);
        $this->assertSame('20.00', $second->fresh()->paid_amount);

        $allocations = $payment->allocations()->orderBy('id')->get();

        $this->assertCount(2, $allocations);
        $this->assertSame($first->id, $allocations[0]->goods_receipt_id);
        $this->assertSame('100.00', $allocations[0]->amount);
        $this->assertSame($second->id, $allocations[1]->goods_receipt_id);
        $this->assertSame('20.00', $allocations[1]->amount);
    }

    public function test_supplier_payment_can_settle_receipts_then_opening_balance(): void
    {
        $openingSupplier = Supplier::create([
            'name' => 'Opening Balance Supplier',
            'opening_balance' => '50.00',
            'current_balance' => '0.00',
            'is_active' => true,
        ]);

        app(SupplierLedgerService::class)->credit(
            supplier: $openingSupplier,
            amount: '50.00',
            entryType: 'opening_balance',
            referenceType: 'supplier',
            referenceId: $openingSupplier->id,
            referenceNumber: null,
            actor: $this->owner,
        );

        [, $piece] = $this->makeProduct('OPENING');
        $originalSupplier = $this->supplier;
        $this->supplier = $openingSupplier;

        try {
            $receipt = $this->receive($piece->id, '2', '10.0000');

            $payment = app(SupplierPaymentService::class)->record($openingSupplier, [
                'idempotency_key' => (string) Str::uuid(),
                'amount' => '40.00',
                'method' => 'bank',
            ], $this->owner);

            $this->assertSame('30.00', $openingSupplier->fresh()->current_balance);
            $this->assertSame('0.00', $receipt->fresh()->balance_due);
            $this->assertSame('20.00', $receipt->fresh()->paid_amount);
            $allocated = '0.00';

            foreach ($payment->allocations as $allocation) {
                $allocated = Decimal::add($allocated, $allocation->amount, 2);
            }

            $this->assertSame('20.00', $allocated);
            $this->assertSame('40.00', $payment->amount);
        } finally {
            $this->supplier = $originalSupplier;
        }
    }

    public function test_supplier_overpayment_rolls_back_payment_and_receipt_summary(): void
    {
        [, $piece] = $this->makeProduct('OVERPAY');
        $receipt = $this->receive($piece->id, '5', '10.0000');

        try {
            app(SupplierPaymentService::class)->record($this->supplier, [
                'idempotency_key' => (string) Str::uuid(),
                'amount' => '51.00',
                'method' => 'cash',
            ], $this->owner);

            $this->fail('Expected supplier overpayment to be rejected.');
        } catch (DomainException) {
            $this->assertSame('50.00', $this->supplier->fresh()->current_balance);
            $this->assertSame('50.00', $receipt->fresh()->balance_due);
            $this->assertSame('0.00', $receipt->fresh()->paid_amount);
            $this->assertSame(0, SupplierPayment::query()->count());
        }
    }

    public function test_partial_purchase_return_reverses_exact_stock_cost_and_supplier_payable(): void
    {
        [$product, $piece] = $this->makeProduct('RETURN');

        $receipt = $this->receive(
            productUnitId: $piece->id,
            quantity: '10',
            unitCost: '10.0000',
            paidAmount: '0.00',
            expenses: [['type' => 'transport', 'amount' => '20.00']],
        );

        $item = $receipt->items()->firstOrFail();
        $layer = InventoryCostLayer::query()
            ->where('source_stock_movement_id', $item->stock_movement_id)
            ->firstOrFail();

        $return = app(PurchaseReturnService::class)->post($receipt, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Damaged on delivery',
            'items' => [[
                'goods_receipt_item_id' => $item->id,
                'quantity' => '4',
            ]],
        ], $this->owner);

        $this->assertSame('48.00', $return->return_total);
        $this->assertSame('6.000000', $product->fresh()->stock_on_hand);
        $this->assertSame('6.000000', $layer->fresh()->remaining_quantity_base);
        $this->assertSame('72.00', $receipt->fresh()->balance_due);
        $this->assertSame('48.00', $receipt->fresh()->returned_total);
        $this->assertSame('72.00', $this->supplier->fresh()->current_balance);

        $returnItem = $return->items()->firstOrFail();
        $this->assertSame('4.000000', $returnItem->quantity_base);
        $this->assertSame('12.0000', $returnItem->unit_cost_base);
        $this->assertSame('48.0000', $returnItem->cost_amount);

        $movement = $returnItem->stockMovement()->firstOrFail();
        $this->assertSame('purchase_return', $movement->movement_type->value);
        $this->assertSame('-4.000000', $movement->quantity_base);
    }

    public function test_expiry_purchase_return_uses_original_receipt_batch(): void
    {
        [$product, $piece] = $this->makeProduct('EXP-RETURN', true);

        $receipt = $this->receive(
            productUnitId: $piece->id,
            quantity: '10',
            unitCost: '10.0000',
            batch: ['batch_number' => 'BATCH-RET', 'expires_at' => '2027-12-31'],
        );

        $item = $receipt->items()->firstOrFail();
        $batch = $product->batches()->where('batch_number', 'BATCH-RET')->firstOrFail();

        app(PurchaseReturnService::class)->post($receipt, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Supplier recall',
            'items' => [[
                'goods_receipt_item_id' => $item->id,
                'quantity' => '3',
            ]],
        ], $this->owner);

        $this->assertSame('7.000000', $batch->fresh()->stock_on_hand);

        $movement = StockMovement::query()
            ->where('movement_type', 'purchase_return')
            ->firstOrFail();

        $this->assertSame($batch->id, $movement->product_batch_id);
        $this->assertSame('-3.000000', $movement->quantity_base);
    }

    public function test_purchase_return_rejects_quantity_already_consumed_by_sales(): void
    {
        [$product, $piece] = $this->makeProduct('CONSUMED');
        $receipt = $this->receive($piece->id, '10', '10.0000');
        $item = $receipt->items()->firstOrFail();

        $cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();

        app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '6',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $cash->id,
                'amount' => '120.00',
                'tendered_amount' => '120.00',
            ]],
        ], $this->owner);

        try {
            app(PurchaseReturnService::class)->post($receipt, [
                'idempotency_key' => (string) Str::uuid(),
                'reason' => 'Attempt to return consumed stock',
                'items' => [[
                    'goods_receipt_item_id' => $item->id,
                    'quantity' => '5',
                ]],
            ], $this->owner);

            $this->fail('Expected return exceeding remaining source cost layer to fail.');
        } catch (DomainException) {
            $this->assertSame('4.000000', $product->fresh()->stock_on_hand);
            $this->assertSame('100.00', $receipt->fresh()->balance_due);
            $this->assertSame('100.00', $this->supplier->fresh()->current_balance);
            $this->assertSame(0, PurchaseReturn::query()->count());
            $this->assertSame(0, StockMovement::query()->where('movement_type', 'purchase_return')->count());
        }
    }

    public function test_fully_paid_purchase_return_creates_supplier_credit(): void
    {
        [, $piece] = $this->makeProduct('CREDIT');

        $receipt = $this->receive(
            productUnitId: $piece->id,
            quantity: '10',
            unitCost: '10.0000',
            paidAmount: '100.00',
        );
        $item = $receipt->items()->firstOrFail();

        $this->assertSame('0.00', $this->supplier->fresh()->current_balance);

        app(PurchaseReturnService::class)->post($receipt, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Paid stock returned',
            'items' => [[
                'goods_receipt_item_id' => $item->id,
                'quantity' => '2',
            ]],
        ], $this->owner);

        $this->assertSame('-20.00', $this->supplier->fresh()->current_balance);
        $this->assertSame('0.00', $receipt->fresh()->balance_due);
        $this->assertSame('20.00', $receipt->fresh()->returned_total);

        $entry = SupplierLedgerEntry::query()
            ->where('entry_type', 'purchase_return')
            ->firstOrFail();

        $this->assertSame('20.00', $entry->debit);
        $this->assertSame('-20.00', $entry->balance_after);
    }

    public function test_purchase_return_retry_is_idempotent_and_over_return_rolls_back(): void
    {
        [$product, $piece] = $this->makeProduct('RETURN-IDEM');
        $receipt = $this->receive($piece->id, '10', '10.0000');
        $item = $receipt->items()->firstOrFail();
        $key = (string) Str::uuid();

        $payload = [
            'idempotency_key' => $key,
            'reason' => 'Retry safe',
            'items' => [[
                'goods_receipt_item_id' => $item->id,
                'quantity' => '4',
            ]],
        ];

        $service = app(PurchaseReturnService::class);
        $first = $service->post($receipt, $payload, $this->owner);
        $second = $service->post($receipt, $payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PurchaseReturn::query()->count());
        $this->assertSame('6.000000', $product->fresh()->stock_on_hand);
        $this->assertSame('60.00', $receipt->fresh()->balance_due);

        try {
            $service->post($receipt, [
                'idempotency_key' => (string) Str::uuid(),
                'reason' => 'Too many',
                'items' => [[
                    'goods_receipt_item_id' => $item->id,
                    'quantity' => '7',
                ]],
            ], $this->owner);

            $this->fail('Expected over-return to be rejected.');
        } catch (DomainException) {
            $this->assertSame(1, PurchaseReturn::query()->count());
            $this->assertSame('6.000000', $product->fresh()->stock_on_hand);
            $this->assertSame('60.00', $receipt->fresh()->balance_due);
            $this->assertSame(1, StockMovement::query()->where('movement_type', 'purchase_return')->count());
        }
    }

    public function test_supplier_payment_retry_is_idempotent(): void
    {
        [, $piece] = $this->makeProduct('PAY-IDEM');
        $receipt = $this->receive($piece->id, '10', '10.0000');
        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'amount' => '40.00',
            'method' => 'cash',
            'reference' => 'PAY-IDEM',
        ];

        $service = app(SupplierPaymentService::class);
        $first = $service->record($this->supplier, $payload, $this->owner);
        $second = $service->record($this->supplier, $payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SupplierPayment::query()->count());
        $this->assertSame('60.00', $this->supplier->fresh()->current_balance);
        $this->assertSame('60.00', $receipt->fresh()->balance_due);
        $this->assertSame(1, SupplierLedgerEntry::query()->where('entry_type', 'supplier_payment')->count());
    }

    private function makeProduct(string $sku, bool $trackExpiry = false): array
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => $sku.'-'.Str::upper(Str::random(5)),
            'name_en' => 'Supplier Return Test Product',
            'name_fa' => 'محصول تست برگشت خرید',
            'name_ps' => 'د پېر بېرته ستنولو ازمایښتي محصول',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '20.00',
            'track_stock' => true,
            'track_expiry' => $trackExpiry,
        ], $this->owner);

        return [
            $product,
            $product->productUnits()->where('unit_id', $piece->id)->firstOrFail(),
        ];
    }

    private function receive(
        int $productUnitId,
        string $quantity,
        string $unitCost,
        string $paidAmount = '0.00',
        ?array $expenses = null,
        ?array $batch = null,
        string $receivedAt = '2026-09-19 08:00:00',
    ): GoodsReceipt {
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => $receivedAt,
            'paid_amount' => $paidAmount,
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'batch_number' => $batch['batch_number'] ?? null,
                'manufactured_at' => $batch['manufactured_at'] ?? null,
                'expires_at' => $batch['expires_at'] ?? null,
            ]],
        ];

        if ($expenses !== null) {
            $payload['expenses'] = $expenses;
        }

        if (Decimal::isPositive($paidAmount)) {
            $payload['payment_method'] = 'cash';
        }

        return app(GoodsReceiptService::class)->post($payload, $this->owner);
    }
}
