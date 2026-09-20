<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\ShiftOpeningService;
use App\Services\Catalog\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PurchasingCoreTest extends TestCase
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
            'name' => 'Kabul Wholesale Supply',
            'phone' => '0700000000',
            'opening_balance' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_purchase_order_creation_and_approval_do_not_change_stock(): void
    {
        [$product, $cartonUnit] = $this->makeProduct();

        $order = app(PurchaseOrderService::class)->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-19',
            'items' => [[
                'product_unit_id' => $cartonUnit->id,
                'quantity' => '10',
                'unit_cost' => '720.0000',
                'line_discount_amount' => '0.00',
            ]],
        ], $this->owner);

        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertSame('7200.00', $order->net_total);
        $this->assertSame('0.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(0, StockMovement::query()->count());

        $approved = app(PurchaseOrderService::class)->approve($order, $this->owner);

        $this->assertSame(PurchaseOrderStatus::Approved, $approved->status);
        $this->assertSame('0.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_partial_goods_receipt_allocates_expense_into_landed_cost_and_updates_po(): void
    {
        [$product, $cartonUnit] = $this->makeProduct();
        $order = $this->approvedOrder($cartonUnit->id, '10', '720.0000');
        $poItem = $order->items()->firstOrFail();

        $receipt = app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $order->id,
            'received_at' => now()->toDateTimeString(),
            'paid_amount' => '1000.00',
            'payment_method' => 'cash',
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'quantity' => '4',
                'unit_cost' => '720.0000',
                'line_discount_amount' => '0.00',
            ]],
            'expenses' => [[
                'type' => 'transport',
                'description' => 'Delivery to shop',
                'amount' => '120.00',
            ]],
        ], $this->owner);

        $item = $receipt->items->first();

        $this->assertSame('2880.00', $receipt->subtotal);
        $this->assertSame('120.00', $receipt->expense_total);
        $this->assertSame('3000.00', $receipt->net_total);
        $this->assertSame('1000.00', $receipt->paid_amount);
        $this->assertSame('2000.00', $receipt->balance_due);

        $this->assertSame('120.00', $item->allocated_expense);
        $this->assertSame('3000.00', $item->landed_total);
        $this->assertSame('750.0000', $item->source_unit_landed_cost);
        $this->assertSame('31.2500', $item->base_unit_landed_cost);

        $this->assertSame('96.000000', $product->fresh()->stock_on_hand);
        $this->assertSame('31.25', $product->fresh()->purchase_cost);

        $movement = $item->stockMovement()->firstOrFail();
        $this->assertSame('96.000000', $movement->quantity_base);
        $this->assertSame('31.2500', $movement->unit_cost_base);

        $order->refresh();
        $poItem->refresh();

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $order->status);
        $this->assertSame('4.000000', $poItem->received_quantity);
        $this->assertCount(1, $receipt->payments);
    }

    public function test_second_receipt_can_complete_a_purchase_order(): void
    {
        [$product, $cartonUnit] = $this->makeProduct();
        $order = $this->approvedOrder($cartonUnit->id, '10', '720.0000');
        $poItem = $order->items()->firstOrFail();

        $service = app(GoodsReceiptService::class);

        $service->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $order->id,
            'received_at' => '2026-09-19 11:00:00',
            'paid_amount' => '0.00',
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'quantity' => '4',
                'unit_cost' => '720.0000',
            ]],
        ], $this->owner);

        $service->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $order->id,
            'received_at' => '2026-09-19 13:00:00',
            'paid_amount' => '0.00',
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'quantity' => '6',
                'unit_cost' => '720.0000',
            ]],
        ], $this->owner);

        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame('10.000000', $poItem->fresh()->received_quantity);
        $this->assertSame('240.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(2, GoodsReceipt::query()->where('purchase_order_id', $order->id)->count());
    }

    public function test_goods_receipt_show_page_renders_with_cost_layer_remaining_quantity(): void
    {
        $this->withoutVite();

        [$product, $cartonUnit] = $this->makeProduct();
        $order = $this->approvedOrder($cartonUnit->id, '10', '720.0000');
        $poItem = $order->items()->firstOrFail();

        $receipt = app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $order->id,
            'received_at' => now()->toDateTimeString(),
            'paid_amount' => '0.00',
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'quantity' => '4',
                'unit_cost' => '720.0000',
                'line_discount_amount' => '0.00',
            ]],
        ], $this->owner);

        $this->assertNotNull($receipt->items->firstOrFail()->costLayer);

        $this->actingAs($this->owner)
            ->get(route('purchasing.receipts.show', $receipt))
            ->assertOk()
            ->assertSee($receipt->number);
    }

    public function test_receipt_discount_and_expense_allocations_reconcile_exactly(): void
    {
        [$productA, $unitA] = $this->makeProduct('ALLOC-A');
        [$productB, $unitB] = $this->makeProduct('ALLOC-B');

        $receipt = app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => '2026-09-19 14:00:00',
            'receipt_discount_amount' => '10.00',
            'paid_amount' => '0.00',
            'items' => [
                [
                    'product_unit_id' => $unitA->id,
                    'quantity' => '1',
                    'unit_cost' => '100.0000',
                    'line_discount_amount' => '0.00',
                ],
                [
                    'product_unit_id' => $unitB->id,
                    'quantity' => '1',
                    'unit_cost' => '200.0000',
                    'line_discount_amount' => '0.00',
                ],
            ],
            'expenses' => [[
                'type' => 'freight',
                'amount' => '7.00',
            ]],
        ], $this->owner);

        $discountAllocated = '0.00';
        $expenseAllocated = '0.00';
        $landedTotal = '0.00';

        foreach ($receipt->items as $item) {
            $discountAllocated = \App\Support\Decimal::add($discountAllocated, $item->allocated_receipt_discount, 2);
            $expenseAllocated = \App\Support\Decimal::add($expenseAllocated, $item->allocated_expense, 2);
            $landedTotal = \App\Support\Decimal::add($landedTotal, $item->landed_total, 2);
        }

        $this->assertSame('10.00', $discountAllocated);
        $this->assertSame('7.00', $expenseAllocated);
        $this->assertSame('297.00', $landedTotal);
        $this->assertSame('297.00', $receipt->net_total);
        $this->assertSame('24.000000', $productA->fresh()->stock_on_hand);
        $this->assertSame('24.000000', $productB->fresh()->stock_on_hand);
    }

    public function test_expiry_tracked_receipt_creates_batch_and_stock_movement(): void
    {
        [$product, $cartonUnit] = $this->makeProduct('EXPIRY', true);

        $receipt = app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => '2026-09-19 15:00:00',
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $cartonUnit->id,
                'quantity' => '2',
                'unit_cost' => '720.0000',
                'batch_number' => 'LOT-2026-09',
                'manufactured_at' => '2026-08-01',
                'expires_at' => '2027-08-01',
            ]],
        ], $this->owner);

        $batch = $product->batches()->where('batch_number', 'LOT-2026-09')->firstOrFail();

        $this->assertSame('48.000000', $batch->stock_on_hand);
        $this->assertSame('48.000000', $product->fresh()->stock_on_hand);
        $this->assertSame($batch->id, $receipt->items->first()->stockMovement->product_batch_id);
    }

    public function test_over_receiving_rolls_back_entire_receipt(): void
    {
        [$product, $cartonUnit] = $this->makeProduct();
        $order = $this->approvedOrder($cartonUnit->id, '10', '720.0000');
        $poItem = $order->items()->firstOrFail();

        try {
            app(GoodsReceiptService::class)->post([
                'idempotency_key' => (string) Str::uuid(),
                'supplier_id' => $this->supplier->id,
                'purchase_order_id' => $order->id,
                'received_at' => '2026-09-19 16:00:00',
                'paid_amount' => '0.00',
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'quantity' => '11',
                    'unit_cost' => '720.0000',
                ]],
            ], $this->owner);

            $this->fail('Expected over-receiving to be rejected.');
        } catch (DomainException) {
            $this->assertSame('0.000000', $product->fresh()->stock_on_hand);
            $this->assertSame('0.000000', $poItem->fresh()->received_quantity);
            $this->assertSame(0, GoodsReceipt::query()->count());
            $this->assertSame(0, StockMovement::query()->count());
        }
    }

    public function test_goods_receipt_idempotency_does_not_duplicate_inventory(): void
    {
        [$product, $cartonUnit] = $this->makeProduct();
        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'supplier_id' => $this->supplier->id,
            'received_at' => '2026-09-19 17:00:00',
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $cartonUnit->id,
                'quantity' => '2',
                'unit_cost' => '720.0000',
            ]],
        ];

        $service = app(GoodsReceiptService::class);
        $first = $service->post($payload, $this->owner);
        $second = $service->post($payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('48.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(1, GoodsReceipt::query()->count());
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_payment_cannot_exceed_receipt_total(): void
    {
        [$product, $cartonUnit] = $this->makeProduct();

        $this->expectException(DomainException::class);

        try {
            app(GoodsReceiptService::class)->post([
                'idempotency_key' => (string) Str::uuid(),
                'supplier_id' => $this->supplier->id,
                'received_at' => '2026-09-19 18:00:00',
                'paid_amount' => '1000.00',
                'payment_method' => 'cash',
                'items' => [[
                    'product_unit_id' => $cartonUnit->id,
                    'quantity' => '1',
                    'unit_cost' => '720.0000',
                ]],
            ], $this->owner);
        } finally {
            $this->assertSame('0.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, GoodsReceipt::query()->count());
        }
    }

    public function test_stock_keeper_cannot_record_initial_purchase_payment(): void
    {
        [$product, $cartonUnit] = $this->makeProduct('PERMISSION');
        $order = $this->approvedOrder($cartonUnit->id, '2', '720.0000');
        $poItem = $order->items()->firstOrFail();

        $stockKeeper = User::factory()->create();
        $stockKeeper->roles()->attach(Role::query()->where('name', 'stock_keeper')->firstOrFail());

        $this->expectException(DomainException::class);

        try {
            app(GoodsReceiptService::class)->post([
                'idempotency_key' => (string) Str::uuid(),
                'supplier_id' => $this->supplier->id,
                'purchase_order_id' => $order->id,
                'received_at' => '2026-09-19 18:30:00',
                'paid_amount' => '10.00',
                'payment_method' => 'cash',
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'quantity' => '1',
                    'unit_cost' => '720.0000',
                ]],
            ], $stockKeeper);
        } finally {
            $this->assertSame('0.000000', $product->fresh()->stock_on_hand);
            $this->assertSame('0.000000', $poItem->fresh()->received_quantity);
            $this->assertSame(0, GoodsReceipt::query()->count());
        }
    }

    public function test_direct_receipt_requires_explicit_unit_cost(): void
    {
        [$product, $cartonUnit] = $this->makeProduct('DIRECT-COST');

        $this->expectException(DomainException::class);

        try {
            app(GoodsReceiptService::class)->post([
                'idempotency_key' => (string) Str::uuid(),
                'supplier_id' => $this->supplier->id,
                'received_at' => '2026-09-19 18:45:00',
                'paid_amount' => '0.00',
                'items' => [[
                    'product_unit_id' => $cartonUnit->id,
                    'quantity' => '1',
                ]],
            ], $this->owner);
        } finally {
            $this->assertSame('0.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, GoodsReceipt::query()->count());
        }
    }

    public function test_posted_goods_receipt_header_is_immutable(): void
    {
        [$product, $cartonUnit] = $this->makeProduct();

        $receipt = app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => '2026-09-19 19:00:00',
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $cartonUnit->id,
                'quantity' => '1',
                'unit_cost' => '720.0000',
            ]],
        ], $this->owner);

        $receipt->notes = 'attempted change';

        $this->expectException(LogicException::class);
        $receipt->save();
    }

    private function approvedOrder(int $productUnitId, string $quantity, string $unitCost)
    {
        $order = app(PurchaseOrderService::class)->create([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-09-19',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_discount_amount' => '0.00',
            ]],
        ], $this->owner);

        return app(PurchaseOrderService::class)->approve($order, $this->owner)->fresh('items');
    }

    private function makeProduct(string $sku = 'JUICE-001', bool $trackExpiry = false): array
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();
        $carton = Unit::query()->where('code', 'CTN')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => $sku.'-'.Str::upper(Str::random(5)),
            'name_en' => 'Purchase Test Product',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '25.00',
            'selling_price' => '50.00',
            'track_stock' => true,
            'track_expiry' => $trackExpiry,
            'units' => [[
                'unit_id' => $carton->id,
                'conversion_factor' => '24',
                'can_purchase' => true,
                'can_sell' => true,
            ]],
        ], $this->owner);

        return [
            $product,
            $product->productUnits()->where('unit_id', $carton->id)->firstOrFail(),
        ];
    }
}
