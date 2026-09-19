<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('phone', 50)->nullable()->index();
            $table->string('alternate_phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['name', 'is_active']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name_en', 100);
            $table->string('name_fa', 100)->nullable();
            $table->string('name_ps', 100)->nullable();
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_collections', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('tendered_amount', 18, 2)->nullable();
            $table->decimal('change_amount', 18, 2)->default(0);
            $table->string('reference', 120)->nullable();
            $table->timestamp('collected_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'collected_at'], 'customer_collection_time_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('cashier_shift_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['customer_id', 'balance_due'], 'sale_customer_balance_idx');
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('applied_amount', 18, 2);
            $table->decimal('tendered_amount', 18, 2)->nullable();
            $table->decimal('change_amount', 18, 2)->default(0);
            $table->string('reference', 120)->nullable();
            $table->string('source_type', 30)->default('checkout');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('paid_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'paid_at']);
            $table->index(['source_type', 'source_id'], 'sale_payment_source_idx');
        });

        Schema::create('customer_collection_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_collection_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(
                ['customer_collection_id', 'sale_id'],
                'collection_sale_unique'
            );
        });

        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 40)->index();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('balance_after', 18, 2);
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 80)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'occurred_at'], 'customer_ledger_time_idx');
            $table->index(['reference_type', 'reference_id'], 'customer_ledger_reference_idx');
            $table->unique(
                ['customer_id', 'entry_type', 'reference_type', 'reference_id'],
                'customer_ledger_unique_ref'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
        Schema::dropIfExists('customer_collection_allocations');
        Schema::dropIfExists('sale_payments');

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sale_customer_balance_idx');
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customer_collections');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customers');
    }
};
