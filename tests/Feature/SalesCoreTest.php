<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\InventoryCostLayer;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\ShiftOpeningService;
use App\Services\Catalog\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Sales\SaleService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesCoreTest extends TestCase
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
            'name' => 'Kabul POS Supplier',
            'phone' => '0700000001',
            'opening_balance' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_exact_barcode_lookup_resolves_the_correct_product_unit(): void
    {
        [$product, $piece, $carton] = $this->makeProduct(
            sku: 'BARCODE',
            withCarton: true,
            withBarcodes: true,
        );

        $response = $this->actingAs($this->owner)
            ->getJson(route('pos.products.search', ['q' => '6291000000999']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.product_unit_id', $carton->id)
            ->assertJsonPath('data.0.matched_barcode', '6291000000999')
            ->assertJsonPath('data.0.price', '700.00');
    }

    public function test_multilingual_product_search_finds_dari_name(): void
    {
        [$product] = $this->makeProduct(sku: 'DARI-SEARCH');

        $this->actingAs($this->owner)
            ->getJson(route('pos.products.search', ['q' => 'آبمیوه']))
            ->assertOk()
            ->assertJsonFragment([
                'product_id' => $product->id,
                'name' => $product->name_en,
            ]);
    }

    public function test_server_recalculates_sale_totals_and_ignores_client_totals(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'SERVER-TOTALS');
        $this->receive($piece->id, '10', '10.0000');

        $cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();

        $response = $this->actingAs($this->owner)->postJson(route('pos.sales.store'), [
            'idempotency_key' => (string) Str::uuid(),
            'subtotal' => '0.01',
            'net_total' => '0.01',
            'cogs_total' => '99999.99',
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
                'unit_price' => '1.00',
            ]],
            'payments' => [[
                'payment_method_id' => $cash->id,
                'amount' => '60.00',
                'tendered_amount' => '60.00',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sale.net_total', '60.00');

        $sale = Sale::query()->firstOrFail();

        $this->assertSame('60.00', $sale->subtotal);
        $this->assertSame('60.00', $sale->net_total);
        $this->assertSame('20.00', $sale->cogs_total);
        $this->assertSame('40.00', $sale->gross_profit);
        $this->assertSame('60.00', $sale->paid_amount);
        $this->assertSame('0.00', $sale->balance_due);
        $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
    }

    public function test_fifo_cogs_remains_historical_across_changing_purchase_costs(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'FIFO');

        $this->receive($piece->id, '10', '10.0000', receivedAt: '2026-09-19 09:00:00');
        $this->receive($piece->id, '10', '20.0000', receivedAt: '2026-09-19 10:00:00');

        $sale = app(SaleService::class)->complete([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '15',
                'line_discount_amount' => '0.00',
            ]],
        ], $this->owner);

        $layers = InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $layers);
        $this->assertSame('0.000000', $layers[0]->remaining_quantity_base);
        $this->assertSame('5.000000', $layers[1]->remaining_quantity_base);

        $this->assertSame('450.00', $sale->net_total);
        $this->assertSame('200.00', $sale->cogs_total);
        $this->assertSame('250.00', $sale->gross_profit);
        $this->assertSame('5.000000', $product->fresh()->stock_on_hand);

        $item = $sale->items()->firstOrFail();
        $this->assertSame('200.00', $item->fresh()->cogs_amount);
        $this->assertCount(2, $item->costConsumptions);
    }

    public function test_expiry_stock_is_depleted_fefo_while_financial_cost_remains_fifo(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'FEFO', trackExpiry: true);

        $this->receive(
            $piece->id,
            '10',
            '10.0000',
            batch: ['batch_number' => 'LATE', 'expires_at' => '2027-12-31'],
            receivedAt: '2026-09-19 09:00:00',
        );
        $this->receive(
            $piece->id,
            '10',
            '20.0000',
            batch: ['batch_number' => 'EARLY', 'expires_at' => '2027-03-31'],
            receivedAt: '2026-09-19 10:00:00',
        );

        $sale = app(SaleService::class)->complete([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '5',
                'line_discount_amount' => '0.00',
            ]],
        ], $this->owner);

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

        $allocation = $sale->items()->firstOrFail()->stockAllocations()->firstOrFail();
        $this->assertSame($early->id, $allocation->product_batch_id);

        // Financial FIFO is independent of physical FEFO: the first receipt cost was AFN 10.
        $this->assertSame('50.00', $sale->cogs_total);
    }

    public function test_insufficient_stock_rolls_back_the_entire_sale(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'ROLLBACK');
        $this->receive($piece->id, '3', '10.0000');

        try {
            app(SaleService::class)->complete([
                'idempotency_key' => (string) Str::uuid(),
                'sale_discount_amount' => '0.00',
                'items' => [[
                    'product_unit_id' => $piece->id,
                    'quantity' => '4',
                    'line_discount_amount' => '0.00',
                ]],
            ], $this->owner);

            $this->fail('Expected insufficient stock to reject the sale.');
        } catch (DomainException) {
            $this->assertSame('3.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, StockMovement::query()->where('movement_type', 'sale')->count());

            $layer = InventoryCostLayer::query()->where('product_id', $product->id)->firstOrFail();
            $this->assertSame('3.000000', $layer->remaining_quantity_base);
        }
    }

    public function test_sale_idempotency_does_not_duplicate_stock_or_cost_consumption(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'IDEMPOTENT');
        $this->receive($piece->id, '10', '10.0000');

        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
        ];

        $service = app(SaleService::class);
        $first = $service->complete($payload, $this->owner);
        $second = $service->complete($payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'sale')->count());
        $this->assertSame(1, $first->items()->firstOrFail()->costConsumptions()->count());
    }

    public function test_sale_retry_rejects_a_changed_cart_payload(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'IDEMPOTENT-CONFLICT');
        $this->receive($piece->id, '10', '10.0000');

        $key = (string) Str::uuid();
        $service = app(SaleService::class);

        $service->complete([
            'idempotency_key' => $key,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
        ], $this->owner);

        try {
            $service->complete([
                'idempotency_key' => $key,
                'sale_discount_amount' => '0.00',
                'items' => [[
                    'product_unit_id' => $piece->id,
                    'quantity' => '1',
                    'line_discount_amount' => '0.00',
                ]],
            ], $this->owner);

            $this->fail('Expected changed cart payload to be rejected for the same idempotency key.');
        } catch (DomainException) {
            $this->assertSame(1, Sale::query()->count());
            $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(1, StockMovement::query()->where('movement_type', 'sale')->count());
        }
    }

    public function test_cashier_cannot_apply_discount(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'NO-DISCOUNT');
        $this->receive($piece->id, '10', '10.0000');

        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->expectException(DomainException::class);

        try {
            app(SaleService::class)->complete([
                'idempotency_key' => (string) Str::uuid(),
                'sale_discount_amount' => '0.00',
                'items' => [[
                    'product_unit_id' => $piece->id,
                    'quantity' => '1',
                    'line_discount_amount' => '1.00',
                ]],
            ], $cashier);
        } finally {
            $this->assertSame('10.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, Sale::query()->count());
        }
    }

    public function test_payment_summary_can_change_without_mutating_commercial_sale_fields(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'PAYMENT-MUTABLE');
        $this->receive($piece->id, '2', '10.0000');

        $sale = app(SaleService::class)->complete([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '1',
                'line_discount_amount' => '0.00',
            ]],
        ], $this->owner);

        $sale->forceFill([
            'payment_status' => 'paid',
            'paid_amount' => $sale->net_total,
            'balance_due' => '0.00',
        ])->save();

        $this->assertSame('0.00', $sale->fresh()->balance_due);

        $sale->net_total = '1.00';

        $this->expectException(\LogicException::class);
        $sale->save();
    }

    public function test_discount_permission_does_not_automatically_allow_below_minimum_price(): void
    {
        [$product, $piece] = $this->makeProduct(sku: 'MIN-PRICE', minimumPrice: '25.00');
        $this->receive($piece->id, '10', '10.0000');

        $role = Role::create([
            'name' => 'discount_without_floor_override',
            'label' => 'Discount without floor override',
        ]);
        $role->permissions()->sync(
            Permission::query()
                ->whereIn('name', ['sales.create', 'sales.discount'])
                ->pluck('id')
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->expectException(DomainException::class);

        try {
            app(SaleService::class)->complete([
                'idempotency_key' => (string) Str::uuid(),
                'sale_discount_amount' => '0.00',
                'items' => [[
                    'product_unit_id' => $piece->id,
                    'quantity' => '1',
                    'line_discount_amount' => '10.00',
                ]],
            ], $user);
        } finally {
            $this->assertSame('10.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, Sale::query()->count());
        }
    }

    private function makeProduct(
        string $sku,
        bool $trackExpiry = false,
        bool $withCarton = false,
        bool $withBarcodes = false,
        string $minimumPrice = '25.00',
    ): array {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();
        $cartonUnit = Unit::query()->where('code', 'CTN')->firstOrFail();

        $units = [];
        if ($withCarton) {
            $units[] = [
                'unit_id' => $cartonUnit->id,
                'conversion_factor' => '24',
                'can_purchase' => true,
                'can_sell' => true,
                'selling_price' => '700.00',
                'minimum_selling_price' => '600.00',
            ];
        }

        $barcodes = [];
        if ($withBarcodes) {
            $barcodes = [
                [
                    'barcode' => '6291000000001',
                    'unit_id' => $piece->id,
                    'is_primary' => true,
                ],
                [
                    'barcode' => '6291000000999',
                    'unit_id' => $cartonUnit->id,
                ],
            ];
        }

        $product = app(ProductService::class)->create([
            'sku' => $sku.'-'.Str::upper(Str::random(5)),
            'name_en' => 'Orange Juice',
            'name_fa' => 'آبمیوه پرتقال',
            'name_ps' => 'د مالټې جوس',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '5.00',
            'selling_price' => '30.00',
            'minimum_selling_price' => $minimumPrice,
            'track_stock' => true,
            'track_expiry' => $trackExpiry,
            'units' => $units,
            'barcodes' => $barcodes,
        ], $this->owner);

        $pieceProductUnit = $product->productUnits()
            ->where('unit_id', $piece->id)
            ->firstOrFail();

        $cartonProductUnit = $withCarton
            ? $product->productUnits()->where('unit_id', $cartonUnit->id)->firstOrFail()
            : null;

        return [$product, $pieceProductUnit, $cartonProductUnit];
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
}
