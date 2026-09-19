<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'code' => 'cash',
                'name_en' => 'Cash',
                'name_fa' => 'نقد',
                'name_ps' => 'نغدي',
                'is_cash' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'bank',
                'name_en' => 'Bank',
                'name_fa' => 'بانک',
                'name_ps' => 'بانک',
                'is_cash' => false,
                'sort_order' => 20,
            ],
            [
                'code' => 'mobile_wallet',
                'name_en' => 'Mobile Wallet',
                'name_fa' => 'کیف پول موبایل',
                'name_ps' => 'موبایل والټ',
                'is_cash' => false,
                'sort_order' => 30,
            ],
            [
                'code' => 'other',
                'name_en' => 'Other',
                'name_fa' => 'سایر',
                'name_ps' => 'نور',
                'is_cash' => false,
                'sort_order' => 40,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                $method + ['is_active' => true],
            );
        }
    }
}
