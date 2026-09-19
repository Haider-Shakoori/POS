<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table): void {
            $table->index(['can_sell', 'product_id'], 'product_unit_sell_product_idx');
            $table->index(['can_purchase', 'product_id'], 'product_unit_buy_product_idx');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->index(['product_id', 'sale_id'], 'sale_item_product_sale_idx');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['customer_id', 'sold_at'], 'sale_customer_sold_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sale_customer_sold_idx');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropIndex('sale_item_product_sale_idx');
        });

        Schema::table('product_units', function (Blueprint $table): void {
            $table->dropIndex('product_unit_sell_product_idx');
            $table->dropIndex('product_unit_buy_product_idx');
        });
    }
};
