<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'rent', 'entry_type' => 'expense', 'name_en' => 'Rent', 'name_fa' => 'کرایه', 'name_ps' => 'کرایه', 'sort_order' => 10],
            ['code' => 'electricity', 'entry_type' => 'expense', 'name_en' => 'Electricity', 'name_fa' => 'برق', 'name_ps' => 'برېښنا', 'sort_order' => 20],
            ['code' => 'salary', 'entry_type' => 'expense', 'name_en' => 'Salary', 'name_fa' => 'معاش', 'name_ps' => 'معاش', 'sort_order' => 30],
            ['code' => 'transport', 'entry_type' => 'expense', 'name_en' => 'Transportation', 'name_fa' => 'ترانسپورت', 'name_ps' => 'ترانسپورټ', 'sort_order' => 40],
            ['code' => 'food', 'entry_type' => 'expense', 'name_en' => 'Food', 'name_fa' => 'غذا', 'name_ps' => 'خواړه', 'sort_order' => 50],
            ['code' => 'maintenance', 'entry_type' => 'expense', 'name_en' => 'Maintenance', 'name_fa' => 'نگهداری', 'name_ps' => 'ساتنه', 'sort_order' => 60],
            ['code' => 'internet', 'entry_type' => 'expense', 'name_en' => 'Internet', 'name_fa' => 'انترنت', 'name_ps' => 'انټرنېټ', 'sort_order' => 70],
            ['code' => 'cleaning', 'entry_type' => 'expense', 'name_en' => 'Cleaning', 'name_fa' => 'پاک‌کاری', 'name_ps' => 'پاکوالی', 'sort_order' => 80],
            ['code' => 'shop_supplies', 'entry_type' => 'expense', 'name_en' => 'Shop Supplies', 'name_fa' => 'لوازم فروشگاه', 'name_ps' => 'د دوکان توکي', 'sort_order' => 90],
            ['code' => 'repair', 'entry_type' => 'expense', 'name_en' => 'Repair', 'name_fa' => 'ترمیم', 'name_ps' => 'ترمیم', 'sort_order' => 100],
            ['code' => 'misc_expense', 'entry_type' => 'expense', 'name_en' => 'Miscellaneous', 'name_fa' => 'متفرقه', 'name_ps' => 'نور مصارف', 'sort_order' => 110],
            ['code' => 'other_income', 'entry_type' => 'income', 'name_en' => 'Other Income', 'name_fa' => 'عاید سایر', 'name_ps' => 'نور عاید', 'sort_order' => 200],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                $category + ['is_active' => true],
            );
        }
    }
}
