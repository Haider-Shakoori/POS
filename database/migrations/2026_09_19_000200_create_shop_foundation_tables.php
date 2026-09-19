<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name')->default('My Shop');
            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('default_locale', 5)->default('en');
            $table->string('receipt_locale', 5)->default('en');
            $table->string('receipt_size', 10)->default('80mm');
            $table->decimal('cash_variance_tolerance', 18, 2)->default(0);
            $table->boolean('negative_stock_enabled')->default(false);
            $table->decimal('discount_approval_threshold', 18, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 18, 2);
            $table->decimal('expected_cash', 18, 2)->nullable();
            $table->decimal('actual_cash', 18, 2)->nullable();
            $table->decimal('variance', 18, 2)->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['terminal_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120)->index();
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('terminals');
        Schema::dropIfExists('shop_settings');
    }
};
