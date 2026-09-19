<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name_en', 100);
            $table->string('name_fa', 100)->nullable();
            $table->string('name_ps', 100)->nullable();
            $table->string('symbol', 30)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name_en', 160);
            $table->string('name_fa', 160)->nullable();
            $table->string('name_ps', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 160)->unique();
            $table->string('name_fa', 160)->nullable();
            $table->string('name_ps', 160)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('name_en', 200);
            $table->string('name_fa', 200)->nullable();
            $table->string('name_ps', 200)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->text('description_en')->nullable();
            $table->text('description_fa')->nullable();
            $table->text('description_ps')->nullable();
            $table->string('shelf_location', 100)->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('purchase_cost', 18, 2)->default(0);
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->decimal('minimum_selling_price', 18, 2)->nullable();
            $table->decimal('wholesale_price', 18, 2)->nullable();
            $table->decimal('stock_on_hand', 20, 6)->default(0);
            $table->decimal('minimum_stock', 20, 6)->default(0);
            $table->decimal('reorder_quantity', 20, 6)->default(0);
            $table->boolean('track_stock')->default(true)->index();
            $table->boolean('track_expiry')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['brand_id', 'is_active']);
        });

        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('conversion_factor', 20, 6);
            $table->boolean('can_purchase')->default(false);
            $table->boolean('can_sell')->default(false);
            $table->decimal('selling_price', 18, 2)->nullable();
            $table->decimal('minimum_selling_price', 18, 2)->nullable();
            $table->decimal('wholesale_price', 18, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });

        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->string('barcode', 191)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'is_primary']);
        });

        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('batch_number', 100);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('stock_on_hand', 20, 6)->default(0);
            $table->boolean('is_blocked')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_number']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->foreignId('source_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 40)->index();
            $table->decimal('source_quantity', 20, 6)->nullable();
            $table->decimal('conversion_factor', 20, 6)->nullable();
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('balance_after', 20, 6);
            $table->decimal('batch_balance_after', 20, 6)->nullable();
            $table->decimal('source_unit_cost', 18, 4)->nullable();
            $table->decimal('unit_cost_base', 18, 4)->nullable();
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'occurred_at'], 'stock_move_product_time_idx');
            $table->index(['reference_type', 'reference_id'], 'stock_move_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_batches');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('units');
    }
};
