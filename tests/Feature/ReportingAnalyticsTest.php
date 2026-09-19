<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\OperatingEntryService;
use App\Services\Catalog\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Reports\ReportingService;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\SaleReturnService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $cashier;
    private PaymentMethod $bank;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->cashier = User::factory()->create();
        $this->cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->bank = PaymentMethod::query()->where('code', 'bank')->firstOrFail();

        $this->supplier = Supplier::create([
            'name' => 'Reporting Supplier',
            'phone' => '0700000055',
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_report_reconciles_returns_profit_and_inventory_value_from_historical_cost(): void
    {
        $category = Category::create([
            'name_en' => 'Beverages',
            'name_fa' => 'نوشیدنی',
            'name_ps' => 'څښاک',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        [$product, $piece] = $this->makeProduct($category->id);

        app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => now()->toDateTimeString(),
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '10',
                'unit_cost' => '10.0000',
            ]],
        ], $this->owner);

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $this->bank->id,
                'amount' => '60.00',
                'reference' => 'BANK-SALE-1',
            ]],
        ], $this->owner);

        app(SaleReturnService::class)->returnItems($sale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'One unit returned',
            'items' => [[
                'sale_item_id' => $sale->items()->firstOrFail()->id,
                'quantity' => '1',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->bank->id,
                'amount' => null,
                'reference' => 'BANK-REFUND-1',
            ]],
        ], $this->owner);

        $expense = ExpenseCategory::query()->where('code', 'rent')->firstOrFail();
        $income = ExpenseCategory::query()->where('code', 'other_income')->firstOrFail();

        app(OperatingEntryService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'expense',
            'expense_category_id' => $expense->id,
            'payment_method_id' => $this->bank->id,
            'amount' => '5.00',
            'description' => 'Reporting expense',
        ], $this->owner);

        app(OperatingEntryService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'income',
            'expense_category_id' => $income->id,
            'payment_method_id' => $this->bank->id,
            'amount' => '2.00',
            'description' => 'Reporting income',
        ], $this->owner);

        // Change current catalog cost after the historical transactions.
        $product->forceFill(['purchase_cost' => '99.00'])->save();

        $report = app(ReportingService::class)->build([
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]);

        $summary = $report['summary'];

        $this->assertSame(1, $summary['sales_count']);
        $this->assertSame('60.00', $summary['sales_net']);
        $this->assertSame('30.00', $summary['returns']);
        $this->assertSame('30.00', $summary['net_sales']);
        $this->assertSame('20.00', $summary['sales_cogs']);
        $this->assertSame('10.00', $summary['cogs_reversed']);
        $this->assertSame('10.00', $summary['net_cogs']);
        $this->assertSame('20.00', $summary['gross_profit']);
        $this->assertSame('5.00', $summary['expenses']);
        $this->assertSame('2.00', $summary['other_income']);
        $this->assertSame('17.00', $summary['net_profit']);
        $this->assertSame('30.00', $summary['aov']);

        // 10 received - 2 sold + 1 returned = 9 units at historical FIFO cost 10.
        $this->assertSame('90.00', $summary['inventory_value']);

        $top = $report['topProducts']->first();
        $this->assertNotNull($top);
        $this->assertSame($product->id, $top->id);
        $this->assertSame('1.000000', $top->quantity_base);
        $this->assertSame('30.00', $top->net_sales);
        $this->assertSame('10.00', $top->cogs);
        $this->assertSame('20.00', $top->gross_profit);

        $categoryRow = $report['categoryProfit']->firstWhere('id', $category->id);
        $this->assertNotNull($categoryRow);
        $this->assertSame('30.00', $categoryRow->net_sales);
        $this->assertSame('10.00', $categoryRow->cogs);
        $this->assertSame('20.00', $categoryRow->gross_profit);

        $trend = $report['salesTrend']->first();
        $this->assertSame(today()->toDateString(), $trend->day);
        $this->assertSame('30.00', $trend->net_total);
        $this->assertSame('20.00', $trend->gross_profit);
    }

    public function test_report_filters_and_sales_csv_use_same_date_range(): void
    {
        [$product, $piece] = $this->makeProduct(null);

        app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => now()->toDateTimeString(),
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '5',
                'unit_cost' => '10.0000',
            ]],
        ], $this->owner);

        app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '1',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $this->bank->id,
                'amount' => '30.00',
            ]],
        ], $this->owner);

        $filters = [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'product_id' => $product->id,
        ];

        $report = app(ReportingService::class)->build($filters);
        $csvRows = app(ReportingService::class)->salesCsv($filters);

        $this->assertSame(1, $report['summary']['sales_count']);
        $this->assertSame('30.00', $report['summary']['net_sales']);
        $this->assertCount(1, $csvRows);
        $this->assertSame('30.00', $csvRows->first()->net_total);

        $this->actingAs($this->owner)
            ->get(route('reports.index', $filters))
            ->assertOk()
            ->assertSee('30.00');

        $this->actingAs($this->owner)
            ->get(route('reports.sales-csv', $filters))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_cashier_without_reports_permission_cannot_open_reports(): void
    {
        $this->assertFalse($this->cashier->hasPermission('reports.view'));

        $this->actingAs($this->cashier)
            ->get(route('reports.index'))
            ->assertForbidden();
    }


    public function test_report_viewer_without_profit_permission_cannot_export_profit_columns(): void
    {
        $viewer = User::factory()->create();
        $role = Role::create([
            'name' => 'report_viewer',
            'label' => 'Report Viewer',
        ]);
        $role->permissions()->attach(
            Permission::query()->where('name', 'reports.view')->firstOrFail()
        );
        $viewer->roles()->attach($role);

        $this->assertTrue($viewer->hasPermission('reports.view'));
        $this->assertFalse($viewer->hasPermission('reports.profit'));

        $this->actingAs($viewer)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('Gross Profit');

        $response = $this->actingAs($viewer)
            ->get(route('reports.sales-csv'));

        $response->assertOk();
        $this->assertStringNotContainsString('Gross Profit', $response->streamedContent());
        $this->assertStringNotContainsString('COGS', $response->streamedContent());
    }

    private function makeProduct(?int $categoryId): array
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => 'REPORT-'.Str::upper(Str::random(6)),
            'name_en' => 'Reporting Product',
            'name_fa' => 'محصول گزارش',
            'name_ps' => 'د راپور محصول',
            'category_id' => $categoryId,
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '30.00',
            'minimum_selling_price' => '25.00',
            'minimum_stock' => '2',
            'reorder_quantity' => '5',
            'track_stock' => true,
            'track_expiry' => false,
        ], $this->owner);

        return [
            $product,
            $product->productUnits()->where('unit_id', $piece->id)->firstOrFail(),
        ];
    }
}
