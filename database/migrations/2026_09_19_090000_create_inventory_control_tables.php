<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->uuid('approval_idempotency_key')->nullable()->unique();
            $table->foreignId('counted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('counted_at')->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->decimal('expected_quantity_base', 20, 6);
            $table->decimal('physical_quantity_base', 20, 6);
            $table->decimal('variance_quantity_base', 20, 6);
            $table->foreignId('stock_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('cost_amount', 20, 4)->default(0);
            $table->timestamps();

            $table->index(['product_id', 'product_batch_id'], 'stock_count_product_batch_idx');
            $table->index(['stock_count_id', 'product_id'], 'stock_count_item_product_idx');
        });

        Schema::create('inventory_writeoffs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->string('writeoff_type', 20)->index();
            $table->foreignId('posted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->decimal('total_cost', 20, 4)->default(0);
            $table->timestamp('posted_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_writeoff_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_writeoff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('cost_amount', 20, 4);
            $table->timestamps();

            $table->index(['product_id', 'product_batch_id'], 'writeoff_product_batch_idx');
        });

        Schema::create('inventory_cost_adjustment_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
            $table->foreignId('inventory_cost_layer_id')->nullable();
            $table->foreign(
                'inventory_cost_layer_id',
                'cost_adjust_consume_layer_fk'
            )->references('id')->on('inventory_cost_layers')->restrictOnDelete();
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('unit_cost_base', 18, 4);
            $table->decimal('cost_amount', 20, 4);
            $table->string('cost_source', 30)->default('fifo');
            $table->timestamps();

            $table->index(['stock_movement_id', 'inventory_cost_layer_id'], 'cost_adjust_move_layer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_adjustment_consumptions');
        Schema::dropIfExists('inventory_writeoff_items');
        Schema::dropIfExists('inventory_writeoffs');
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
