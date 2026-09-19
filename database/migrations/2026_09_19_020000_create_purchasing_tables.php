<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('contact_person', 160)->nullable();
            $table->string('phone', 50)->nullable()->index();
            $table->string('alternate_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['name', 'is_active']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->date('order_date')->index();
            $table->date('expected_date')->nullable()->index();
            $table->string('supplier_reference', 120)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('line_discount_total', 18, 2)->default(0);
            $table->decimal('order_discount_amount', 18, 2)->default(0);
            $table->decimal('net_total', 18, 2)->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->decimal('ordered_quantity', 20, 6);
            $table->decimal('received_quantity', 20, 6)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_discount_amount', 18, 2)->default(0);
            $table->decimal('line_net_total', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['purchase_order_id', 'product_id', 'product_unit_id'],
                'po_item_product_unit_unique'
            );
            $table->index(['purchase_order_id', 'product_id']);
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('posted')->index();
            $table->uuid('idempotency_key')->unique();
            $table->string('supplier_invoice_reference', 120)->nullable();
            $table->timestamp('received_at')->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('line_discount_total', 18, 2)->default(0);
            $table->decimal('receipt_discount_amount', 18, 2)->default(0);
            $table->decimal('expense_total', 18, 2)->default(0);
            $table->decimal('net_total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'received_at']);
            $table->index(['purchase_order_id', 'status']);
        });

        Schema::create('goods_receipt_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('description', 180)->nullable();
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained()->restrictOnDelete();
            $table->uuid('stock_movement_key')->unique();
            $table->decimal('quantity', 20, 6);
            $table->decimal('conversion_factor', 20, 6);
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('source_unit_cost', 18, 4);
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_discount_amount', 18, 2)->default(0);
            $table->decimal('allocated_receipt_discount', 18, 2)->default(0);
            $table->decimal('allocated_expense', 18, 2)->default(0);
            $table->decimal('landed_total', 18, 2);
            $table->decimal('source_unit_landed_cost', 18, 4);
            $table->decimal('base_unit_landed_cost', 18, 4);
            $table->string('batch_number', 100)->nullable();
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->foreignId('stock_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->index(['goods_receipt_id', 'product_id']);
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('method', 30);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipt_expenses');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('document_sequences');
    }
};
