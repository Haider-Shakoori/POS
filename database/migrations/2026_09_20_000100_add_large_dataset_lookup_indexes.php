<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['is_active', 'name_en'], 'product_active_name_en_idx');
            $table->index(['is_active', 'name_fa'], 'product_active_name_fa_idx');
            $table->index(['is_active', 'name_ps'], 'product_active_name_ps_idx');
            $table->index(
                ['is_active', 'track_stock', 'track_expiry', 'stock_on_hand'],
                'product_inventory_lookup_idx'
            );
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->index(['is_active', 'name_en'], 'category_active_name_en_idx');
            $table->index(['is_active', 'name_fa'], 'category_active_name_fa_idx');
            $table->index(['is_active', 'name_ps'], 'category_active_name_ps_idx');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->index(['is_active', 'name'], 'customer_active_name_idx');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->index(['is_active', 'name'], 'supplier_active_name_idx');
        });

        Schema::table('product_batches', function (Blueprint $table): void {
            $table->index(['expires_at', 'stock_on_hand'], 'batch_expiry_stock_idx');
            $table->index(['batch_number', 'expires_at'], 'batch_number_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table): void {
            $table->dropIndex('batch_number_expiry_idx');
            $table->dropIndex('batch_expiry_stock_idx');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex('supplier_active_name_idx');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customer_active_name_idx');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('category_active_name_ps_idx');
            $table->dropIndex('category_active_name_fa_idx');
            $table->dropIndex('category_active_name_en_idx');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('product_inventory_lookup_idx');
            $table->dropIndex('product_active_name_ps_idx');
            $table->dropIndex('product_active_name_fa_idx');
            $table->dropIndex('product_active_name_en_idx');
        });
    }
};
