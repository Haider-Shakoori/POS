<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\GoodsReceipt;
use App\Models\HeldSale;
use App\Models\InventoryCostLayer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleRefund;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Customers\CustomerService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\HeldSaleService;
use App\Services\Sales\SaleReturnService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesReversalHeldTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private Supplier $supplier;
    private PaymentMethod $cash;
    private PaymentMethod $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->supplier = Supplier::create([
            'name' => 'Returns Test Supplier',
            'phone' => '0700000077',
            'opening_balance' => '0.00',
            'is_active' => true,
        ]);

        $this->cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('code', 'bank')->firstOrFail();
    }

    public function test_held_sale_has_no_stock_payment_or_receivable_effect_and_can_resume(): void
    {
        [$product, $piece] = $this->makeProduct('HOLD');
        $this->receive($piece->id, '10', '10.0000');

        $held = app(HeldSaleService::class)->hold([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
        ], $this->cashier);

        $this->assertSame('held', $held->status);
        $this->assertSame('10.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, SalePayment::query()->count());
        $this->assertSame(0, CustomerLedgerEntry::query()->count());
        $this->assertSame(0, StockMovement::query()->where('movement_type', 'sale')->count());

        $resumed = app(HeldSaleService::class)->resume($held, $this->cashier);

        $this->assertSame('resumed', $resumed->status);
        $this->assertNotNull($resumed->resumed_at);
        $this->assertSame('10.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_partial_paid_return_restores_stock_fifo_cost_and_creates_refund_evidence(): void
    {
        [$product, $piece] = $this->makeProduct('PAID-RETURN');
        $this->receive($piece->id, '10', '10.0000');

        $sale = $this->cashSale($piece->id, '4');

        $layer = InventoryCostLayer::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('6.000000', $layer->remaining_quantity_base);

        $return = app(SaleReturnService::class)->returnItems($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Customer changed mind',
            'items' => [[
                'sale_item_id' => $sale->items()->firstOrFail()->id,
                'quantity' => '2',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => null,
            ]],
        ], $this->owner);

        $sale->refresh();
        $layer->refresh();

        $this->assertSame('60.00', $return->return_total);
        $this->assertSame('20.00', $return->cogs_reversed);
        $this->assertSame('0.00', $return->receivable_reversed);
        $this->assertSame('60.00', $return->refund_total);

        $this->assertSame(SaleStatus::PartiallyReturned, $sale->status);
        $this->assertSame('60.00', $sale->returned_total);
        $this->assertSame('60.00', $sale->refunded_total);
        $this->assertSame('120.00', $sale->net_total);
        $this->assertSame('40.00', $sale->cogs_total);

        $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
        $this->assertSame('8.000000', $layer->remaining_quantity_base);

        $refund = $return->refunds()->firstOrFail();
        $this->assertSame('60.00', $refund->amount);
        $this->assertSame($this->cash->id, $refund->payment_method_id);
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'sale_return')->count());
    }

    public function test_expiry_return_restores_original_physical_batch_while_fifo_cost_layer_is_restored_separately(): void
    {
        [$product, $piece] = $this->makeProduct('EXPIRY-RETURN', true);

        $this->receive(
            $piece->id,
            '10',
            '10.0000',
            ['batch_number' => 'LATE', 'expires_at' => '2027-12-31'],
            '2026-09-19 09:00:00',
        );
        $this->receive(
            $piece->id,
            '10',
            '20.0000',
            ['batch_number' => 'EARLY', 'expires_at' => '2027-03-31'],
            '2026-09-19 10:00:00',
        );

        $sale = $this->cashSale($piece->id, '5');

        $early = ProductBatch::query()
            ->where('product_id', $product->id)
            ->where('batch_number', 'EARLY')
            ->firstOrFail();
        $late = ProductBatch::query()
            ->where('product_id', $product->id)
            ->where('batch_number', 'LATE')
            ->firstOrFail();

        $this->assertSame('5.000000', $early->stock_on_hand);
        $this->assertSame('10.000000', $late->stock_on_hand);

        app(SaleReturnService::class)->returnItems($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Returned sealed units',
            'items' => [[
                'sale_item_id' => $sale->items()->firstOrFail()->id,
                'quantity' => '3',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => null,
            ]],
        ], $this->owner);

        $this->assertSame('8.000000', $early->fresh()->stock_on_hand);
        $this->assertSame('10.000000', $late->fresh()->stock_on_hand);

        $firstLayer = InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->orderBy('received_at')
            ->firstOrFail();

        // Financial FIFO consumed LATE receipt cost first; its layer is restored independently of FEFO batch.
        $this->assertSame('8.000000', $firstLayer->remaining_quantity_base);
    }

    public function test_credit_sale_return_reduces_receivable_without_refund(): void
    {
        [$product, $piece] = $this->makeProduct('CREDIT-RETURN');
        $this->receive($piece->id, '10', '10.0000');
        $customer = $this->makeCustomer('Credit Return Customer', '500.00');

        $sale = $this->creditSale($piece->id, $customer, '2');

        $this->assertSame('60.00', $sale->balance_due);
        $this->assertSame('60.00', $customer->fresh()->current_balance);

        $return = app(SaleReturnService::class)->returnItems($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'One unit returned',
            'items' => [[
                'sale_item_id' => $sale->items()->firstOrFail()->id,
                'quantity' => '1',
            ]],
            'refunds' => [],
        ], $this->owner);

        $this->assertSame('30.00', $return->return_total);
        $this->assertSame('30.00', $return->receivable_reversed);
        $this->assertSame('0.00', $return->refund_total);
        $this->assertSame('30.00', $sale->fresh()->balance_due);
        $this->assertSame('30.00', $customer->fresh()->current_balance);
        $this->assertSame(0, SaleRefund::query()->count());

        $this->assertDatabaseHas('customer_ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'sale_return',
            'credit' => '30.00',
            'balance_after' => '30.00',
        ]);
    }

    public function test_return_reduces_receivable_first_then_refunds_only_paid_portion(): void
    {
        [$product, $piece] = $this->makeProduct('PARTIAL-RETURN');
        $this->receive($piece->id, '10', '10.0000');
        $customer = $this->makeCustomer('Partial Return Customer', '500.00');

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => '20.00',
                'tendered_amount' => '20.00',
            ]],
        ], $this->owner);

        $this->assertSame('40.00', $sale->balance_due);
        $this->assertSame('40.00', $customer->fresh()->current_balance);

        $return = app(SaleReturnService::class)->returnItems($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Full order returned',
            'items' => [[
                'sale_item_id' => $sale->items()->firstOrFail()->id,
                'quantity' => '2',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->bank->id,
                'amount' => null,
                'reference' => 'REF-001',
            ]],
        ], $this->owner);

        $this->assertSame('60.00', $return->return_total);
        $this->assertSame('40.00', $return->receivable_reversed);
        $this->assertSame('20.00', $return->refund_total);
        $this->assertSame('0.00', $sale->fresh()->balance_due);
        $this->assertSame('0.00', $customer->fresh()->current_balance);
        $this->assertSame('20.00', $return->refunds()->firstOrFail()->amount);
    }

    public function test_paid_return_requires_refund_evidence_and_rolls_back_when_missing(): void
    {
        [$product, $piece] = $this->makeProduct('REFUND-REQUIRED');
        $this->receive($piece->id, '10', '10.0000');
        $sale = $this->cashSale($piece->id, '2');

        try {
            app(SaleReturnService::class)->returnItems($sale, [
                'idempotency_key' => (string) Str::uuid(),
                'reason' => 'No refund method',
                'items' => [[
                    'sale_item_id' => $sale->items()->firstOrFail()->id,
                    'quantity' => '1',
                ]],
                'refunds' => [],
            ], $this->owner);

            $this->fail('Expected paid return without refund evidence to fail.');
        } catch (DomainException) {
            $this->assertSame(0, SaleReturn::query()->count());
            $this->assertSame(0, SaleRefund::query()->count());
            $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
            $this->assertSame('0.00', $sale->fresh()->returned_total);
        }
    }

    public function test_over_return_is_rejected_without_duplicate_stock_or_cost_restore(): void
    {
        [$product, $piece] = $this->makeProduct('OVER-RETURN');
        $this->receive($piece->id, '10', '10.0000');
        $sale = $this->cashSale($piece->id, '2');
        $saleItem = $sale->items()->firstOrFail();

        app(SaleReturnService::class)->returnItems($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'First unit returned',
            'items' => [[
                'sale_item_id' => $saleItem->id,
                'quantity' => '1',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => null,
            ]],
        ], $this->owner);

        try {
            app(SaleReturnService::class)->returnItems($sale, [
                'idempotency_key' => (string) Str::uuid(),
                'reason' => 'Too many units',
                'items' => [[
                    'sale_item_id' => $saleItem->id,
                    'quantity' => '2',
                ]],
                'refunds' => [[
                    'payment_method_id' => $this->cash->id,
                    'amount' => null,
                ]],
            ], $this->owner);

            $this->fail('Expected over-return to fail.');
        } catch (DomainException) {
            $this->assertSame(1, SaleReturn::query()->count());
            $this->assertSame(1, SaleRefund::query()->count());
            $this->assertSame('9.000000', $product->fresh()->stock_on_hand);
            $this->assertSame('30.00', $sale->fresh()->returned_total);
            $this->assertSame(1, StockMovement::query()->where('movement_type', 'sale_return')->count());
        }
    }

    public function test_return_retry_is_idempotent(): void
    {
        [$product, $piece] = $this->makeProduct('RETURN-IDEM');
        $this->receive($piece->id, '10', '10.0000');
        $sale = $this->cashSale($piece->id, '2');

        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'reason' => 'Retry safe return',
            'items' => [[
                'sale_item_id' => $sale->items()->firstOrFail()->id,
                'quantity' => '1',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => null,
            ]],
        ];

        $service = app(SaleReturnService::class);
        $first = $service->returnItems($sale, $payload, $this->owner);
        $second = $service->returnItems($sale, $payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SaleReturn::query()->count());
        $this->assertSame(1, SaleRefund::query()->count());
        $this->assertSame('9.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'sale_return')->count());
    }

    public function test_void_after_partial_return_reverses_only_remaining_quantity(): void
    {
        [$product, $piece] = $this->makeProduct('VOID-REMAINING');
        $this->receive($piece->id, '10', '10.0000');
        $sale = $this->cashSale($piece->id, '4');
        $saleItem = $sale->items()->firstOrFail();

        app(SaleReturnService::class)->returnItems($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Partial return first',
            'items' => [[
                'sale_item_id' => $saleItem->id,
                'quantity' => '1',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => null,
            ]],
        ], $this->owner);

        $void = app(SaleReturnService::class)->void($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Wrong transaction',
            'refunds' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => null,
            ]],
        ], $this->owner);

        $sale->refresh();

        $this->assertSame('90.00', $void->return_total);
        $this->assertSame(SaleStatus::Voided, $sale->status);
        $this->assertSame('120.00', $sale->returned_total);
        $this->assertSame('120.00', $sale->refunded_total);
        $this->assertSame('10.000000', $product->fresh()->stock_on_hand);

        $layer = InventoryCostLayer::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('10.000000', $layer->remaining_quantity_base);
        $this->assertSame(2, SaleReturn::query()->count());
        $this->assertSame(2, SaleRefund::query()->count());
    }

    public function test_cashier_cannot_void_sale(): void
    {
        [$product, $piece] = $this->makeProduct('VOID-PERMISSION');
        $this->receive($piece->id, '10', '10.0000');
        $sale = $this->cashSale($piece->id, '2');

        $this->assertFalse($this->cashier->hasPermission('sales.void'));

        $this->expectException(DomainException::class);

        try {
            app(SaleReturnService::class)->void($sale, [
                'idempotency_key' => (string) Str::uuid(),
                'reason' => 'Attempted cashier void',
                'refunds' => [[
                    'payment_method_id' => $this->cash->id,
                    'amount' => null,
                ]],
            ], $this->cashier);
        } finally {
            $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, SaleReturn::query()->count());
        }
    }

    private function makeProduct(string $sku, bool $trackExpiry = false): array
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => $sku.'-'.Str::upper(Str::random(5)),
            'name_en' => 'Return Test Product',
            'name_fa' => 'محصول تست برگشت',
            'name_ps' => 'د بېرته ستنولو ازمایښتي محصول',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '30.00',
            'minimum_selling_price' => '25.00',
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
        ?array $batch = null,
        ?string $receivedAt = null,
    ): GoodsReceipt {
        return app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => $receivedAt ?? '2026-09-19 08:00:00',
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'batch_number' => $batch['batch_number'] ?? null,
                'manufactured_at' => $batch['manufactured_at'] ?? null,
                'expires_at' => $batch['expires_at'] ?? null,
            ]],
        ], $this->owner);
    }

    private function cashSale(int $productUnitId, string $quantity): Sale
    {
        $amount = (string) ((int) $quantity * 30).'.00';

        return app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => $amount,
                'tendered_amount' => $amount,
            ]],
        ], $this->owner);
    }

    private function creditSale(int $productUnitId, Customer $customer, string $quantity): Sale
    {
        return app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [],
        ], $this->owner);
    }

    private function makeCustomer(string $name, string $creditLimit): Customer
    {
        return app(CustomerService::class)->create([
            'name' => $name,
            'phone' => '07'.random_int(10000000, 99999999),
            'credit_limit' => $creditLimit,
            'opening_balance' => '0.00',
        ], $this->owner);
    }
}
