<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\OperatingEntryService;
use App\Services\Cash\ShiftOpeningService;
use App\Services\Catalog\ProductService;
use App\Services\Closing\BusinessDayClosingService;
use App\Services\Closing\ShiftClosingService;
use App\Services\Customers\CustomerCollectionService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Reports\ReportingService;
use App\Services\Sales\CheckoutService;
use App\Services\Suppliers\SupplierPaymentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinalGoldenPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_shop_day_reconciles_stock_cash_receivables_payables_profit_and_closing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $shift = app(ShiftOpeningService::class)->open([
            'idempotency_key' => (string) Str::uuid(),
            'terminal_id' => Terminal::query()->where('code', 'COUNTER-1')->firstOrFail()->id,
            'opening_cash' => '1000.00',
        ], $owner);

        $supplier = Supplier::create([
            'name' => 'Golden Path Supplier',
            'phone' => '0700000991',
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'is_active' => true,
        ]);

        $product = app(ProductService::class)->create([
            'sku' => 'GOLDEN-001',
            'name_en' => 'Golden Path Product',
            'name_fa' => 'محصول مسیر طلایی',
            'name_ps' => 'د طلایي لارې محصول',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '30.00',
            'minimum_selling_price' => '25.00',
            'minimum_stock' => '2',
            'reorder_quantity' => '5',
            'track_stock' => true,
            'track_expiry' => false,
        ], $owner);

        $productUnit = $product->productUnits()->where('unit_id', $piece->id)->firstOrFail();

        $receipt = app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'received_at' => now()->toDateTimeString(),
            'paid_amount' => '40.00',
            'payment_method' => 'cash',
            'items' => [[
                'product_unit_id' => $productUnit->id,
                'quantity' => '10',
                'unit_cost' => '10.0000',
            ]],
        ], $owner);

        app(SupplierPaymentService::class)->record($supplier, [
            'idempotency_key' => (string) Str::uuid(),
            'amount' => '10.00',
            'method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ], $owner);

        $customer = Customer::create([
            'name' => 'Golden Path Customer',
            'phone' => '0700000992',
            'credit_limit' => '500.00',
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'is_active' => true,
        ]);

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnit->id,
                'quantity' => '3',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $cash->id,
                'amount' => '40.00',
                'tendered_amount' => '40.00',
            ]],
        ], $owner);

        app(CustomerCollectionService::class)->collect($customer, [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method_id' => $cash->id,
            'amount' => '20.00',
            'tendered_amount' => '20.00',
            'collected_at' => now()->toDateTimeString(),
        ], $owner);

        app(OperatingEntryService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'expense',
            'expense_category_id' => ExpenseCategory::query()->where('code', 'rent')->firstOrFail()->id,
            'payment_method_id' => $cash->id,
            'amount' => '5.00',
            'description' => 'Golden path operating expense',
            'occurred_at' => now()->toDateTimeString(),
        ], $owner);

        $this->assertSame('100.00', $receipt->net_total);
        $this->assertSame('50.00', $receipt->fresh()->balance_due);
        $this->assertSame('50.00', $supplier->fresh()->current_balance);

        $this->assertSame('90.00', $sale->fresh()->net_total);
        $this->assertSame('60.00', $sale->fresh()->paid_amount);
        $this->assertSame('30.00', $sale->fresh()->balance_due);
        $this->assertSame('30.00', $customer->fresh()->current_balance);
        $this->assertSame('7.000000', $product->fresh()->stock_on_hand);

        $date = $shift->business_date->format('Y-m-d');

        $shiftClose = app(ShiftClosingService::class)->close($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'actual_cash' => '1005.00',
            'closing_notes' => 'Golden path exact count',
        ], $owner);

        $this->assertSame('1005.00', $shiftClose->expected_cash);
        $this->assertSame('0.00', $shiftClose->variance);

        $dayClose = app(BusinessDayClosingService::class)->close($date, [
            'idempotency_key' => (string) Str::uuid(),
            'notes' => 'Golden path daily close',
        ], $owner);

        $this->assertSame('90.00', $dayClose->net_sales_total);
        $this->assertSame('30.00', $dayClose->net_cogs_total);
        $this->assertSame('60.00', $dayClose->gross_profit_total);
        $this->assertSame('5.00', $dayClose->operating_expenses_total);
        $this->assertSame('55.00', $dayClose->net_profit_total);
        $this->assertSame('20.00', $dayClose->customer_collections_total);
        $this->assertSame('100.00', $dayClose->purchases_total);
        $this->assertSame('50.00', $dayClose->supplier_payments_total);
        $this->assertSame('1000.00', $dayClose->opening_cash_total);
        $this->assertSame('60.00', $dayClose->cash_inflow_total);
        $this->assertSame('55.00', $dayClose->cash_outflow_total);
        $this->assertSame('1005.00', $dayClose->expected_cash_total);
        $this->assertSame('1005.00', $dayClose->actual_cash_total);
        $this->assertSame('0.00', $dayClose->variance_total);

        $report = app(ReportingService::class)->build([
            'from' => $date,
            'to' => $date,
        ]);

        $this->assertSame('90.00', $report['summary']['net_sales']);
        $this->assertSame('30.00', $report['summary']['net_cogs']);
        $this->assertSame('60.00', $report['summary']['gross_profit']);
        $this->assertSame('5.00', $report['summary']['expenses']);
        $this->assertSame('55.00', $report['summary']['net_profit']);
        $this->assertSame('30.00', $report['summary']['receivables']);
        $this->assertSame('50.00', $report['summary']['payables']);
        $this->assertSame('70.00', $report['summary']['inventory_value']);

        $this->assertCount(1, $report['closingHistory']);
        $this->assertSame($dayClose->id, $report['closingHistory']->first()->id);
    }
}
