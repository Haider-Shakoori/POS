<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create(['preferred_locale' => 'en']);
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());
    }

    public function test_short_terms_return_no_results(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'a']))
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_owner_searches_products_customers_suppliers_and_sales(): void
    {
        $this->makeProduct('Zebra Chocolate', 'ZEB-1');

        Customer::create([
            'name' => 'Zebra Customer',
            'phone' => '0700000001',
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'credit_limit' => '0.00',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('search', ['q' => 'Zebra']))
            ->assertOk();

        $types = collect($response->json('data'))->pluck('type')->unique()->values()->all();

        $this->assertContains('product', $types);
        $this->assertContains('customer', $types);
        $this->assertTrue(
            collect($response->json('data'))->contains(fn (array $item) => str_contains($item['url'], '/inventory/products/')),
        );
    }

    public function test_users_without_permissions_receive_no_results(): void
    {
        $this->makeProduct('Zebra Chocolate', 'ZEB-1');

        $limited = User::factory()->create(['preferred_locale' => 'en']);
        $role = Role::create(['name' => 'search_limited', 'label' => 'Search Limited']);
        $role->permissions()->attach(Permission::query()->where('name', 'reports.view')->firstOrFail());
        $limited->roles()->attach($role);

        $this->actingAs($limited)
            ->getJson(route('search', ['q' => 'Zebra']))
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    private function makeProduct(string $name, string $sku): void
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        app(ProductService::class)->create([
            'sku' => $sku,
            'name_en' => $name,
            'name_fa' => 'محصول',
            'name_ps' => 'توکی',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '30.00',
            'track_stock' => true,
            'track_expiry' => false,
        ], $this->owner);
    }
}
