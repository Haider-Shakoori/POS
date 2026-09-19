<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('returned_total', 18, 2)->default(0)->after('balance_due');
            $table->decimal('receivable_reversed_total', 18, 2)->default(0)->after('returned_total');
            $table->decimal('refunded_total', 18, 2)->default(0)->after('receivable_reversed_total');
        });

        Schema::create('held_sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('cashier_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name_snapshot', 180)->default('Walk-in Customer');
            $table->decimal('sale_discount_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('held')->index();
            $table->text('notes')->nullable();
            $table->timestamp('held_at')->index();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['cashier_user_id', 'status', 'held_at'], 'held_sale_cashier_status_idx');
        });

        Schema::create('held_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('held_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot', 200);
            $table->string('sku_snapshot', 100);
            $table->string('unit_name_snapshot', 100);
            $table->decimal('quantity', 20, 6);
            $table->decimal('line_discount_amount', 18, 2)->default(0);
            $table->decimal('unit_price_snapshot', 18, 2);
            $table->timestamps();

            $table->unique(['held_sale_id', 'product_unit_id'], 'held_sale_product_unit_unique');
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20)->default('return')->index();
            $table->string('status', 20)->default('posted')->index();
            $table->string('reason', 500);
            $table->decimal('return_total', 18, 2);
            $table->decimal('cogs_reversed', 18, 2);
            $table->decimal('receivable_reversed', 18, 2)->default(0);
            $table->decimal('refund_total', 18, 2)->default(0);
            $table->timestamp('posted_at')->index();
            $table->timestamps();

            $table->index(['sale_id', 'type', 'posted_at'], 'sale_return_sale_type_idx');
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('return_amount', 18, 2);
            $table->decimal('cogs_amount', 18, 2);
            $table->timestamps();

            $table->unique(['sale_return_id', 'sale_item_id'], 'sale_return_item_unique');
        });

        Schema::create('sale_return_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_sale_stock_allocation_id')
                ->constrained('sale_item_stock_allocations')
                ->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('quantity_base', 20, 6);
            $table->timestamps();

            $table->index(
                ['original_sale_stock_allocation_id', 'quantity_base'],
                'return_stock_original_alloc_idx'
            );
        });

        Schema::create('inventory_cost_layer_restorations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_consumption_id')
                ->constrained('inventory_cost_layer_consumptions')
                ->restrictOnDelete();
            $table->foreignId('inventory_cost_layer_id')->nullable();
            $table->foreign(
                'inventory_cost_layer_id',
                'cost_restore_layer_fk'
            )->references('id')->on('inventory_cost_layers')->restrictOnDelete();
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('unit_cost_base', 18, 4);
            $table->decimal('cost_amount', 20, 4);
            $table->timestamps();

            $table->index(['original_consumption_id', 'quantity_base'], 'cost_restore_original_idx');
        });

        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('sale_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('reference', 120)->nullable();
            $table->timestamp('refunded_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sale_return_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
        Schema::dropIfExists('inventory_cost_layer_restorations');
        Schema::dropIfExists('sale_return_stock_allocations');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('held_sale_items');
        Schema::dropIfExists('held_sales');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'returned_total',
                'receivable_reversed_total',
                'refunded_total',
            ]);
        });
    }
};
