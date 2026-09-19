<?php

namespace Tests\Feature;

use App\Enums\BusinessDayStatus;
use App\Enums\StockMovementType;
use App\Models\BusinessDay;
use App\Models\InventoryCostLayer;
use App\Models\InventoryWriteoff;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Closing\BusinessDayClosingService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\InventoryWriteoffService;
use App\Services\Inventory\StockCountService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());
    }

    public function test_stock_count_draft_has_no_effect_and_approval_posts_positive_and_negative_variances_once(): void
    {
        [$lossProduct, $lossUnit] = $this->makeProduct('COUNT-LOSS', '10.00');
        [$gainProduct, $gainUnit] = $this->makeProduct('COUNT-GAIN', '12.00');

        $this->openStock($lossProduct, $lossUnit->unit_id, '10', '10.00');
        $this->openStock($gainProduct, $gainUnit->unit_id, '5', '12.00');

        $count = app(StockCountService::class)->createDraft([
            'idempotency_key' => (string) Str::uuid(),
            'notes' => 'Cycle count aisle A',
            'items' => [
                [
                    'target' => $lossProduct->id.':',
                    'physical_quantity_base' => '8',
                ],
                [
                    'target' => $gainProduct->id.':',
                    'physical_quantity_base' => '7',
                ],
            ],
        ], $this->owner);

        $this->assertSame('draft', $count->status);
        $this->assertSame('10.000000', $lossProduct->fresh()->stock_on_hand);
        $this->assertSame('5.000000', $gainProduct->fresh()->stock_on_hand);
        $this->assertSame(0, StockMovement::query()->whereIn('movement_type', [
            StockMovementType::AdjustmentIn->value,
            StockMovementType::AdjustmentOut->value,
        ])->count());

        $key = (string) Str::uuid();
        $service = app(StockCountService::class);
        $approved = $service->approve($count, ['idempotency_key' => $key], $this->owner);
        $retry = $service->approve($count, ['idempotency_key' => $key], $this->owner);

        $this->assertSame($approved->id, $retry->id);
        $this->assertSame('approved', $approved->status);
        $this->assertSame('8.000000', $lossProduct->fresh()->stock_on_hand);
        $this->assertSame('7.000000', $gainProduct->fresh()->stock_on_hand);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovementType::AdjustmentOut->value)->count());
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovementType::AdjustmentIn->value)->count());

        $lossLayer = InventoryCostLayer::query()
            ->where('product_id', $lossProduct->id)
            ->orderBy('id')
            ->firstOrFail();
        $this->assertSame('8.000000', $lossLayer->remaining_quantity_base);

        $gainLayerTotal = InventoryCostLayer::query()
            ->where('product_id', $gainProduct->id)
            ->sum('remaining_quantity_base');
        $this->assertSame('7.000000', \App\Support\Decimal::normalize((string) $gainLayerTotal));

        $lossLine = $approved->items->firstWhere('product_id', $lossProduct->id);
        $gainLine = $approved->items->firstWhere('product_id', $gainProduct->id);

        $this->assertSame('-2.000000', $lossLine->variance_quantity_base);
        $this->assertSame('20.0000', $lossLine->cost_amount);
        $this->assertSame('2.000000', $gainLine->variance_quantity_base);
        $this->assertSame('24.0000', $gainLine->cost_amount);
    }

    public function test_stock_count_approval_rejects_stale_snapshot(): void
    {
        [$product, $unit] = $this->makeProduct('COUNT-STALE', '10.00');
        $this->openStock($product, $unit->unit_id, '10', '10.00');

        $count = app(StockCountService::class)->createDraft([
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'target' => $product->id.':',
                'physical_quantity_base' => '8',
            ]],
        ], $this->owner);

        $this->openStock($product, $unit->unit_id, '1', '10.00');

        try {
            app(StockCountService::class)->approve($count, [
                'idempotency_key' => (string) Str::uuid(),
            ], $this->owner);

            $this->fail('Expected stale stock count approval to fail.');
        } catch (DomainException) {
            $this->assertSame('draft', $count->fresh()->status);
            $this->assertSame('11.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, StockMovement::query()
                ->where('movement_type', StockMovementType::AdjustmentOut->value)
                ->count());
        }
    }

    public function test_damage_writeoff_reduces_stock_cost_layer_and_is_idempotent(): void
    {
        [$product, $unit] = $this->makeProduct('DAMAGE', '10.00');
        $this->openStock($product, $unit->unit_id, '10', '10.00');

        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'writeoff_type' => 'damage',
            'reason' => 'Broken packaging',
            'items' => [[
                'target' => $product->id.':',
                'quantity_base' => '3',
            ]],
        ];

        $service = app(InventoryWriteoffService::class);
        $first = $service->post($payload, $this->owner);
        $second = $service->post($payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, InventoryWriteoff::query()->count());
        $this->assertSame('30.0000', $first->total_cost);
        $this->assertSame('7.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovementType::Damage->value)->count());

        $layer = InventoryCostLayer::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('7.000000', $layer->remaining_quantity_base);

        $this->assertDatabaseHas('inventory_cost_adjustment_consumptions', [
            'stock_movement_id' => $first->items()->firstOrFail()->stock_movement_id,
            'quantity_base' => '3.000000',
            'cost_amount' => '30.0000',
        ]);
    }

    public function test_expiry_writeoff_reduces_exact_expired_batch_and_cost_layer(): void
    {
        [$product, $unit] = $this->makeProduct('EXPIRY', '8.00', true);

        $movement = app(InventoryService::class)->addOpeningStock(
            product: $product,
            sourceQuantity: '10',
            sourceUnitId: $unit->unit_id,
            batchData: [
                'batch_number' => 'EXP-OLD',
                'expires_at' => today()->subDay()->format('Y-m-d'),
            ],
            sourceUnitCost: '8.00',
            actor: $this->owner,
            idempotencyKey: (string) Str::uuid(),
        );

        $batch = ProductBatch::query()->findOrFail($movement->product_batch_id);

        $writeoff = app(InventoryWriteoffService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'writeoff_type' => 'expiry',
            'reason' => 'Expired on shelf',
            'items' => [[
                'target' => $product->id.':'.$batch->id,
                'quantity_base' => '4',
            ]],
        ], $this->owner);

        $this->assertSame('32.0000', $writeoff->total_cost);
        $this->assertSame('6.000000', $product->fresh()->stock_on_hand);
        $this->assertSame('6.000000', $batch->fresh()->stock_on_hand);

        $layer = InventoryCostLayer::query()
            ->where('product_batch_id', $batch->id)
            ->firstOrFail();

        $this->assertSame('6.000000', $layer->remaining_quantity_base);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovementType::Expiry->value)->count());
    }

    public function test_over_writeoff_rolls_back_document_stock_and_cost(): void
    {
        [$product, $unit] = $this->makeProduct('OVER-WRITE', '10.00');
        $this->openStock($product, $unit->unit_id, '5', '10.00');

        try {
            app(InventoryWriteoffService::class)->post([
                'idempotency_key' => (string) Str::uuid(),
                'writeoff_type' => 'damage',
                'reason' => 'Too many units requested',
                'items' => [[
                    'target' => $product->id.':',
                    'quantity_base' => '6',
                ]],
            ], $this->owner);

            $this->fail('Expected over-writeoff to fail.');
        } catch (DomainException) {
            $this->assertSame(0, InventoryWriteoff::query()->count());
            $this->assertSame('5.000000', $product->fresh()->stock_on_hand);
            $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovementType::Damage->value)->count());

            $layer = InventoryCostLayer::query()->where('product_id', $product->id)->firstOrFail();
            $this->assertSame('5.000000', $layer->remaining_quantity_base);
        }
    }

    public function test_closed_business_day_blocks_count_approval_and_writeoff(): void
    {
        [$product, $unit] = $this->makeProduct('DAY-LOCK-INV', '10.00');
        $this->openStock($product, $unit->unit_id, '5', '10.00');

        $count = app(StockCountService::class)->createDraft([
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'target' => $product->id.':',
                'physical_quantity_base' => '4',
            ]],
        ], $this->owner);

        $date = now()->format('Y-m-d');

        app(BusinessDayClosingService::class)->close($date, [
            'idempotency_key' => (string) Str::uuid(),
            'notes' => 'Close before inventory adjustment',
        ], $this->owner);

        $this->assertSame(
            BusinessDayStatus::Closed,
            BusinessDay::query()->whereDate('business_date', $date)->firstOrFail()->status,
        );

        try {
            app(StockCountService::class)->approve($count, [
                'idempotency_key' => (string) Str::uuid(),
            ], $this->owner);

            $this->fail('Expected stock-count approval on a closed business day to fail.');
        } catch (DomainException) {
            $this->assertSame('draft', $count->fresh()->status);
        }

        try {
            app(InventoryWriteoffService::class)->post([
                'idempotency_key' => (string) Str::uuid(),
                'writeoff_type' => 'damage',
                'reason' => 'Closed day test',
                'items' => [[
                    'target' => $product->id.':',
                    'quantity_base' => '1',
                ]],
            ], $this->owner);

            $this->fail('Expected write-off on a closed business day to fail.');
        } catch (DomainException) {
            $this->assertSame(0, InventoryWriteoff::query()->count());
            $this->assertSame('5.000000', $product->fresh()->stock_on_hand);
        }
    }

    public function test_inventory_operations_dashboard_surfaces_expiry_and_reorder_signals(): void
    {
        [$low, $lowUnit] = $this->makeProduct(
            'REORDER-LOW',
            '5.00',
            false,
            minimumStock: '5',
            reorderQuantity: '10',
        );
        $this->openStock($low, $lowUnit->unit_id, '3', '5.00');

        [$expiry, $expiryUnit] = $this->makeProduct('EXP-DASH', '4.00', true);

        app(InventoryService::class)->addOpeningStock(
            product: $expiry,
            sourceQuantity: '2',
            sourceUnitId: $expiryUnit->unit_id,
            batchData: [
                'batch_number' => 'EXPIRED-DASH',
                'expires_at' => today()->subDay()->format('Y-m-d'),
            ],
            sourceUnitCost: '4.00',
            actor: $this->owner,
            idempotencyKey: (string) Str::uuid(),
        );

        app(InventoryService::class)->addOpeningStock(
            product: $expiry,
            sourceQuantity: '3',
            sourceUnitId: $expiryUnit->unit_id,
            batchData: [
                'batch_number' => 'SOON-DASH',
                'expires_at' => today()->addDays(10)->format('Y-m-d'),
            ],
            sourceUnitCost: '4.00',
            actor: $this->owner,
            idempotencyKey: (string) Str::uuid(),
        );

        $response = $this->actingAs($this->owner)
            ->get(route('inventory.operations.index', ['expiry_days' => 30]));

        $response->assertOk();
        $response->assertSee('REORDER-LOW', false);
        $response->assertSee('EXPIRED-DASH', false);
        $response->assertSee('SOON-DASH', false);

        $response->assertViewHas('reorderSuggestions', function ($rows) use ($low): bool {
            $row = $rows->first(fn (array $row) => (int) $row['product']->id === (int) $low->id);

            return $row
                && $row['status'] === 'low'
                && $row['suggested_quantity'] === '10.000000';
        });

        $response->assertViewHas('expiredBatches', fn ($rows) => $rows->contains('batch_number', 'EXPIRED-DASH'));
        $response->assertViewHas('expiringBatches', fn ($rows) => $rows->contains('batch_number', 'SOON-DASH'));
    }

    private function makeProduct(
        string $sku,
        string $purchaseCost,
        bool $trackExpiry = false,
        string $minimumStock = '0',
        string $reorderQuantity = '0',
    ): array {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => $sku.'-'.Str::upper(Str::random(5)),
            'name_en' => $sku,
            'name_fa' => $sku,
            'name_ps' => $sku,
            'base_unit_id' => $piece->id,
            'purchase_cost' => $purchaseCost,
            'selling_price' => '20.00',
            'minimum_selling_price' => '15.00',
            'minimum_stock' => $minimumStock,
            'reorder_quantity' => $reorderQuantity,
            'track_stock' => true,
            'track_expiry' => $trackExpiry,
        ], $this->owner);

        return [
            $product,
            $product->productUnits()->where('unit_id', $piece->id)->firstOrFail(),
        ];
    }

    private function openStock(Product $product, int $unitId, string $quantity, string $cost): StockMovement
    {
        return app(InventoryService::class)->addOpeningStock(
            product: $product,
            sourceQuantity: $quantity,
            sourceUnitId: $unitId,
            sourceUnitCost: $cost,
            actor: $this->owner,
            idempotencyKey: (string) Str::uuid(),
        );
    }
}
