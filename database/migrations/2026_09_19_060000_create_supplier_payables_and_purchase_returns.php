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
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('current_balance', 18, 2)->default(0)->after('opening_balance')->index();
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->decimal('returned_total', 18, 2)->default(0)->after('balance_due');
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('method', 30);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'paid_at'], 'supplier_payment_time_idx');
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'goods_receipt_id'], 'supplier_payment_receipt_unique');
        });

        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 40)->index();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->decimal('balance_after', 18, 2);
            $table->string('reference_type', 80);
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_number', 80)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'occurred_at'], 'supplier_ledger_time_idx');
            $table->index(['reference_type', 'reference_id'], 'supplier_ledger_reference_idx');
            $table->unique(
                ['supplier_id', 'entry_type', 'reference_type', 'reference_id'],
                'supplier_ledger_unique_ref'
            );
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->decimal('return_total', 18, 2);
            $table->timestamp('posted_at')->index();
            $table->timestamps();

            $table->index(['supplier_id', 'posted_at'], 'purchase_return_supplier_time_idx');
            $table->index(['goods_receipt_id', 'posted_at'], 'purchase_return_receipt_time_idx');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_cost_layer_id')->constrained('inventory_cost_layers')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->decimal('quantity_base', 20, 6);
            $table->decimal('return_amount', 18, 2);
            $table->decimal('unit_cost_base', 18, 4);
            $table->decimal('cost_amount', 20, 4);
            $table->timestamps();

            $table->unique(['purchase_return_id', 'goods_receipt_item_id'], 'purchase_return_item_unique');
            $table->index(['goods_receipt_item_id', 'quantity_base'], 'purchase_return_receipt_item_idx');
        });

        $this->backfillSupplierLedger();
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_ledger_entries');
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn('returned_total');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['current_balance']);
            $table->dropColumn('current_balance');
        });
    }

    private function backfillSupplierLedger(): void
    {
        $suppliers = DB::table('suppliers')->orderBy('id')->get();

        foreach ($suppliers as $supplier) {
            $events = [];

            if (Decimal::isPositive((string) $supplier->opening_balance)) {
                $events[] = [
                    'time' => $supplier->created_at ?? now(),
                    'sort' => 0,
                    'id' => (int) $supplier->id,
                    'entry_type' => 'opening_balance',
                    'debit' => '0.00',
                    'credit' => Decimal::normalize((string) $supplier->opening_balance, 2),
                    'reference_type' => 'supplier',
                    'reference_id' => (int) $supplier->id,
                    'reference_number' => null,
                    'actor_user_id' => null,
                    'notes' => 'Opening supplier payable balance backfill.',
                ];
            }

            foreach (DB::table('goods_receipts')->where('supplier_id', $supplier->id)->get() as $receipt) {
                $events[] = [
                    'time' => $receipt->received_at,
                    'sort' => 1,
                    'id' => (int) $receipt->id,
                    'entry_type' => 'goods_receipt',
                    'debit' => '0.00',
                    'credit' => Decimal::normalize((string) $receipt->net_total, 2),
                    'reference_type' => 'goods_receipt',
                    'reference_id' => (int) $receipt->id,
                    'reference_number' => $receipt->number,
                    'actor_user_id' => $receipt->posted_by_user_id,
                    'notes' => 'Goods receipt payable backfill.',
                ];
            }

            foreach (DB::table('purchase_payments')->where('supplier_id', $supplier->id)->get() as $payment) {
                $events[] = [
                    'time' => $payment->paid_at,
                    'sort' => 2,
                    'id' => (int) $payment->id,
                    'entry_type' => 'initial_purchase_payment',
                    'debit' => Decimal::normalize((string) $payment->amount, 2),
                    'credit' => '0.00',
                    'reference_type' => 'purchase_payment',
                    'reference_id' => (int) $payment->id,
                    'reference_number' => null,
                    'actor_user_id' => $payment->recorded_by_user_id,
                    'notes' => 'Initial purchase payment backfill.',
                ];
            }

            usort($events, function (array $left, array $right): int {
                $time = strcmp((string) $left['time'], (string) $right['time']);

                if ($time !== 0) {
                    return $time;
                }

                $sort = $left['sort'] <=> $right['sort'];

                return $sort !== 0 ? $sort : ($left['id'] <=> $right['id']);
            });

            $balance = '0.00';

            foreach ($events as $event) {
                $balance = Decimal::subtract(
                    Decimal::add($balance, $event['credit'], 2),
                    $event['debit'],
                    2,
                );

                DB::table('supplier_ledger_entries')->insert([
                    'supplier_id' => $supplier->id,
                    'actor_user_id' => $event['actor_user_id'],
                    'entry_type' => $event['entry_type'],
                    'debit' => $event['debit'],
                    'credit' => $event['credit'],
                    'balance_after' => $balance,
                    'reference_type' => $event['reference_type'],
                    'reference_id' => $event['reference_id'],
                    'reference_number' => $event['reference_number'],
                    'occurred_at' => $event['time'],
                    'notes' => $event['notes'],
                    'created_at' => $event['time'],
                ]);
            }

            DB::table('suppliers')
                ->where('id', $supplier->id)
                ->update(['current_balance' => $balance]);
        }
    }
};
