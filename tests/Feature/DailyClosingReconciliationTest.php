<?php

namespace Tests\Feature;

use App\Enums\BusinessDayStatus;
use App\Enums\ShiftStatus;
use App\Models\BusinessDay;
use App\Models\BusinessDayClosure;
use App\Models\CashierShift;
use App\Models\CashierShiftClosure;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopSetting;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\ManualCashMovementService;
use App\Services\Cash\OperatingEntryService;
use App\Services\Cash\ShiftOpeningService;
use App\Services\Catalog\ProductService;
use App\Services\Closing\BusinessDayClosingService;
use App\Services\Closing\BusinessDayService;
use App\Services\Closing\ShiftClosingService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Sales\CheckoutService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DailyClosingReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Terminal $terminal;
    private PaymentMethod $cash;
    private PaymentMethod $bank;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->roles()->attach(Role::query()->where('name', 'owner')->firstOrFail());

        $this->terminal = Terminal::query()->where('code', 'COUNTER-1')->firstOrFail();
        $this->cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('code', 'bank')->firstOrFail();

        $this->supplier = Supplier::create([
            'name' => 'Closing Test Supplier',
            'phone' => '0700000066',
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_shift_close_is_idempotent_and_records_signed_variance_with_tolerance(): void
    {
        ShopSetting::query()->update(['cash_variance_tolerance' => '10.00']);

        $shift = $this->openShift('1000.00');

        app(ManualCashMovementService::class)->record($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'movement_type' => 'cash_deposit',
            'amount' => '50.00',
            'reason' => 'Drawer top up',
        ], $this->owner);

        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'actual_cash' => '1045.00',
            'variance_reason' => null,
            'closing_notes' => 'Counted by owner',
        ];

        $service = app(ShiftClosingService::class);
        $first = $service->close($shift, $payload, $this->owner);
        $second = $service->close($shift, $payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CashierShiftClosure::query()->count());
        $this->assertSame('1050.00', $first->expected_cash);
        $this->assertSame('1045.00', $first->actual_cash);
        $this->assertSame('-5.00', $first->variance);
        $this->assertSame('10.00', $first->tolerance);
        $this->assertTrue($first->within_tolerance);

        $shift->refresh();

        $this->assertSame(ShiftStatus::Closed, $shift->status);
        $this->assertSame('1050.00', $shift->expected_cash);
        $this->assertSame('1045.00', $shift->actual_cash);
        $this->assertSame('-5.00', $shift->variance);
        $this->assertTrue($shift->variance_within_tolerance);

        $conflict = $payload;
        $conflict['actual_cash'] = '1044.00';

        $this->expectException(DomainException::class);
        $service->close($shift, $conflict, $this->owner);
    }

    public function test_variance_outside_tolerance_requires_reason_and_exact_cash_has_zero_variance(): void
    {
        ShopSetting::query()->update(['cash_variance_tolerance' => '2.00']);

        $shift = $this->openShift('500.00');

        try {
            app(ShiftClosingService::class)->close($shift, [
                'idempotency_key' => (string) Str::uuid(),
                'actual_cash' => '510.00',
                'variance_reason' => '',
            ], $this->owner);

            $this->fail('Expected variance outside tolerance to require a reason.');
        } catch (DomainException) {
            $this->assertSame(0, CashierShiftClosure::query()->count());
            $this->assertSame(ShiftStatus::Open, $shift->fresh()->status);
        }

        $over = app(ShiftClosingService::class)->close($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'actual_cash' => '510.00',
            'variance_reason' => 'Unexpected cash overage',
        ], $this->owner);

        $this->assertSame('10.00', $over->variance);
        $this->assertFalse($over->within_tolerance);
        $this->assertSame('Unexpected cash overage', $over->variance_reason);

        app(BusinessDayService::class)->reopen(
            $shift->business_date->format('Y-m-d'),
            'Reopen day for shift recount',
            $this->owner,
        );

        app(ShiftClosingService::class)->reopen(
            $shift,
            'Recount physical cash',
            $this->owner,
        );

        $exact = app(ShiftClosingService::class)->close($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'actual_cash' => '500.00',
            'variance_reason' => null,
        ], $this->owner);

        $this->assertSame(2, $exact->version);
        $this->assertSame('0.00', $exact->variance);
        $this->assertTrue($exact->within_tolerance);
        $this->assertSame(2, CashierShiftClosure::query()->where('cashier_shift_id', $shift->id)->count());
    }

    public function test_daily_close_rejects_open_shift_then_snapshots_financial_and_cash_totals(): void
    {
        ShopSetting::query()->update(['cash_variance_tolerance' => '10.00']);

        $shift = $this->openShift('1000.00');
        [$product, $piece] = $this->makeProduct('DAY-CLOSE');

        $receipt = $this->receive($piece->id, '10', '10.0000');

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => '60.00',
                'tendered_amount' => '60.00',
            ]],
        ], $this->owner);

        $expenseCategory = ExpenseCategory::query()->where('code', 'rent')->firstOrFail();

        app(OperatingEntryService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'expense',
            'expense_category_id' => $expenseCategory->id,
            'payment_method_id' => $this->cash->id,
            'amount' => '25.00',
            'description' => 'Daily close expense',
        ], $this->owner);

        $date = $shift->business_date->format('Y-m-d');

        try {
            app(BusinessDayClosingService::class)->close($date, [
                'idempotency_key' => (string) Str::uuid(),
                'notes' => 'Should not close with open shift',
            ], $this->owner);

            $this->fail('Expected daily close to reject an open shift.');
        } catch (DomainException) {
            $this->assertSame(0, BusinessDayClosure::query()->count());
        }

        $shiftClosure = app(ShiftClosingService::class)->close($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'actual_cash' => '1030.00',
            'variance_reason' => null,
        ], $this->owner);

        $this->assertSame('1035.00', $shiftClosure->expected_cash);
        $this->assertSame('-5.00', $shiftClosure->variance);

        $key = (string) Str::uuid();
        $closing = app(BusinessDayClosingService::class)->close($date, [
            'idempotency_key' => $key,
            'notes' => 'First daily close',
        ], $this->owner);

        $retry = app(BusinessDayClosingService::class)->close($date, [
            'idempotency_key' => $key,
            'notes' => 'First daily close',
        ], $this->owner);

        $this->assertSame($closing->id, $retry->id);
        $this->assertSame(1, $closing->version);
        $this->assertSame(1, $closing->shift_count);
        $this->assertSame(1, $closing->sales_count);
        $this->assertSame('60.00', $closing->sales_net_total);
        $this->assertSame('0.00', $closing->sales_return_total);
        $this->assertSame('60.00', $closing->net_sales_total);
        $this->assertSame('20.00', $closing->sales_cogs_total);
        $this->assertSame('20.00', $closing->net_cogs_total);
        $this->assertSame('40.00', $closing->gross_profit_total);
        $this->assertSame('25.00', $closing->operating_expenses_total);
        $this->assertSame('15.00', $closing->net_profit_total);
        $this->assertSame('100.00', $closing->purchases_total);
        $this->assertSame('1000.00', $closing->opening_cash_total);
        $this->assertSame('60.00', $closing->cash_inflow_total);
        $this->assertSame('25.00', $closing->cash_outflow_total);
        $this->assertSame('1035.00', $closing->expected_cash_total);
        $this->assertSame('1030.00', $closing->actual_cash_total);
        $this->assertSame('-5.00', $closing->variance_total);

        $day = BusinessDay::query()->whereDate('business_date', $date)->firstOrFail();
        $this->assertSame(BusinessDayStatus::Closed, $day->status);
        $this->assertSame('60.00', $sale->fresh()->net_total);
        $this->assertSame('100.00', $receipt->fresh()->net_total);
        $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
    }

    public function test_closed_business_day_blocks_new_sales_and_receipts_until_reopened_then_reclose_creates_revision_two(): void
    {
        $shift = $this->openShift('1000.00');
        [$product, $piece] = $this->makeProduct('DAY-LOCK');
        $this->receive($piece->id, '10', '10.0000');

        app(ShiftClosingService::class)->close($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'actual_cash' => '1000.00',
        ], $this->owner);

        $date = $shift->business_date->format('Y-m-d');

        $firstClose = app(BusinessDayClosingService::class)->close($date, [
            'idempotency_key' => (string) Str::uuid(),
            'notes' => 'Initial close',
        ], $this->owner);

        $this->assertSame(1, $firstClose->version);

        try {
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

            $this->fail('Expected sale posting to a closed business day to fail.');
        } catch (DomainException) {
            $this->assertSame(0, \App\Models\Sale::query()->count());
        }

        try {
            $this->receive($piece->id, '1', '10.0000');

            $this->fail('Expected goods receipt posting to a closed business day to fail.');
        } catch (DomainException) {
            $this->assertSame(1, GoodsReceipt::query()->count());
        }

        try {
            app(ShiftClosingService::class)->reopen(
                $shift,
                'Cannot reopen while business day is closed',
                $this->owner,
            );

            $this->fail('Expected shift reopen to fail while the business day is closed.');
        } catch (DomainException) {
            $this->assertSame(ShiftStatus::Closed, $shift->fresh()->status);
        }

        app(BusinessDayService::class)->reopen(
            $date,
            'Post late approved activity',
            $this->owner,
        );

        $this->assertSame(
            BusinessDayStatus::Open,
            BusinessDay::query()->whereDate('business_date', $date)->firstOrFail()->status,
        );

        app(ShiftClosingService::class)->reopen(
            $shift,
            'Reopen drawer for approved late activity',
            $this->owner,
        );

        app(ManualCashMovementService::class)->record($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'movement_type' => 'cash_deposit',
            'amount' => '10.00',
            'reason' => 'Approved late cash correction',
        ], $this->owner);

        $secondShiftClose = app(ShiftClosingService::class)->close($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'actual_cash' => '1010.00',
        ], $this->owner);

        $this->assertSame(2, $secondShiftClose->version);
        $this->assertSame('1010.00', $secondShiftClose->expected_cash);
        $this->assertSame('0.00', $secondShiftClose->variance);

        $secondClose = app(BusinessDayClosingService::class)->close($date, [
            'idempotency_key' => (string) Str::uuid(),
            'notes' => 'Re-close after approved late activity',
        ], $this->owner);

        $this->assertSame(2, $secondClose->version);
        $this->assertSame('1010.00', $secondClose->expected_cash_total);
        $this->assertSame('1010.00', $secondClose->actual_cash_total);
        $this->assertSame('0.00', $secondClose->variance_total);
        $this->assertSame(2, BusinessDayClosure::query()->where('business_day_id', $secondClose->business_day_id)->count());

        $firstClose->refresh();
        $this->assertSame('1000.00', $firstClose->expected_cash_total);
        $this->assertSame('1000.00', $firstClose->actual_cash_total);
    }

    private function openShift(string $openingCash): CashierShift
    {
        return app(ShiftOpeningService::class)->open([
            'idempotency_key' => (string) Str::uuid(),
            'terminal_id' => $this->terminal->id,
            'opening_cash' => $openingCash,
        ], $this->owner);
    }

    private function makeProduct(string $sku): array
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => $sku.'-'.Str::upper(Str::random(5)),
            'name_en' => 'Closing Test Product',
            'name_fa' => 'محصول تست بستن',
            'name_ps' => 'د تړلو ازمایښتي محصول',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '30.00',
            'minimum_selling_price' => '25.00',
            'track_stock' => true,
            'track_expiry' => false,
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
    ): GoodsReceipt {
        return app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => now()->toDateTimeString(),
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ]],
        ], $this->owner);
    }
}
