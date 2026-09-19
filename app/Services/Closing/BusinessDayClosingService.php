<?php

namespace App\Services\Closing;

use App\Enums\BusinessDayStatus;
use App\Enums\ShiftStatus;
use App\Models\BusinessDayClosure;
use App\Models\CashierShift;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class BusinessDayClosingService
{
    public function __construct(
        private readonly BusinessDayService $days,
        private readonly BusinessDaySummaryService $summaries,
        private readonly AuditLogger $audit,
    ) {
    }

    public function close(string $date, array $data, User $actor): BusinessDayClosure
    {
        if (! $actor->hasPermission('business_days.close')) {
            throw new DomainException('The user is not allowed to close business days.');
        }

        $date = CarbonImmutable::parse($date)->format('Y-m-d');

        return DB::transaction(function () use ($date, $data, $actor): BusinessDayClosure {
            if ($existing = BusinessDayClosure::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first()) {
                if ($existing->businessDay->business_date->format('Y-m-d') !== $date) {
                    throw new DomainException('The daily-close idempotency key belongs to another business day.');
                }

                if (trim((string) ($existing->notes ?? '')) !== trim((string) ($data['notes'] ?? ''))) {
                    throw new DomainException('The daily-close idempotency key is already bound to another closing payload.');
                }

                return $existing->load(['businessDay', 'closedBy']);
            }

            $day = $this->days->lock($date);

            if ($day->status !== BusinessDayStatus::Open) {
                throw new DomainException('The business day is already closed.');
            }

            $shifts = CashierShift::query()
                ->whereDate('business_date', $date)
                ->lockForUpdate()
                ->get();

            if ($shifts->contains(fn (CashierShift $shift) => $shift->status === ShiftStatus::Open)) {
                throw new DomainException('All cashier shifts for this business day must be closed first.');
            }

            if ($shifts->contains(fn (CashierShift $shift) => $shift->actual_cash === null || $shift->variance === null)) {
                throw new DomainException('Every closed cashier shift must contain an actual cash count and variance.');
            }

            $summary = $this->summaries->summarize($date);

            if (Decimal::compare($summary['expected_cash_total'], $summary['ledger_expected_cash_total']) !== 0) {
                throw new DomainException('Cash reconciliation failed: shift expected cash does not match the cash movement ledger.');
            }

            $derivedVariance = Decimal::subtract(
                $summary['actual_cash_total'],
                $summary['expected_cash_total'],
                2,
            );

            if (Decimal::compare($summary['variance_total'], $derivedVariance) !== 0) {
                throw new DomainException('Cash reconciliation failed: shift variance does not match actual minus expected cash.');
            }

            $version = (int) BusinessDayClosure::query()
                ->where('business_day_id', $day->id)
                ->max('version') + 1;

            $number = sprintf(
                'DAY-%s-R%02d',
                str_replace('-', '', $date),
                $version,
            );

            $closure = BusinessDayClosure::create([
                'business_day_id' => $day->id,
                'idempotency_key' => $data['idempotency_key'],
                'number' => $number,
                'version' => $version,
                'closed_by_user_id' => $actor->id,
                'shift_count' => $summary['shift_count'],
                'sales_count' => $summary['sales_count'],
                'sales_subtotal' => $summary['sales_subtotal'],
                'sales_line_discount_total' => $summary['sales_line_discount_total'],
                'sales_discount_total' => $summary['sales_discount_total'],
                'sales_net_total' => $summary['sales_net_total'],
                'sales_return_total' => $summary['sales_return_total'],
                'net_sales_total' => $summary['net_sales_total'],
                'sales_cogs_total' => $summary['sales_cogs_total'],
                'cogs_reversed_total' => $summary['cogs_reversed_total'],
                'net_cogs_total' => $summary['net_cogs_total'],
                'gross_profit_total' => $summary['gross_profit_total'],
                'customer_collections_total' => $summary['customer_collections_total'],
                'purchases_total' => $summary['purchases_total'],
                'purchase_returns_total' => $summary['purchase_returns_total'],
                'supplier_payments_total' => $summary['supplier_payments_total'],
                'operating_expenses_total' => $summary['operating_expenses_total'],
                'other_income_total' => $summary['other_income_total'],
                'net_profit_total' => $summary['net_profit_total'],
                'opening_cash_total' => $summary['opening_cash_total'],
                'cash_inflow_total' => $summary['cash_inflow_total'],
                'cash_outflow_total' => $summary['cash_outflow_total'],
                'expected_cash_total' => $summary['expected_cash_total'],
                'actual_cash_total' => $summary['actual_cash_total'],
                'variance_total' => $summary['variance_total'],
                'cash_breakdown' => $summary['cash_breakdown'],
                'notes' => $data['notes'] ?? null,
                'closed_at' => now(),
                'created_at' => now(),
            ]);

            $day->forceFill([
                'status' => BusinessDayStatus::Closed,
                'closed_at' => $closure->closed_at,
                'closed_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record(
                'closing.business_day.closed',
                model: $day,
                newValues: [
                    'closure_id' => $closure->id,
                    'number' => $closure->number,
                    'version' => $version,
                    'shift_count' => $closure->shift_count,
                    'expected_cash_total' => $closure->expected_cash_total,
                    'actual_cash_total' => $closure->actual_cash_total,
                    'variance_total' => $closure->variance_total,
                    'net_profit_total' => $closure->net_profit_total,
                ],
                actor: $actor,
            );

            return $closure->fresh(['businessDay', 'closedBy']);
        }, 3);
    }
}
