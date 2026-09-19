<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->foreignId('source_stock_movement_id')->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('initial_quantity_base', 20, 6);
            $table->decimal('remaining_quantity_base', 20, 6);
            $table->decimal('unit_cost_base', 18, 4)->default(0);
            $table->timestamp('received_at')->index();
            $table->timestamps();

            $table->index(['product_id', 'remaining_quantity_base', 'received_at'], 'cost_layer_product_fifo_idx');
            $table->index(['product_batch_id', 'remaining_quantity_base'], 'cost_layer_batch_remaining_idx');
        });

        DB::table('inventory_cost_layers')->insertUsing(
            [
                'product_id',
                'product_batch_id',
                'source_stock_movement_id',
                'initial_quantity_base',
                'remaining_quantity_base',
                'unit_cost_base',
                'received_at',
                'created_at',
                'updated_at',
            ],
            DB::table('stock_movements')
                ->selectRaw(
                    'product_id, product_batch_id, id, quantity_base, quantity_base, COALESCE(unit_cost_base, 0), occurred_at, created_at, created_at'
                )
                ->whereIn('movement_type', ['opening_stock', 'purchase'])
                ->where('quantity_base', '>', 0)
        );

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('completed')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('customer_name_snapshot', 180)->default('Walk-in Customer');
            $table->decimal('subtotal', 18, 2);
            $table->decimal('line_discount_total', 18, 2)->default(0);
            $table->decimal('sale_discount_amount', 18, 2)->default(0);
            $table->decimal('net_total', 18, 2);
            $table->decimal('cogs_total', 18, 2)->default(0);
            $table->decimal('gross_profit', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2);
            $table->timestamp('sold_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cashier_user_id', 'sold_at']);
            $table->index(['cashier_shift_id', 'sold_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot', 200);
            $table->string('sku_snapshot', 100);
            $table->string('unit_name_snapshot', 100);
            $table->decimal('quantity', 20, 6);
            $table->decimal('conversion_factor', 20, 6);
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('minimum_unit_price', 18, 2)->nullable();
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_discount_amount', 18, 2)->default(0);
            $table->decimal('allocated_sale_discount', 18, 2)->default(0);
            $table->decimal('line_net_total', 18, 2);
            $table->decimal('cogs_amount', 18, 2)->default(0);
            $table->decimal('gross_profit', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
        });

        Schema::create('sale_item_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('quantity_base', 20, 6);
            $table->timestamps();

            $table->index(['sale_item_id', 'product_batch_id'], 'sale_stock_item_batch_idx');
        });

        Schema::create('inventory_cost_layer_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_cost_layer_id')->nullable()->constrained('inventory_cost_layers')->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('unit_cost_base', 18, 4);
            $table->decimal('cost_amount', 20, 4);
            $table->string('cost_source', 30)->default('fifo');
            $table->timestamps();

            $table->index(['sale_item_id', 'inventory_cost_layer_id'], 'cost_consume_sale_layer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layer_consumptions');
        Schema::dropIfExists('sale_item_stock_allocations');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('inventory_cost_layers');
    }
};
