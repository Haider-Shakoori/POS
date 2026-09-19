<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductService;
use App\Services\Inventory\InventoryService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class InventoryCoreTest extends TestCase
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

    public function test_product_can_be_created_with_multiple_units_and_barcodes(): void
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();
        $carton = Unit::query()->where('code', 'CTN')->firstOrFail();

        $response = $this->actingAs($this->owner)->post(route('inventory.products.store'), [
            'sku' => 'JUICE-001',
            'name_en' => 'Orange Juice',
            'name_fa' => 'آب پرتقال',
            'name_ps' => 'د مالټې جوس',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '30.00',
            'selling_price' => '50.00',
            'minimum_stock' => '12',
            'reorder_quantity' => '24',
            'track_stock' => '1',
            'units' => [
                [
                    'unit_id' => $carton->id,
                    'conversion_factor' => '24',
                    'can_purchase' => '1',
                    'can_sell' => '1',
                    'selling_price' => '1150.00',
                ],
            ],
            'barcodes' => [
                ['barcode' => '6291000000011', 'unit_id' => $piece->id, 'is_primary' => '1'],
                ['barcode' => '6291000000028', 'unit_id' => $carton->id],
            ],
        ]);

        $product = Product::query()->where('sku', 'JUICE-001')->firstOrFail();

        $response->assertRedirect(route('inventory.products.show', $product));
        $this->assertCount(2, $product->productUnits);
        $this->assertCount(2, $product->barcodes);
        $this->assertSame('24.000000', $product->productUnits()->where('unit_id', $carton->id)->firstOrFail()->conversion_factor);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'inventory.product.created',
            'auditable_id' => $product->id,
        ]);
    }

    public function test_opening_stock_converts_source_unit_to_base_unit_and_records_ledger(): void
    {
        $product = $this->makeProduct();
        $carton = Unit::query()->where('code', 'CTN')->firstOrFail();
        $key = (string) Str::uuid();

        $this->actingAs($this->owner)
            ->post(route('inventory.products.opening-stock.store', $product), [
                'quantity' => '2',
                'unit_id' => $carton->id,
                'unit_cost' => '720.0000',
                'idempotency_key' => $key,
            ])
            ->assertRedirect();

        $product->refresh();
        $movement = StockMovement::query()->where('idempotency_key', $key)->firstOrFail();

        $this->assertSame('48.000000', $product->stock_on_hand);
        $this->assertSame('2.000000', $movement->source_quantity);
        $this->assertSame('24.000000', $movement->conversion_factor);
        $this->assertSame('48.000000', $movement->quantity_base);
        $this->assertSame('48.000000', $movement->balance_after);
        $this->assertSame(StockMovementType::OpeningStock, $movement->movement_type);
    }

    public function test_expiry_tracked_opening_stock_requires_and_updates_a_batch(): void
    {
        $product = $this->makeProduct(trackExpiry: true);
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $this->actingAs($this->owner)
            ->post(route('inventory.products.opening-stock.store', $product), [
                'quantity' => '10',
                'unit_id' => $piece->id,
            ])
            ->assertSessionHasErrors(['batch_number', 'expires_at']);

        $this->actingAs($this->owner)
            ->post(route('inventory.products.opening-stock.store', $product), [
                'quantity' => '10',
                'unit_id' => $piece->id,
                'batch_number' => 'B-2026-01',
                'expires_at' => '2027-03-31',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect();

        $batch = $product->batches()->where('batch_number', 'B-2026-01')->firstOrFail();

        $this->assertSame('10.000000', $product->fresh()->stock_on_hand);
        $this->assertSame('10.000000', $batch->stock_on_hand);
    }

    public function test_negative_stock_is_blocked_when_policy_is_disabled(): void
    {
        $product = $this->makeProduct();

        $this->expectException(DomainException::class);

        app(InventoryService::class)->recordMovement(
            product: $product,
            type: StockMovementType::Sale,
            baseQuantity: '-1',
            actor: $this->owner,
        );
    }

    public function test_stock_movements_are_immutable(): void
    {
        $product = $this->makeProduct();
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $movement = app(InventoryService::class)->addOpeningStock(
            product: $product,
            sourceQuantity: '1',
            sourceUnitId: $piece->id,
            actor: $this->owner,
        );

        $movement->notes = 'changed';

        $this->expectException(LogicException::class);
        $movement->save();
    }

    private function makeProduct(bool $trackExpiry = false): Product
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();
        $carton = Unit::query()->where('code', 'CTN')->firstOrFail();

        return app(ProductService::class)->create([
            'sku' => 'TEST-'.Str::upper(Str::random(8)),
            'name_en' => 'Test Product',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '15.00',
            'track_stock' => true,
            'track_expiry' => $trackExpiry,
            'units' => [
                [
                    'unit_id' => $carton->id,
                    'conversion_factor' => '24',
                    'can_purchase' => true,
                    'can_sell' => true,
                ],
            ],
        ], $this->owner);
    }
}
