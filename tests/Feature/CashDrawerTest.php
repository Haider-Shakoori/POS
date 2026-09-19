<?php

namespace Tests\Feature;

use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\GoodsReceipt;
use App\Models\OperatingEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\ManualCashMovementService;
use App\Services\Cash\OperatingEntryService;
use App\Services\Cash\ShiftOpeningService;
use App\Services\Catalog\ProductService;
use App\Services\Customers\CustomerCollectionService;
use App\Services\Customers\CustomerService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\SaleReturnService;
use App\Services\Suppliers\SupplierPaymentService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashDrawerTest extends TestCase
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
            'name' => 'Cash Drawer Supplier',
            'phone' => '0700000088',
            'opening_balance' => '0.00',
            'current_balance' => '0.00',
            'is_active' => true,
        ]);
    }

    public function test_shift_opening_creates_exactly_one_opening_float_and_retry_is_idempotent(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'terminal_id' => $this->terminal->id,
            'opening_cash' => '500.00',
        ];

        $service = app(ShiftOpeningService::class);
        $first = $service->open($payload, $this->owner);
        $second = $service->open($payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CashierShift::query()->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'opening_float')->count());

        $movement = CashMovement::query()->firstOrFail();
        $this->assertSame('500.00', $movement->amount);
        $this->assertSame('500.00', $movement->expected_cash_after);
        $this->assertSame('500.00', $first->fresh()->expected_cash);

        $conflict = $payload;
        $conflict['opening_cash'] = '600.00';

        $this->expectException(DomainException::class);
        $service->open($conflict, $this->owner);
    }

    public function test_zero_opening_float_still_creates_one_opening_movement(): void
    {
        $shift = $this->openShift('0.00');

        $movement = CashMovement::query()
            ->where('cashier_shift_id', $shift->id)
            ->where('movement_type', 'opening_float')
            ->firstOrFail();

        $this->assertSame('0.00', $movement->amount);
        $this->assertSame('0.00', $shift->fresh()->expected_cash);
    }

    public function test_cash_drawer_reconciles_all_cash_sources_and_manual_movements(): void
    {
        $shift = $this->openShift('1000.00');
        [$product, $piece] = $this->makeProduct('CASH-FLOW');

        $receipt = $this->receive(
            productUnitId: $piece->id,
            quantity: '10',
            unitCost: '10.0000',
            paidAmount: '20.00',
            paymentMethod: 'cash',
        );

        $cashSale = $this->sale(
            productUnitId: $piece->id,
            quantity: '2',
            paymentMethod: $this->cash,
            amount: '60.00',
        );

        $customer = $this->makeCustomer('Drawer Customer', '200.00');

        $creditSale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '1',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [],
        ], $this->owner);

        app(CustomerCollectionService::class)->collect($customer, [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method_id' => $this->cash->id,
            'amount' => '30.00',
            'tendered_amount' => '30.00',
        ], $this->owner);

        app(SupplierPaymentService::class)->record($this->supplier, [
            'idempotency_key' => (string) Str::uuid(),
            'amount' => '40.00',
            'method' => 'cash',
            'reference' => 'SP-40',
        ], $this->owner);

        $expenseCategory = ExpenseCategory::query()->where('code', 'rent')->firstOrFail();
        $incomeCategory = ExpenseCategory::query()->where('code', 'other_income')->firstOrFail();

        app(OperatingEntryService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'expense',
            'expense_category_id' => $expenseCategory->id,
            'payment_method_id' => $this->cash->id,
            'amount' => '25.00',
            'description' => 'Cash expense test',
        ], $this->owner);

        app(OperatingEntryService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'income',
            'expense_category_id' => $incomeCategory->id,
            'payment_method_id' => $this->cash->id,
            'amount' => '10.00',
            'description' => 'Cash other income test',
        ], $this->owner);

        app(SaleReturnService::class)->returnItems($cashSale, [
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Refund one unit',
            'items' => [[
                'sale_item_id' => $cashSale->items()->firstOrFail()->id,
                'quantity' => '1',
            ]],
            'refunds' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => null,
            ]],
        ], $this->owner);

        $manual = app(ManualCashMovementService::class);

        $depositKey = (string) Str::uuid();
        $firstDeposit = $manual->record($shift, [
            'idempotency_key' => $depositKey,
            'movement_type' => 'cash_deposit',
            'amount' => '100.00',
            'reason' => 'Top up drawer',
        ], $this->owner);
        $retryDeposit = $manual->record($shift, [
            'idempotency_key' => $depositKey,
            'movement_type' => 'cash_deposit',
            'amount' => '100.00',
            'reason' => 'Top up drawer',
        ], $this->owner);

        $this->assertSame($firstDeposit->id, $retryDeposit->id);

        $manual->record($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'movement_type' => 'cash_withdrawal',
            'amount' => '20.00',
            'reason' => 'Petty cash withdrawal',
        ], $this->owner);

        $manual->record($shift, [
            'idempotency_key' => (string) Str::uuid(),
            'movement_type' => 'drawer_to_safe',
            'amount' => '15.00',
            'reason' => 'Move excess cash to safe',
        ], $this->owner);

        // 1000 - 20 + 60 + 30 - 40 - 25 + 10 - 30 + 100 - 20 - 15 = 1050.
        $this->assertSame('1050.00', $shift->fresh()->expected_cash);
        $this->assertSame(11, CashMovement::query()->where('cashier_shift_id', $shift->id)->count());

        $this->assertSame(1, CashMovement::query()->where('movement_type', 'purchase_payment')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'cash_sale')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'customer_collection')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'supplier_payment')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'expense')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'other_income')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'sale_refund')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'cash_deposit')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'cash_withdrawal')->count());
        $this->assertSame(1, CashMovement::query()->where('movement_type', 'drawer_to_safe')->count());

        $this->assertSame('80.00', $this->supplier->fresh()->current_balance);
        $this->assertSame('0.00', $creditSale->fresh()->balance_due);
        $this->assertSame('0.00', $customer->fresh()->current_balance);
        $this->assertSame('30.00', $cashSale->fresh()->refunded_total);
        $this->assertSame('80.00', $receipt->fresh()->balance_due);
    }

    public function test_non_cash_transactions_do_not_require_or_change_a_cash_drawer(): void
    {
        [$product, $piece] = $this->makeProduct('NON-CASH');

        $this->receive(
            productUnitId: $piece->id,
            quantity: '10',
            unitCost: '10.0000',
            paidAmount: '0.00',
        );

        $sale = $this->sale(
            productUnitId: $piece->id,
            quantity: '1',
            paymentMethod: $this->bank,
            amount: '30.00',
        );

        $expenseCategory = ExpenseCategory::query()->where('code', 'internet')->firstOrFail();

        $entry = app(OperatingEntryService::class)->record([
            'idempotency_key' => (string) Str::uuid(),
            'entry_type' => 'expense',
            'expense_category_id' => $expenseCategory->id,
            'payment_method_id' => $this->bank->id,
            'amount' => '12.00',
            'description' => 'Bank-paid internet',
        ], $this->owner);

        $this->assertSame('0.00', $sale->balance_due);
        $this->assertSame('12.00', $entry->amount);
        $this->assertSame(0, CashierShift::query()->count());
        $this->assertSame(0, CashMovement::query()->count());
    }

    public function test_cash_checkout_without_open_shift_rolls_back_sale_payment_stock_and_cash(): void
    {
        [$product, $piece] = $this->makeProduct('NO-SHIFT');

        $this->receive(
            productUnitId: $piece->id,
            quantity: '5',
            unitCost: '10.0000',
            paidAmount: '0.00',
        );

        try {
            $this->sale(
                productUnitId: $piece->id,
                quantity: '2',
                paymentMethod: $this->cash,
                amount: '60.00',
            );

            $this->fail('Expected cash checkout without an open shift to fail.');
        } catch (DomainException) {
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, SalePayment::query()->count());
            $this->assertSame(0, CashMovement::query()->count());
            $this->assertSame('5.000000', $product->fresh()->stock_on_hand);
        }
    }

    public function test_cash_operating_entry_without_shift_rolls_back_entry(): void
    {
        $category = ExpenseCategory::query()->where('code', 'repair')->firstOrFail();

        try {
            app(OperatingEntryService::class)->record([
                'idempotency_key' => (string) Str::uuid(),
                'entry_type' => 'expense',
                'expense_category_id' => $category->id,
                'payment_method_id' => $this->cash->id,
                'amount' => '20.00',
                'description' => 'Requires drawer',
            ], $this->owner);

            $this->fail('Expected cash expense without shift to fail.');
        } catch (DomainException) {
            $this->assertSame(0, OperatingEntry::query()->count());
            $this->assertSame(0, CashMovement::query()->count());
        }
    }

    private function openShift(string $openingCash = '1000.00'): CashierShift
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
            'name_en' => 'Cash Drawer Product',
            'name_fa' => 'محصول صندوق',
            'name_ps' => 'د صندوق محصول',
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
        string $paidAmount,
        ?string $paymentMethod = null,
    ): GoodsReceipt {
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => now()->toDateTimeString(),
            'paid_amount' => $paidAmount,
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
            ]],
        ];

        if ($paymentMethod !== null) {
            $payload['payment_method'] = $paymentMethod;
        }

        return app(GoodsReceiptService::class)->post($payload, $this->owner);
    }

    private function sale(
        int $productUnitId,
        string $quantity,
        PaymentMethod $paymentMethod,
        string $amount,
    ): Sale {
        return app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $paymentMethod->id,
                'amount' => $amount,
                'tendered_amount' => $paymentMethod->is_cash ? $amount : null,
            ]],
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
