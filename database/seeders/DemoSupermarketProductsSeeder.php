<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSupermarketProductsSeeder extends Seeder
{
    public function run(): void
    {
        // Sample prices and stock for first-time trials; replace with actual shop figures.
        $items = [
            ['Rice 5 kg', 'برنج ۵ کیلو', 'وریجې ۵ کیلو', 'BAG', 420, 490, 20],
            ['Flour 5 kg', 'آرد ۵ کیلو', 'اوړه ۵ کیلو', 'BAG', 240, 285, 24],
            ['Sugar 1 kg', 'شکر ۱ کیلو', 'بوره ۱ کیلو', 'BAG', 70, 85, 40],
            ['Cooking Oil 1 L', 'روغن پخت و پز ۱ لیتر', 'د پخلي غوړي ۱ لیټر', 'BTL', 115, 140, 35],
            ['Black Tea 500 g', 'چای سیاه ۵۰۰ گرام', 'تور چای ۵۰۰ ګرامه', 'PACK', 190, 230, 20],
            ['Green Tea 250 g', 'چای سبز ۲۵۰ گرام', 'شین چای ۲۵۰ ګرامه', 'PACK', 105, 130, 20],
            ['Salt 1 kg', 'نمک ۱ کیلو', 'مالګه ۱ کیلو', 'BAG', 25, 35, 30],
            ['Red Lentils 1 kg', 'دال سرخ ۱ کیلو', 'سره دال ۱ کیلو', 'BAG', 115, 140, 25],
            ['Chickpeas 1 kg', 'نخود ۱ کیلو', 'نخود ۱ کیلو', 'BAG', 130, 160, 25],
            ['Pasta 500 g', 'مکرونی ۵۰۰ گرام', 'مکروني ۵۰۰ ګرامه', 'PACK', 45, 60, 35],
            ['Biscuits Pack', 'بیسکویت بسته', 'بسکټ پاکټ', 'PACK', 25, 35, 50],
            ['Chocolate Bar', 'چاکلیت تخته ای', 'چاکلېټ ټوټه', 'PCS', 25, 35, 45],
            ['Bottled Water 500 ml', 'آب معدنی ۵۰۰ ملی لیتر', 'د اوبو بوتل ۵۰۰ ملي لیټر', 'BTL', 12, 20, 72],
            ['Bottled Water 1.5 L', 'آب معدنی ۱.۵ لیتر', 'د اوبو بوتل ۱.۵ لیټر', 'BTL', 25, 35, 40],
            ['Orange Juice 1 L', 'آب مالته ۱ لیتر', 'د مالټې جوس ۱ لیټر', 'BTL', 75, 95, 25],
            ['Cola 330 ml', 'نوشابه ۳۳۰ ملی لیتر', 'کولا ۳۳۰ ملي لیټر', 'CAN', 30, 40, 48],
            ['Milk 1 L', 'شیر ۱ لیتر', 'شیدې ۱ لیټر', 'BTL', 65, 80, 25],
            ['Powdered Milk 400 g', 'شیر خشک ۴۰۰ گرام', 'وچې شیدې ۴۰۰ ګرامه', 'CAN', 245, 295, 15],
            ['Laundry Detergent 1 kg', 'پودر لباس شویی ۱ کیلو', 'د جامو پوډر ۱ کیلو', 'BAG', 115, 145, 25],
            ['Dishwashing Liquid 500 ml', 'مایع ظرف شویی ۵۰۰ ملی لیتر', 'د لوښو مایع ۵۰۰ ملي لیټر', 'BTL', 65, 85, 25],
            ['Bath Soap', 'صابون حمام', 'د حمام صابون', 'PCS', 30, 45, 45],
            ['Shampoo 400 ml', 'شامپو ۴۰۰ ملی لیتر', 'شامپو ۴۰۰ ملي لیټر', 'BTL', 140, 175, 20],
            ['Toothpaste 100 ml', 'کریم دندان ۱۰۰ ملی لیتر', 'د غاښونو کریم ۱۰۰ ملي لیټر', 'PCS', 70, 95, 25],
            ['Tissue Box', 'دستمال کاغذی جعبه', 'د کاغذي دستمالو بکس', 'BOX', 55, 75, 30],
        ];

        $inventory = app(InventoryService::class);
        DB::transaction(function () use ($items, $inventory): void {
            foreach ($items as $index => [$en, $fa, $ps, $unitCode, $cost, $price, $quantity]) {
                $sku = sprintf('DEMO-%04d', $index + 1);
                $unit = Unit::query()->where('code', $unitCode)->firstOrFail();
                $product = Product::query()->firstOrCreate(
                    ['sku' => $sku],
                    [
                        'name_en' => $en, 'name_fa' => $fa, 'name_ps' => $ps,
                        'base_unit_id' => $unit->id,
                        'purchase_cost' => $cost, 'selling_price' => $price,
                        'minimum_stock' => 5, 'reorder_quantity' => 20,
                        'track_stock' => true, 'is_active' => true,
                    ]
                );
                ProductUnit::query()->firstOrCreate(
                    ['product_id' => $product->id, 'unit_id' => $unit->id],
                    ['conversion_factor' => 1, 'can_purchase' => true, 'can_sell' => true]
                );
                $hash = md5('dukan-demo-opening-'.$sku);
                $key = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);
                $inventory->addOpeningStock(
                    product: $product,
                    sourceQuantity: (string) $quantity,
                    sourceUnitId: $unit->id,
                    sourceUnitCost: (string) $cost,
                    notes: 'Demo opening inventory',
                    idempotencyKey: $key,
                );
            }
        });
    }
}
