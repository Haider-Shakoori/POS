<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->date('business_date')->nullable()->after('user_id')->index();
            $table->foreignId('closed_by_user_id')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->boolean('variance_within_tolerance')->nullable()->after('variance');
            $table->text('variance_reason')->nullable()->after('variance_within_tolerance');
            $table->timestamp('reopened_at')->nullable()->after('closing_notes');
            $table->foreignId('reopened_by_user_id')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
        });

        DB::table('cashier_shifts')
            ->whereNull('business_date')
            ->update(['business_date' => DB::raw('DATE(opened_at)')]);

        Schema::create('business_days', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });

        foreach (
            DB::table('cashier_shifts')
                ->whereNotNull('business_date')
                ->distinct()
                ->orderBy('business_date')
                ->pluck('business_date') as $date
        ) {
            DB::table('business_days')->insertOrIgnore([
                'business_date' => $date,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('cashier_shift_closures', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('cashier_shift_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('closed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('expected_cash', 18, 2);
            $table->decimal('actual_cash', 18, 2);
            $table->decimal('variance', 18, 2);
            $table->decimal('tolerance', 18, 2);
            $table->boolean('within_tolerance');
            $table->text('variance_reason')->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamp('closed_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cashier_shift_id', 'version'], 'shift_closure_version_unique');
            $table->index(['cashier_shift_id', 'closed_at'], 'shift_closure_time_idx');
        });

        Schema::create('business_day_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_day_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('number', 40)->unique();
            $table->unsignedInteger('version');
            $table->foreignId('closed_by_user_id')->constrained('users')->restrictOnDelete();

            $table->unsignedInteger('shift_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);

            $table->decimal('sales_subtotal', 18, 2)->default(0);
            $table->decimal('sales_line_discount_total', 18, 2)->default(0);
            $table->decimal('sales_discount_total', 18, 2)->default(0);
            $table->decimal('sales_net_total', 18, 2)->default(0);
            $table->decimal('sales_return_total', 18, 2)->default(0);
            $table->decimal('net_sales_total', 18, 2)->default(0);

            $table->decimal('sales_cogs_total', 18, 2)->default(0);
            $table->decimal('cogs_reversed_total', 18, 2)->default(0);
            $table->decimal('net_cogs_total', 18, 2)->default(0);
            $table->decimal('gross_profit_total', 18, 2)->default(0);

            $table->decimal('customer_collections_total', 18, 2)->default(0);
            $table->decimal('purchases_total', 18, 2)->default(0);
            $table->decimal('purchase_returns_total', 18, 2)->default(0);
            $table->decimal('supplier_payments_total', 18, 2)->default(0);
            $table->decimal('operating_expenses_total', 18, 2)->default(0);
            $table->decimal('other_income_total', 18, 2)->default(0);
            $table->decimal('net_profit_total', 18, 2)->default(0);

            $table->decimal('opening_cash_total', 18, 2)->default(0);
            $table->decimal('cash_inflow_total', 18, 2)->default(0);
            $table->decimal('cash_outflow_total', 18, 2)->default(0);
            $table->decimal('expected_cash_total', 18, 2)->default(0);
            $table->decimal('actual_cash_total', 18, 2)->default(0);
            $table->decimal('variance_total', 18, 2)->default(0);
            $table->json('cash_breakdown')->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['business_day_id', 'version'], 'business_day_closure_version_unique');
            $table->index(['business_day_id', 'closed_at'], 'business_day_closure_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_day_closures');
        Schema::dropIfExists('cashier_shift_closures');
        Schema::dropIfExists('business_days');

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by_user_id');
            $table->dropColumn('reopened_at');
            $table->dropColumn('variance_reason');
            $table->dropColumn('variance_within_tolerance');
            $table->dropConstrainedForeignId('closed_by_user_id');
            $table->dropIndex(['business_date']);
            $table->dropColumn('business_date');
        });
    }
};
