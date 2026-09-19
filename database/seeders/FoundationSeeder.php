<?php

namespace Database\Seeders;

use App\Models\ShopSetting;
use App\Models\Terminal;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        ShopSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'shop_name' => 'My Shop',
                'default_locale' => 'en',
                'receipt_locale' => 'en',
                'receipt_size' => config('pos.default_receipt_size', '80mm'),
                'cash_variance_tolerance' => 0,
                'negative_stock_enabled' => false,
            ],
        );

        Terminal::query()->firstOrCreate(
            ['code' => 'COUNTER-1'],
            ['name' => 'Counter 1', 'is_active' => true],
        );
    }
}
