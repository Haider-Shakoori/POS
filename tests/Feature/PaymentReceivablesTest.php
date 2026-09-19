<?php

namespace Tests\Feature;

use App\Enums\SalePaymentStatus;
use App\Models\Customer;
use App\Models\CustomerCollection;
use App\Models\CustomerLedgerEntry;
use App\Models\GoodsReceipt;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Cash\ShiftOpeningService;
use App\Services\Catalog\ProductService;
use App\Services\Customers\CustomerCollectionService;
use App\Services\Customers\CustomerService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Sales\CheckoutService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Supplier $supplier;
    private PaymentMethod $cash;
    private PaymentMethod $bank;

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
            'name' => 'Payments Test Supplier',
            'phone' => '0700000099',
            'opening_balance' => '0.00',
            'is_active' => true,
        ]);

        $this->cash = PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        $this->bank = PaymentMethod::query()->where('code', 'bank')->firstOrFail();
    }

    public function test_cashier_can_quick_create_customer_from_pos_without_full_customer_manage_permission(): void
    {
        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->assertTrue($cashier->hasPermission('customers.quick_create'));
        $this->assertFalse($cashier->hasPermission('customers.manage'));

        $this->actingAs($cashier)
            ->postJson(route('pos.customers.store'), [
                'name' => 'Quick POS Customer',
                'phone' => '0700111222',
                'credit_limit' => '100.00',
                'opening_balance' => '0.00',
            ])
            ->assertCreated()
            ->assertJsonPath('customer.name', 'Quick POS Customer');

        $this->actingAs($cashier)
            ->postJson(route('customers.store'), [
                'name' => 'Forbidden Full Manage Customer',
                'credit_limit' => '0.00',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('customers', ['name' => 'Quick POS Customer']);
        $this->assertDatabaseMissing('customers', ['name' => 'Forbidden Full Manage Customer']);
    }

    public function test_full_cash_checkout_records_applied_amount_tender_and_change(): void
    {
        [$product, $piece] = $this->makeProduct('CASH');
        $this->receive($piece->id, '10');

        $response = $this->actingAs($this->owner)->postJson(route('pos.sales.store'), [
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
                'tendered_amount' => '100.00',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('sale.net_total', '60.00')
            ->assertJsonPath('sale.paid_amount', '60.00')
            ->assertJsonPath('sale.balance_due', '0.00')
            ->assertJsonPath('sale.payment_status', 'paid');

        $sale = Sale::query()->firstOrFail();
        $payment = $sale->payments()->firstOrFail();

        $this->assertSame(SalePaymentStatus::Paid, $sale->payment_status);
        $this->assertSame('60.00', $payment->applied_amount);
        $this->assertSame('100.00', $payment->tendered_amount);
        $this->assertSame('40.00', $payment->change_amount);
        $this->assertNotNull($sale->settlement_finalized_at);
        $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
        $this->assertSame(0, CustomerLedgerEntry::query()->count());
    }

    public function test_split_payment_checkout_reconciles_exactly(): void
    {
        [$product, $piece] = $this->makeProduct('SPLIT');
        $this->receive($piece->id, '10');

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [
                [
                    'payment_method_id' => $this->bank->id,
                    'amount' => '20.00',
                    'reference' => 'BANK-001',
                ],
                [
                    'payment_method_id' => $this->cash->id,
                    'amount' => '40.00',
                    'tendered_amount' => '50.00',
                ],
            ],
        ], $this->owner);

        $this->assertSame('60.00', $sale->paid_amount);
        $this->assertSame('0.00', $sale->balance_due);
        $this->assertSame(SalePaymentStatus::Paid, $sale->payment_status);
        $this->assertCount(2, $sale->payments);

        $cashPayment = $sale->payments->firstWhere('payment_method_id', $this->cash->id);
        $this->assertSame('10.00', $cashPayment->change_amount);
        $this->assertSame('8.000000', $product->fresh()->stock_on_hand);
    }

    public function test_partial_payment_posts_exact_customer_receivable(): void
    {
        [$product, $piece] = $this->makeProduct('PARTIAL');
        $this->receive($piece->id, '10');
        $customer = $this->makeCustomer('Partial Customer', '100.00');

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => '20.00',
                'tendered_amount' => '20.00',
            ]],
        ], $this->owner);

        $this->assertSame('20.00', $sale->paid_amount);
        $this->assertSame('40.00', $sale->balance_due);
        $this->assertSame(SalePaymentStatus::Partial, $sale->payment_status);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame('40.00', $customer->fresh()->current_balance);

        $this->assertDatabaseHas('customer_ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'sale',
            'debit' => '60.00',
            'credit' => '0.00',
        ]);
        $this->assertDatabaseHas('customer_ledger_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'sale_payment',
            'debit' => '0.00',
            'credit' => '20.00',
            'balance_after' => '40.00',
        ]);
    }

    public function test_unpaid_walk_in_checkout_is_rejected_and_rolls_back_stock(): void
    {
        [$product, $piece] = $this->makeProduct('WALKIN-CREDIT');
        $this->receive($piece->id, '10');

        try {
            app(CheckoutService::class)->checkout([
                'idempotency_key' => (string) Str::uuid(),
                'sale_discount_amount' => '0.00',
                'items' => [[
                    'product_unit_id' => $piece->id,
                    'quantity' => '2',
                    'line_discount_amount' => '0.00',
                ]],
                'payments' => [],
            ], $this->owner);

            $this->fail('Expected walk-in credit checkout to fail.');
        } catch (DomainException) {
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, StockMovement::query()->where('movement_type', 'sale')->count());
            $this->assertSame('10.000000', $product->fresh()->stock_on_hand);
        }
    }

    public function test_credit_limit_failure_rolls_back_sale_stock_and_ledger(): void
    {
        [$product, $piece] = $this->makeProduct('LIMIT');
        $this->receive($piece->id, '10');
        $customer = $this->makeCustomer('Limited Customer', '30.00');

        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->assertTrue($cashier->hasPermission('sales.credit'));
        $this->assertFalse($cashier->hasPermission('sales.override_credit_limit'));

        try {
            app(CheckoutService::class)->checkout([
                'idempotency_key' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'sale_discount_amount' => '0.00',
                'items' => [[
                    'product_unit_id' => $piece->id,
                    'quantity' => '2',
                    'line_discount_amount' => '0.00',
                ]],
                'payments' => [[
                    'payment_method_id' => $this->cash->id,
                    'amount' => '20.00',
                    'tendered_amount' => '20.00',
                ]],
            ], $cashier);

            $this->fail('Expected credit limit to reject checkout.');
        } catch (DomainException) {
            $this->assertSame(0, Sale::query()->count());
            $this->assertSame(0, SalePayment::query()->count());
            $this->assertSame(0, CustomerLedgerEntry::query()->where('customer_id', $customer->id)->count());
            $this->assertSame('0.00', $customer->fresh()->current_balance);
            $this->assertSame('10.000000', $product->fresh()->stock_on_hand);
        }
    }

    public function test_full_credit_sale_is_allowed_for_cashier_within_limit(): void
    {
        [$product, $piece] = $this->makeProduct('CASHIER-CREDIT');
        $this->receive($piece->id, '10');
        $customer = $this->makeCustomer('Cashier Credit', '100.00');

        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->assertTrue($cashier->hasPermission('sales.credit'));
        $this->assertFalse($cashier->hasPermission('sales.override_credit_limit'));

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [],
        ], $cashier);

        $this->assertSame(SalePaymentStatus::Unpaid, $sale->payment_status);
        $this->assertSame('60.00', $sale->balance_due);
        $this->assertSame('60.00', $customer->fresh()->current_balance);
    }

    public function test_collection_reduces_customer_balance_and_allocates_oldest_sales_first(): void
    {
        [$product, $piece] = $this->makeProduct('COLLECT');
        $this->receive($piece->id, '20');
        $customer = $this->makeCustomer('Collection Customer', '500.00');

        $first = $this->creditSale($piece->id, $customer);
        $second = $this->creditSale($piece->id, $customer);

        $this->assertSame('120.00', $customer->fresh()->current_balance);

        $collection = app(CustomerCollectionService::class)->collect($customer, [
            'idempotency_key' => (string) Str::uuid(),
            'payment_method_id' => $this->cash->id,
            'amount' => '90.00',
            'tendered_amount' => '100.00',
            'reference' => 'RCPT-90',
        ], $this->owner);

        $this->assertSame('90.00', $collection->amount);
        $this->assertSame('10.00', $collection->change_amount);
        $this->assertSame('30.00', $customer->fresh()->current_balance);

        $first->refresh();
        $second->refresh();

        $this->assertSame('0.00', $first->balance_due);
        $this->assertSame('60.00', $first->paid_amount);
        $this->assertSame(SalePaymentStatus::Paid, $first->payment_status);

        $this->assertSame('30.00', $second->balance_due);
        $this->assertSame('30.00', $second->paid_amount);
        $this->assertSame(SalePaymentStatus::Partial, $second->payment_status);

        $allocations = $collection->allocations()->orderBy('id')->get();
        $this->assertCount(2, $allocations);
        $this->assertSame($first->id, $allocations[0]->sale_id);
        $this->assertSame('60.00', $allocations[0]->amount);
        $this->assertSame($second->id, $allocations[1]->sale_id);
        $this->assertSame('30.00', $allocations[1]->amount);
    }

    public function test_collection_retry_is_idempotent(): void
    {
        [$product, $piece] = $this->makeProduct('COLLECTION-IDEM');
        $this->receive($piece->id, '10');
        $customer = $this->makeCustomer('Idempotent Collection', '200.00');
        $sale = $this->creditSale($piece->id, $customer);

        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'payment_method_id' => $this->cash->id,
            'amount' => '30.00',
            'tendered_amount' => '30.00',
        ];

        $service = app(CustomerCollectionService::class);
        $first = $service->collect($customer, $payload, $this->owner);
        $second = $service->collect($customer, $payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CustomerCollection::query()->count());
        $this->assertSame('30.00', $customer->fresh()->current_balance);
        $this->assertSame('30.00', $sale->fresh()->balance_due);
        $this->assertSame(1, SalePayment::query()->where('source_type', 'collection')->count());
    }

    public function test_checkout_retry_does_not_duplicate_payment_or_ledger_and_rejects_conflict(): void
    {
        [$product, $piece] = $this->makeProduct('CHECKOUT-IDEM');
        $this->receive($piece->id, '10');
        $customer = $this->makeCustomer('Checkout Retry', '100.00');

        $key = (string) Str::uuid();
        $payload = [
            'idempotency_key' => $key,
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $piece->id,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => $this->cash->id,
                'amount' => '20.00',
                'tendered_amount' => '20.00',
            ]],
        ];

        $service = app(CheckoutService::class);
        $first = $service->checkout($payload, $this->owner);
        $second = $service->checkout($payload, $this->owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame(1, SalePayment::query()->where('source_type', 'checkout')->count());
        $this->assertSame(2, CustomerLedgerEntry::query()->where('customer_id', $customer->id)->count());
        $this->assertSame('40.00', $customer->fresh()->current_balance);
        $this->assertSame('8.000000', $product->fresh()->stock_on_hand);

        $conflict = $payload;
        $conflict['payments'][0]['payment_method_id'] = $this->bank->id;
        unset($conflict['payments'][0]['tendered_amount']);

        $this->expectException(DomainException::class);
        $service->checkout($conflict, $this->owner);
    }

    public function test_customer_opening_balance_creates_matching_ledger_entry(): void
    {
        $customer = app(CustomerService::class)->create([
            'name' => 'Opening Balance Customer',
            'phone' => '0700123456',
            'credit_limit' => '100.00',
            'opening_balance' => '25.00',
        ], $this->owner);

        $this->assertSame('25.00', $customer->current_balance);

        $entry = $customer->ledgerEntries()->firstOrFail();
        $this->assertSame('opening_balance', $entry->entry_type);
        $this->assertSame('25.00', $entry->debit);
        $this->assertSame('0.00', $entry->credit);
        $this->assertSame('25.00', $entry->balance_after);
    }

    private function makeProduct(string $sku): array
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => $sku.'-'.Str::upper(Str::random(5)),
            'name_en' => 'Payment Test Product',
            'name_fa' => 'محصول تست پرداخت',
            'name_ps' => 'د تادیې ازمایښتي محصول',
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

    private function receive(int $productUnitId, string $quantity): GoodsReceipt
    {
        return app(GoodsReceiptService::class)->post([
            'idempotency_key' => (string) Str::uuid(),
            'supplier_id' => $this->supplier->id,
            'received_at' => '2026-09-19 12:00:00',
            'paid_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => $quantity,
                'unit_cost' => '10.0000',
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

    private function creditSale(int $productUnitId, Customer $customer): Sale
    {
        return app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $productUnitId,
                'quantity' => '2',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [],
        ], $this->owner);
    }
}
