<?php

use App\Support\Decimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('entry_type', 20)->index();
            $table->string('name_en', 120);
            $table->string('name_fa', 120)->nullable();
            $table->string('name_ps', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('operating_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('entry_type', 20)->index();
            $table->decimal('amount', 18, 2);
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['entry_type', 'occurred_at'], 'operating_entry_type_time_idx');
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 160)->unique();
            $table->foreignId('cashier_shift_id')->constrained()->restrictOnDelete();
            $table->foreignId('terminal_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 50)->index();
            $table->string('direction', 10)->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('expected_cash_after', 18, 2);
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_number', 80)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cashier_shift_id', 'occurred_at'], 'cash_movement_shift_time_idx');
            $table->index(['source_type', 'source_id'], 'cash_movement_source_idx');
            $table->unique(
                ['source_type', 'source_id', 'movement_type'],
                'cash_movement_unique_source'
            );
        });

        $this->backfillExistingCashEvidence();
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('operating_entries');
        Schema::dropIfExists('expense_categories');
    }

    private function backfillExistingCashEvidence(): void
    {
        $shifts = DB::table('cashier_shifts')->orderBy('opened_at')->orderBy('id')->get();

        foreach ($shifts as $shift) {
            $events = [];

            $opening = Decimal::normalize((string) $shift->opening_cash, 2);

            if (Decimal::isPositive($opening)) {
                $events[] = [
                    'key' => 'shift:'.$shift->id.':opening',
                    'movement_type' => 'opening_float',
                    'direction' => 'inflow',
                    'amount' => $opening,
                    'source_type' => 'cashier_shift',
                    'source_id' => (int) $shift->id,
                    'reference_number' => null,
                    'reason' => 'Opening float backfill.',
                    'occurred_at' => $shift->opened_at,
                    'actor_user_id' => $shift->user_id,
                ];
            }

            $salePayments = DB::table('sale_payments')
                ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
                ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
                ->where('payment_methods.is_cash', true)
                ->where('sales.cashier_shift_id', $shift->id)
                ->select('sale_payments.*', 'sales.number as sale_number')
                ->get();

            foreach ($salePayments as $payment) {
                if ($payment->source_type === 'collection') {
                    continue;
                }

                $events[] = [
                    'key' => 'sale-payment:'.$payment->id,
                    'movement_type' => 'cash_sale',
                    'direction' => 'inflow',
                    'amount' => Decimal::normalize((string) $payment->applied_amount, 2),
                    'source_type' => 'sale_payment',
                    'source_id' => (int) $payment->id,
                    'reference_number' => $payment->sale_number,
                    'reason' => 'Cash sale payment backfill.',
                    'occurred_at' => $payment->paid_at,
                    'actor_user_id' => $payment->recorded_by_user_id,
                ];
            }

            foreach ($this->matchingEventsForShift('customer_collections', 'collected_at', $shift) as $collection) {
                $isCash = (bool) DB::table('payment_methods')
                    ->where('id', $collection->payment_method_id)
                    ->value('is_cash');

                if ($isCash) {
                    $events[] = [
                        'key' => 'customer-collection:'.$collection->id,
                        'movement_type' => 'customer_collection',
                        'direction' => 'inflow',
                        'amount' => Decimal::normalize((string) $collection->amount, 2),
                        'source_type' => 'customer_collection',
                        'source_id' => (int) $collection->id,
                        'reference_number' => $collection->number,
                        'reason' => 'Cash customer collection backfill.',
                        'occurred_at' => $collection->collected_at,
                        'actor_user_id' => $collection->recorded_by_user_id,
                    ];
                }
            }

            foreach ($this->matchingEventsForShift('purchase_payments', 'paid_at', $shift) as $payment) {
                if ($payment->method === 'cash') {
                    $events[] = [
                        'key' => 'purchase-payment:'.$payment->id,
                        'movement_type' => 'purchase_payment',
                        'direction' => 'outflow',
                        'amount' => Decimal::normalize((string) $payment->amount, 2),
                        'source_type' => 'purchase_payment',
                        'source_id' => (int) $payment->id,
                        'reference_number' => null,
                        'reason' => 'Initial cash purchase payment backfill.',
                        'occurred_at' => $payment->paid_at,
                        'actor_user_id' => $payment->recorded_by_user_id,
                    ];
                }
            }

            foreach ($this->matchingEventsForShift('supplier_payments', 'paid_at', $shift) as $payment) {
                if ($payment->method === 'cash') {
                    $events[] = [
                        'key' => 'supplier-payment:'.$payment->id,
                        'movement_type' => 'supplier_payment',
                        'direction' => 'outflow',
                        'amount' => Decimal::normalize((string) $payment->amount, 2),
                        'source_type' => 'supplier_payment',
                        'source_id' => (int) $payment->id,
                        'reference_number' => $payment->number,
                        'reason' => 'Cash supplier payment backfill.',
                        'occurred_at' => $payment->paid_at,
                        'actor_user_id' => $payment->recorded_by_user_id,
                    ];
                }
            }

            $refunds = DB::table('sale_refunds')
                ->join('payment_methods', 'payment_methods.id', '=', 'sale_refunds.payment_method_id')
                ->where('payment_methods.is_cash', true)
                ->where('sale_refunds.recorded_by_user_id', $shift->user_id)
                ->where('sale_refunds.refunded_at', '>=', $shift->opened_at)
                ->when($shift->closed_at, fn ($query) => $query->where('sale_refunds.refunded_at', '<=', $shift->closed_at))
                ->select('sale_refunds.*')
                ->get();

            foreach ($refunds as $refund) {
                $events[] = [
                    'key' => 'sale-refund:'.$refund->id,
                    'movement_type' => 'sale_refund',
                    'direction' => 'outflow',
                    'amount' => Decimal::normalize((string) $refund->amount, 2),
                    'source_type' => 'sale_refund',
                    'source_id' => (int) $refund->id,
                    'reference_number' => null,
                    'reason' => 'Cash sale refund backfill.',
                    'occurred_at' => $refund->refunded_at,
                    'actor_user_id' => $refund->recorded_by_user_id,
                ];
            }

            usort($events, fn (array $a, array $b) =>
                strcmp((string) $a['occurred_at'], (string) $b['occurred_at'])
                ?: strcmp($a['key'], $b['key'])
            );

            $expected = '0.00';

            foreach ($events as $event) {
                $expected = $event['direction'] === 'inflow'
                    ? Decimal::add($expected, $event['amount'], 2)
                    : Decimal::subtract($expected, $event['amount'], 2);

                DB::table('cash_movements')->insertOrIgnore([
                    'idempotency_key' => $event['key'],
                    'cashier_shift_id' => $shift->id,
                    'terminal_id' => $shift->terminal_id,
                    'actor_user_id' => $event['actor_user_id'],
                    'movement_type' => $event['movement_type'],
                    'direction' => $event['direction'],
                    'amount' => $event['amount'],
                    'expected_cash_after' => $expected,
                    'source_type' => $event['source_type'],
                    'source_id' => $event['source_id'],
                    'reference_number' => $event['reference_number'],
                    'reason' => $event['reason'],
                    'occurred_at' => $event['occurred_at'],
                    'created_at' => now(),
                ]);
            }

            DB::table('cashier_shifts')
                ->where('id', $shift->id)
                ->update(['expected_cash' => $expected]);
        }
    }

    private function matchingEventsForShift(string $table, string $timeColumn, object $shift)
    {
        return DB::table($table)
            ->where('recorded_by_user_id', $shift->user_id)
            ->where($timeColumn, '>=', $shift->opened_at)
            ->when($shift->closed_at, fn ($query) => $query->where($timeColumn, '<=', $shift->closed_at))
            ->get();
    }
};
