<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopSetting;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\Catalog\ProductCsvService;
use App\Services\Catalog\ProductService;
use App\Services\Sales\CheckoutService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrintingImportSettingsTest extends TestCase
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

    public function test_shop_settings_are_validated_authorized_and_audited(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.shop.update'), [
                'shop_name' => 'Kabul Market',
                'address' => 'Kabul',
                'phone' => '0700000000',
                'default_locale' => 'fa',
                'receipt_locale' => 'ps',
                'receipt_size' => '57mm',
                'cash_variance_tolerance' => '25.00',
                'negative_stock_enabled' => '1',
                'discount_approval_threshold' => '100.00',
            ])
            ->assertRedirect();

        $settings = ShopSetting::query()->findOrFail(1);
        $this->assertSame('Kabul Market', $settings->shop_name);
        $this->assertSame('57mm', $settings->receipt_size);
        $this->assertSame('ps', $settings->receipt_locale);
        $this->assertTrue($settings->negative_stock_enabled);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'settings.shop.updated',
            'auditable_id' => $settings->id,
        ]);

        $cashier = User::factory()->create();
        $cashier->roles()->attach(Role::query()->where('name', 'cashier')->firstOrFail());

        $this->actingAs($cashier)
            ->get(route('settings.shop.edit'))
            ->assertForbidden();
    }

    public function test_product_csv_import_creates_catalog_without_bypassing_stock_ledger_and_exports_cleanly(): void
    {
        $content = $this->csv([
            ProductCsvService::HEADERS,
            [
                'CSV-001',
                'CSV Product',
                'محصول CSV',
                'د CSV محصول',
                '',
                '',
                'PCS',
                '10.00',
                '25.00',
                '20.00',
                '',
                '5',
                '10',
                '1',
                '0',
                '6291000000999',
                'A-1',
            ],
        ]);

        $file = UploadedFile::fake()->createWithContent('products.csv', $content);

        $this->actingAs($this->owner)
            ->post(route('inventory.products.import'), ['file' => $file])
            ->assertRedirect(route('inventory.products.index'));

        $product = Product::query()->where('sku', 'CSV-001')->firstOrFail();

        $this->assertSame('0.000000', $product->stock_on_hand);
        $this->assertSame(0, StockMovement::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $product->id,
            'barcode' => '6291000000999',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.products.imported']);

        $response = $this->actingAs($this->owner)
            ->get(route('inventory.products.export'));

        $response->assertOk();
        $export = $response->streamedContent();

        $this->assertStringContainsString('CSV-001', $export);
        $this->assertStringContainsString('6291000000999', $export);
        $this->assertStringNotContainsString('stock_on_hand', $export);
    }

    public function test_sale_receipt_respects_configured_paper_size_and_never_exposes_profit_data(): void
    {
        ShopSetting::query()->findOrFail(1)->update([
            'shop_name' => 'Receipt Shop',
            'receipt_size' => '57mm',
            'receipt_locale' => 'en',
        ]);

        [$product, $unit] = $this->makeProductWithBarcode();

        $sale = app(CheckoutService::class)->checkout([
            'idempotency_key' => (string) Str::uuid(),
            'sale_discount_amount' => '0.00',
            'items' => [[
                'product_unit_id' => $unit->id,
                'quantity' => '1',
                'line_discount_amount' => '0.00',
            ]],
            'payments' => [[
                'payment_method_id' => PaymentMethod::query()->where('code', 'bank')->firstOrFail()->id,
                'amount' => '25.00',
            ]],
        ], $this->owner);

        $this->actingAs($this->owner)
            ->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertSee($sale->number)
            ->assertSee('Receipt Shop')
            ->assertSee('width: 57mm', false)
            ->assertDontSee('COGS')
            ->assertDontSee('Gross Profit');
    }

    public function test_barcode_label_route_renders_machine_readable_svg_and_requested_quantity(): void
    {
        [$product] = $this->makeProductWithBarcode();
        $barcode = $product->barcodes()->firstOrFail();

        $response = $this->actingAs($this->owner)
            ->get(route('inventory.barcodes.labels', [
                'productBarcode' => $barcode,
                'quantity' => 2,
            ]));

        $response->assertOk()
            ->assertSee('<svg', false)
            ->assertSee($barcode->barcode)
            ->assertSee($product->sku);

        $this->assertSame(2, substr_count($response->getContent(), 'class="label"'));
    }

    private function makeProductWithBarcode(): array
    {
        $piece = Unit::query()->where('code', 'PCS')->firstOrFail();

        $product = app(ProductService::class)->create([
            'sku' => 'PRINT-'.Str::upper(Str::random(6)),
            'name_en' => 'Printable Product',
            'base_unit_id' => $piece->id,
            'purchase_cost' => '10.00',
            'selling_price' => '25.00',
            'track_stock' => false,
            'track_expiry' => false,
            'barcodes' => [[
                'barcode' => '6291000012345',
                'unit_id' => $piece->id,
                'is_primary' => true,
            ]],
        ], $this->owner);

        return [
            $product,
            $product->productUnits()->where('unit_id', $piece->id)->firstOrFail(),
        ];
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
