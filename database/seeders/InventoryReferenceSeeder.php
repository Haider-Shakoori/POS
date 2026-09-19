<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class InventoryReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'PCS', 'name_en' => 'Piece', 'name_fa' => 'عدد', 'name_ps' => 'عدد', 'symbol' => 'pc', 'decimal_places' => 0],
            ['code' => 'PACK', 'name_en' => 'Packet', 'name_fa' => 'بسته', 'name_ps' => 'پاکټ', 'symbol' => 'pack', 'decimal_places' => 0],
            ['code' => 'BOX', 'name_en' => 'Box', 'name_fa' => 'بکس', 'name_ps' => 'بکس', 'symbol' => 'box', 'decimal_places' => 0],
            ['code' => 'CTN', 'name_en' => 'Carton', 'name_fa' => 'کارتن', 'name_ps' => 'کارتن', 'symbol' => 'ctn', 'decimal_places' => 0],
            ['code' => 'BTL', 'name_en' => 'Bottle', 'name_fa' => 'بوتل', 'name_ps' => 'بوتل', 'symbol' => 'btl', 'decimal_places' => 0],
            ['code' => 'CAN', 'name_en' => 'Can', 'name_fa' => 'قوطی', 'name_ps' => 'قوطی', 'symbol' => 'can', 'decimal_places' => 0],
            ['code' => 'BAG', 'name_en' => 'Bag', 'name_fa' => 'بوجی', 'name_ps' => 'بوجۍ', 'symbol' => 'bag', 'decimal_places' => 0],
            ['code' => 'KG', 'name_en' => 'Kilogram', 'name_fa' => 'کیلوگرام', 'name_ps' => 'کیلوګرام', 'symbol' => 'kg', 'decimal_places' => 3],
            ['code' => 'G', 'name_en' => 'Gram', 'name_fa' => 'گرام', 'name_ps' => 'ګرام', 'symbol' => 'g', 'decimal_places' => 0],
            ['code' => 'L', 'name_en' => 'Liter', 'name_fa' => 'لیتر', 'name_ps' => 'لیټر', 'symbol' => 'L', 'decimal_places' => 3],
            ['code' => 'DOZ', 'name_en' => 'Dozen', 'name_fa' => 'درجن', 'name_ps' => 'درجن', 'symbol' => 'doz', 'decimal_places' => 0],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [...$unit, 'is_active' => true],
            );
        }
    }
}
