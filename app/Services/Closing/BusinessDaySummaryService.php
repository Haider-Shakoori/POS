<?php

namespace App\Services\Closing;

use App\Enums\ShiftStatus;
use App\Models\CashierShift;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BusinessDaySummaryService
{
    public function summarize(string $date): array
    {
        $date = CarbonImmutable::parse($date)->format('Y-m-d');
        $start = CarbonImmutable::parse($date)->startOfDay();
        $end = CarbonImmutable::parse($date)->endOfDay();

        $shifts = CashierShift::query()
            ->whereDate('business_date', $date)
            ->get();

        $shiftCount = $shifts->count();
        $openShiftCount = $shifts->where('status', ShiftStatus::Open)->count();

        $expectedCash = $this->sumCollection($shifts->pluck('expected_cash')->filter());
        $actualCash = $this->sumCollection($shifts->pluck('actual_cash')->filter());
        $variance = $this->sumCollection($shifts->pluck('variance')->filter());

        $sales = DB::table('sales')
            ->whereBetween('sold_at', [$start, $end])
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as sales_subtotal')
            ->selectRaw('COALESCE(SUM(line_discount_total), 0) as sales_line_discount_total')
            ->selectRaw('COALESCE(SUM(sale_discount_amount), 0) as sales_discount_total')
            ->selectRaw('COALESCE(SUM(net_total), 0) as sales_net_total')
            ->selectRaw('COALESCE(SUM(cogs_total), 0) as sales_cogs_total')
            ->first();

        $returns = DB::table('sale_returns')
            ->whereBetween('posted_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(return_total), 0) as sales_return_total')
            ->selectRaw('COALESCE(SUM(cogs_reversed), 0) as cogs_reversed_total')
            ->first();

        $customerCollections = DB::table('customer_collections')
            ->whereBetween('collected_at', [$start, $end])
            ->sum('amount');

        $purchases = DB::table('goods_receipts')
            ->whereBetween('received_at', [$start, $end])
            ->sum('net_total');

        $purchaseReturns = DB::table('purchase_returns')
            ->whereBetween('posted_at', [$start, $end])
            ->sum('return_total');

        $initialSupplierPayments = DB::table('purchase_payments')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $laterSupplierPayments = DB::table('supplier_payments')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $operatingExpenses = DB::table('operating_entries')
            ->where('entry_type', 'expense')
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');

        $otherIncome = DB::table('operating_entries')
            ->where('entry_type', 'income')
            ->whereBetween('occurred_at', [$start, $end])
            ->sum('amount');

        $cashRows = DB::table('cash_movements')
            ->join('cashier_shifts', 'cashier_shifts.id', '=', 'cash_movements.cashier_shift_id')
            ->whereDate('cashier_shifts.business_date', $date)
            ->select(
                'cash_movements.movement_type',
                'cash_movements.direction',
                DB::raw('SUM(cash_movements.amount) as amount')
            )
            ->groupBy('cash_movements.movement_type', 'cash_movements.direction')
            ->get();

        $openingCash = '0.00';
        $cashInflow = '0.00';
        $cashOutflow = '0.00';
        $cashBreakdown = [];

        foreach ($cashRows as $row) {
            $amount = Decimal::normalize((string) $row->amount, 2);
            $cashBreakdown[] = [
                'movement_type' => $row->movement_type,
                'direction' => $row->direction,
                'amount' => $amount,
            ];

            if ($row->movement_type === 'opening_float') {
                $openingCash = Decimal::add($openingCash, $amount, 2);
                continue;
            }

            if ($row->direction === 'inflow') {
                $cashInflow = Decimal::add($cashInflow, $amount, 2);
            } else {
                $cashOutflow = Decimal::add($cashOutflow, $amount, 2);
            }
        }

        $salesSubtotal = Decimal::normalize((string) ($sales->sales_subtotal ?? 0), 2);
        $lineDiscount = Decimal::normalize((string) ($sales->sales_line_discount_total ?? 0), 2);
        $saleDiscount = Decimal::normalize((string) ($sales->sales_discount_total ?? 0), 2);
        $salesNet = Decimal::normalize((string) ($sales->sales_net_total ?? 0), 2);
        $salesReturn = Decimal::normalize((string) ($returns->sales_return_total ?? 0), 2);
        $netSales = Decimal::subtract($salesNet, $salesReturn, 2);

        $salesCogs = Decimal::normalize((string) ($sales->sales_cogs_total ?? 0), 2);
        $cogsReversed = Decimal::normalize((string) ($returns->cogs_reversed_total ?? 0), 2);
        $netCogs = Decimal::subtract($salesCogs, $cogsReversed, 2);
        $grossProfit = Decimal::subtract($netSales, $netCogs, 2);

        $operatingExpenses = Decimal::normalize((string) $operatingExpenses, 2);
        $otherIncome = Decimal::normalize((string) $otherIncome, 2);
        $netProfit = Decimal::subtract(
            Decimal::add($grossProfit, $otherIncome, 2),
            $operatingExpenses,
            2,
        );

        $ledgerExpected = Decimal::subtract(
            Decimal::add($openingCash, $cashInflow, 2),
            $cashOutflow,
            2,
        );

        return [
            'business_date' => $date,
            'shift_count' => $shiftCount,
            'open_shift_count' => $openShiftCount,
            'sales_count' => (int) ($sales->sales_count ?? 0),
            'sales_subtotal' => $salesSubtotal,
            'sales_line_discount_total' => $lineDiscount,
            'sales_discount_total' => $saleDiscount,
            'sales_net_total' => $salesNet,
            'sales_return_total' => $salesReturn,
            'net_sales_total' => $netSales,
            'sales_cogs_total' => $salesCogs,
            'cogs_reversed_total' => $cogsReversed,
            'net_cogs_total' => $netCogs,
            'gross_profit_total' => $grossProfit,
            'customer_collections_total' => Decimal::normalize((string) $customerCollections, 2),
            'purchases_total' => Decimal::normalize((string) $purchases, 2),
            'purchase_returns_total' => Decimal::normalize((string) $purchaseReturns, 2),
            'supplier_payments_total' => Decimal::add(
                Decimal::normalize((string) $initialSupplierPayments, 2),
                Decimal::normalize((string) $laterSupplierPayments, 2),
                2,
            ),
            'operating_expenses_total' => $operatingExpenses,
            'other_income_total' => $otherIncome,
            'net_profit_total' => $netProfit,
            'opening_cash_total' => $openingCash,
            'cash_inflow_total' => $cashInflow,
            'cash_outflow_total' => $cashOutflow,
            'expected_cash_total' => $expectedCash,
            'actual_cash_total' => $actualCash,
            'variance_total' => $variance,
            'ledger_expected_cash_total' => $ledgerExpected,
            'cash_breakdown' => $cashBreakdown,
        ];
    }

    private function sumCollection(iterable $values): string
    {
        $total = '0.00';

        foreach ($values as $value) {
            $total = Decimal::add($total, Decimal::normalize((string) $value, 2), 2);
        }

        return $total;
    }
}
