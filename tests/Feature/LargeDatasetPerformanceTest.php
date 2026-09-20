<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LargeDatasetPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Unit $piece;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());
        $this->piece = Unit::query()->where('code', 'PCS')->firstOrFail();
    }

    public function test_reports_use_bounded_async_lookups_instead_of_embedding_full_catalogs(): void
    {
        for ($i = 0; $i < 80; $i++) {
            Product::create([
                'sku' => sprintf('LARGE-%03d', $i),
                'name_en' => sprintf('Catalog Product %03d', $i),
                'name_fa' => sprintf('Catalog Product %03d', $i),
                'name_ps' => sprintf('Catalog Product %03d', $i),
                'base_unit_id' => $this->piece->id,
                'track_stock' => true,
                'track_expiry' => false,
                'is_active' => true,
            ]);
        }

        for ($i = 0; $i < 40; $i++) {
            Customer::create([
                'name' => sprintf('Catalog Customer %03d', $i),
                'phone' => sprintf('0700%06d', $i),
                'credit_limit' => '0.00',
                'opening_balance' => '0.00',
                'current_balance' => '0.00',
                'is_active' => true,
            ]);

            Supplier::create([
                'name' => sprintf('Catalog Supplier %03d', $i),
                'phone' => sprintf('0790%06d', $i),
                'opening_balance' => '0.00',
                'current_balance' => '0.00',
                'is_active' => true,
            ]);
        }

        $target = Product::query()->where('sku', 'LARGE-079')->firstOrFail();

        $this->withoutVite();

        $this->actingAs($this->owner)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('Catalog Product 079')
            ->assertDontSee('Catalog Customer 039')
            ->assertDontSee('Catalog Supplier 039');

        $productLookup = $this->actingAs($this->owner)
            ->getJson(route('reports.lookup', [
                'type' => 'product',
                'q' => 'Catalog Product',
            ]))
            ->assertOk()
            ->json('data');

        $this->assertCount(15, $productLookup);
        $this->assertSame('Catalog Product 000', $productLookup[0]['label']);

        $this->actingAs($this->owner)
            ->getJson(route('reports.lookup', [
                'type' => 'product',
                'q' => 'LARGE-079',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('data.0.meta', 'LARGE-079');

        $this->actingAs($this->owner)
            ->get(route('reports.index', ['product_id' => $target->id]))
            ->assertOk()
            ->assertSee('Catalog Product 079')
            ->assertSee('LARGE-079');
    }

    public function test_inventory_operations_searches_targets_on_demand_and_paginates_large_monitoring_sets(): void
    {
        for ($i = 0; $i < 80; $i++) {
            Product::create([
                'sku' => sprintf('WARE-%03d', $i),
                'name_en' => sprintf('Warehouse Item %03d', $i),
                'name_fa' => sprintf('Warehouse Item %03d', $i),
                'name_ps' => sprintf('Warehouse Item %03d', $i),
                'base_unit_id' => $this->piece->id,
                'stock_on_hand' => $i < 45 ? '0.000000' : '25.000000',
                'minimum_stock' => $i < 45 ? '5.000000' : '0.000000',
                'reorder_quantity' => $i < 45 ? '10.000000' : '0.000000',
                'track_stock' => true,
                'track_expiry' => false,
                'is_active' => true,
            ]);
        }

        $expiryProduct = Product::create([
            'sku' => 'EXP-LARGE',
            'name_en' => 'Large Expiry Product',
            'name_fa' => 'Large Expiry Product',
            'name_ps' => 'Large Expiry Product',
            'base_unit_id' => $this->piece->id,
            'stock_on_hand' => '30.000000',
            'minimum_stock' => '0.000000',
            'reorder_quantity' => '0.000000',
            'track_stock' => true,
            'track_expiry' => true,
            'is_active' => true,
        ]);

        for ($i = 0; $i < 30; $i++) {
            ProductBatch::create([
                'product_id' => $expiryProduct->id,
                'batch_number' => sprintf('EXP-LARGE-%02d', $i),
                'expires_at' => today()->subDays(30 - $i),
                'stock_on_hand' => '1.000000',
                'is_blocked' => false,
            ]);
        }

        $this->withoutVite();

        $response = $this->actingAs($this->owner)
            ->get(route('inventory.operations.index'));

        $response
            ->assertOk()
            ->assertDontSee('Warehouse Item 079')
            ->assertDontSee('EXP-LARGE-29');

        $response->assertViewHas('reorderSuggestionCount', 45);
        $response->assertViewHas('expiredBatchCount', 30);
        $response->assertViewHas('reorderSuggestions', fn ($rows): bool => $rows->count() === 20 && $rows->total() === 45);
        $response->assertViewHas('expiredBatches', fn ($rows): bool => $rows->count() === 12 && $rows->total() === 30);

        $countLookup = $this->actingAs($this->owner)
            ->getJson(route('inventory.operations.targets.search', [
                'q' => 'Warehouse Item',
                'mode' => 'count',
            ]))
            ->assertOk()
            ->json('data');

        $this->assertCount(15, $countLookup);

        $this->actingAs($this->owner)
            ->getJson(route('inventory.operations.targets.search', [
                'q' => 'WARE-079',
                'mode' => 'count',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.meta', 'WARE-079');

        $this->actingAs($this->owner)
            ->getJson(route('inventory.operations.targets.search', [
                'q' => 'EXP-LARGE-29',
                'mode' => 'expiry',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.value', $expiryProduct->id.':'.ProductBatch::query()->where('batch_number', 'EXP-LARGE-29')->value('id'));
    }

    public function test_large_dataset_lookup_endpoints_keep_existing_permissions(): void
    {
        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->actingAs($cashier)
            ->getJson(route('reports.lookup', ['type' => 'product', 'q' => 'test']))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->getJson(route('inventory.operations.targets.search', ['q' => 'test', 'mode' => 'count']))
            ->assertForbidden();
    }
}
