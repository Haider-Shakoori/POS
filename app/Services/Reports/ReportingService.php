<?php

namespace App\Services\Reports;

use App\Models\BusinessDayClosure;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryCostLayer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Supplier;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public function build(array $filters): array
    {
        [$from, $to] = $this->range($filters);

        return [
            'filters' => [
                ...$filters,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => $this->financialSummary($from, $to, $filters),
            'salesTrend' => $this->salesTrend($from, $to),
            'topProducts' => $this->productPerformance($from, $to, $filters, true),
            'slowProducts' => $this->productPerformance($from, $to, $filters, false),
            'categoryProfit' => $this->categoryProfit($from, $to, $filters),
            'customers' => $this->customerSummary($from, $to, $filters),
            'suppliers' => $this->supplierSummary($from, $to, $filters),
            'inventory' => $this->inventorySummary(),
            'expiring' => $this->expirySummary(),
            'closingHistory' => $this->closingHistory($from, $to),
            'peakHours' => $this->peakHours($from, $to),
            'weekdays' => $this->weekdayPerformance($from, $to),
        ];
    }

    public function salesCsv(array $filters): Collection
    {
        [$from, $to] = $this->range($filters);

        $query = DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereBetween('sales.sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->select(
                'sales.number',
                'sales.sold_at',
                'sales.customer_name_snapshot',
                'customers.name as customer_name',
                'sales.subtotal',
                'sales.line_discount_total',
                'sales.sale_discount_amount',
                'sales.net_total',
                'sales.returned_total',
                'sales.cogs_total',
                'sales.gross_profit',
                'sales.paid_amount',
                'sales.balance_due'
            )
            ->orderBy('sales.sold_at')
            ->orderBy('sales.id');

        if (! empty($filters['customer_id'])) {
            $query->where('sales.customer_id', (int) $filters['customer_id']);
        }

        return $query->get();
    }

    private function financialSummary(CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $salesQuery = DB::table('sales')
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()]);

        if (! empty($filters['customer_id'])) {
            $salesQuery->where('customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['product_id']) || ! empty($filters['category_id'])) {
            $salesQuery->whereExists(function (Builder $query) use ($filters): void {
                $query->selectRaw('1')
                    ->from('sale_items')
                    ->join('products', 'products.id', '=', 'sale_items.product_id')
                    ->whereColumn('sale_items.sale_id', 'sales.id');

                if (! empty($filters['product_id'])) {
                    $query->where('sale_items.product_id', (int) $filters['product_id']);
                }

                if (! empty($filters['category_id'])) {
                    $query->where('products.category_id', (int) $filters['category_id']);
                }
            });
        }

        $sales = (clone $salesQuery)
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(subtotal),0) as subtotal')
            ->selectRaw('COALESCE(SUM(line_discount_total),0) as line_discount')
            ->selectRaw('COALESCE(SUM(sale_discount_amount),0) as sale_discount')
            ->selectRaw('COALESCE(SUM(net_total),0) as net_total')
            ->selectRaw('COALESCE(SUM(cogs_total),0) as cogs')
            ->first();

        $saleIds = (clone $salesQuery)->pluck('id');

        $returns = DB::table('sale_returns')
            ->whereIn('sale_id', $saleIds)
            ->whereBetween('posted_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('COALESCE(SUM(return_total),0) as return_total')
            ->selectRaw('COALESCE(SUM(cogs_reversed),0) as cogs_reversed')
            ->first();

        $salesNet = Decimal::normalize((string) ($sales->net_total ?? 0), 2);
        $returnTotal = Decimal::normalize((string) ($returns->return_total ?? 0), 2);
        $netSales = Decimal::subtract($salesNet, $returnTotal, 2);

        $salesCogs = Decimal::normalize((string) ($sales->cogs ?? 0), 2);
        $cogsReversed = Decimal::normalize((string) ($returns->cogs_reversed ?? 0), 2);
        $netCogs = Decimal::subtract($salesCogs, $cogsReversed, 2);
        $grossProfit = Decimal::subtract($netSales, $netCogs, 2);

        $expenses = DB::table('operating_entries')
            ->where('entry_type', 'expense')
            ->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');

        $otherIncome = DB::table('operating_entries')
            ->where('entry_type', 'income')
            ->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');

        $expenses = Decimal::normalize((string) $expenses, 2);
        $otherIncome = Decimal::normalize((string) $otherIncome, 2);
        $netProfit = Decimal::subtract(Decimal::add($grossProfit, $otherIncome, 2), $expenses, 2);

        $purchases = DB::table('goods_receipts')
            ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', (int) $filters['supplier_id']))
            ->whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('net_total');

        $purchaseReturns = DB::table('purchase_returns')
            ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', (int) $filters['supplier_id']))
            ->whereBetween('posted_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('return_total');

        $collections = DB::table('customer_collections')
            ->when(! empty($filters['customer_id']), fn ($q) => $q->where('customer_id', (int) $filters['customer_id']))
            ->whereBetween('collected_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');

        $supplierPayments = Decimal::add(
            Decimal::normalize((string) DB::table('purchase_payments')
                ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', (int) $filters['supplier_id']))
                ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount'), 2),
            Decimal::normalize((string) DB::table('supplier_payments')
                ->when(! empty($filters['supplier_id']), fn ($q) => $q->where('supplier_id', (int) $filters['supplier_id']))
                ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('amount'), 2),
            2,
        );

        $count = (int) ($sales->count ?? 0);
        $aov = $count > 0 ? Decimal::divide($netSales, (string) $count, 2) : '0.00';

        return [
            'sales_count' => $count,
            'subtotal' => Decimal::normalize((string) ($sales->subtotal ?? 0), 2),
            'discounts' => Decimal::add(
                Decimal::normalize((string) ($sales->line_discount ?? 0), 2),
                Decimal::normalize((string) ($sales->sale_discount ?? 0), 2),
                2,
            ),
            'sales_net' => $salesNet,
            'returns' => $returnTotal,
            'net_sales' => $netSales,
            'sales_cogs' => $salesCogs,
            'cogs_reversed' => $cogsReversed,
            'net_cogs' => $netCogs,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'other_income' => $otherIncome,
            'net_profit' => $netProfit,
            'purchases' => Decimal::normalize((string) $purchases, 2),
            'purchase_returns' => Decimal::normalize((string) $purchaseReturns, 2),
            'collections' => Decimal::normalize((string) $collections, 2),
            'supplier_payments' => $supplierPayments,
            'aov' => $aov,
            'receivables' => Decimal::normalize((string) Customer::query()->sum('current_balance'), 2),
            'payables' => Decimal::normalize((string) Supplier::query()->sum('current_balance'), 2),
            'inventory_value' => $this->inventoryValue(),
            'damaged_cost' => Decimal::normalize((string) DB::table('inventory_writeoffs')
                ->where('writeoff_type', 'damage')
                ->whereBetween('posted_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('total_cost'), 2),
            'expired_cost' => Decimal::normalize((string) DB::table('inventory_writeoffs')
                ->where('writeoff_type', 'expiry')
                ->whereBetween('posted_at', [$from->startOfDay(), $to->endOfDay()])
                ->sum('total_cost'), 2),
        ];
    }

    private function salesTrend(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('sales')
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(sold_at) as day')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(net_total),0) as net_total')
            ->selectRaw('COALESCE(SUM(gross_profit),0) as gross_profit')
            ->groupByRaw('DATE(sold_at)')
            ->orderBy('day')
            ->get();
    }

    private function productPerformance(CarbonImmutable $from, CarbonImmutable $to, array $filters, bool $descending): Collection
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->select(
                'products.id',
                'products.sku',
                'products.name_en',
                'products.name_fa',
                'products.name_ps',
                'categories.name_en as category_name_en',
                'categories.name_fa as category_name_fa',
                'categories.name_ps as category_name_ps',
            )
            ->selectRaw('SUM(sale_items.quantity_base) as quantity_base')
            ->selectRaw('SUM(sale_items.line_net_total) as net_sales')
            ->selectRaw('SUM(sale_items.cogs_amount) as cogs')
            ->selectRaw('SUM(sale_items.gross_profit) as gross_profit')
            ->groupBy(
                'products.id','products.sku','products.name_en','products.name_fa','products.name_ps',
                'categories.name_en','categories.name_fa','categories.name_ps'
            );

        if (! empty($filters['product_id'])) {
            $query->where('products.id', (int) $filters['product_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', (int) $filters['category_id']);
        }

        return $query
            ->orderBy('quantity_base', $descending ? 'desc' : 'asc')
            ->limit(10)
            ->get();
    }

    private function categoryProfit(CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->select(
                'categories.id',
                DB::raw("COALESCE(categories.name_en, 'Uncategorized') as name_en"),
                'categories.name_fa',
                'categories.name_ps'
            )
            ->selectRaw('SUM(sale_items.line_net_total) as net_sales')
            ->selectRaw('SUM(sale_items.cogs_amount) as cogs')
            ->selectRaw('SUM(sale_items.gross_profit) as gross_profit')
            ->groupBy('categories.id','categories.name_en','categories.name_fa','categories.name_ps')
            ->orderByDesc('gross_profit');

        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', (int) $filters['category_id']);
        }

        return $query->limit(20)->get();
    }

    private function customerSummary(CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $query = DB::table('customers')
            ->leftJoin('sales', function ($join) use ($from, $to): void {
                $join->on('sales.customer_id', '=', 'customers.id')
                    ->whereBetween('sales.sold_at', [$from->startOfDay(), $to->endOfDay()]);
            })
            ->select('customers.id','customers.name','customers.phone','customers.current_balance')
            ->selectRaw('COUNT(sales.id) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.net_total),0) as sales_total')
            ->groupBy('customers.id','customers.name','customers.phone','customers.current_balance')
            ->orderByDesc('sales_total');

        if (! empty($filters['customer_id'])) {
            $query->where('customers.id', (int) $filters['customer_id']);
        }

        return $query->limit(20)->get();
    }

    private function supplierSummary(CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $query = DB::table('suppliers')
            ->leftJoin('goods_receipts', function ($join) use ($from, $to): void {
                $join->on('goods_receipts.supplier_id', '=', 'suppliers.id')
                    ->whereBetween('goods_receipts.received_at', [$from->startOfDay(), $to->endOfDay()]);
            })
            ->select('suppliers.id','suppliers.name','suppliers.phone','suppliers.current_balance')
            ->selectRaw('COUNT(goods_receipts.id) as receipt_count')
            ->selectRaw('COALESCE(SUM(goods_receipts.net_total),0) as purchases_total')
            ->groupBy('suppliers.id','suppliers.name','suppliers.phone','suppliers.current_balance')
            ->orderByDesc('purchases_total');

        if (! empty($filters['supplier_id'])) {
            $query->where('suppliers.id', (int) $filters['supplier_id']);
        }

        return $query->limit(20)->get();
    }

    private function inventorySummary(): array
    {
        $low = Product::query()
            ->where('track_stock', true)
            ->whereColumn('stock_on_hand', '<=', 'minimum_stock')
            ->count();

        $out = Product::query()
            ->where('track_stock', true)
            ->where('stock_on_hand', '<=', 0)
            ->count();

        return [
            'tracked_products' => Product::query()->where('track_stock', true)->count(),
            'low_stock' => $low,
            'out_of_stock' => $out,
            'expired_batches' => ProductBatch::query()
                ->where('stock_on_hand', '>', 0)
                ->whereDate('expires_at', '<', today())
                ->count(),
            'expiring_batches' => ProductBatch::query()
                ->where('stock_on_hand', '>', 0)
                ->whereDate('expires_at', '>=', today())
                ->whereDate('expires_at', '<=', today()->addDays(30))
                ->count(),
        ];
    }

    private function expirySummary(): Collection
    {
        return ProductBatch::query()
            ->with('product.baseUnit')
            ->where('stock_on_hand', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', today()->addDays(30))
            ->orderBy('expires_at')
            ->limit(20)
            ->get();
    }

    private function closingHistory(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return BusinessDayClosure::query()
            ->with('businessDay')
            ->whereHas('businessDay', fn ($q) => $q->whereBetween('business_date', [$from->toDateString(), $to->toDateString()]))
            ->orderByDesc('closed_at')
            ->limit(30)
            ->get();
    }

    private function peakHours(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('sales')
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('HOUR(sold_at) as hour')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(net_total) as net_total')
            ->groupByRaw('HOUR(sold_at)')
            ->orderByDesc('sales_count')
            ->limit(24)
            ->get();
    }

    private function weekdayPerformance(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return DB::table('sales')
            ->whereBetween('sold_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('WEEKDAY(sold_at) as weekday_index')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('SUM(net_total) as net_total')
            ->groupByRaw('WEEKDAY(sold_at)')
            ->orderBy('weekday_index')
            ->get();
    }

    private function inventoryValue(): string
    {
        $total = '0.00';

        foreach (
            InventoryCostLayer::query()
                ->where('remaining_quantity_base', '>', 0)
                ->select('remaining_quantity_base', 'unit_cost_base')
                ->cursor() as $layer
        ) {
            $total = Decimal::add(
                $total,
                Decimal::multiplyRounded(
                    $layer->remaining_quantity_base,
                    $layer->unit_cost_base,
                    2,
                ),
                2,
            );
        }

        return $total;
    }

    private function range(array $filters): array
    {
        $to = ! empty($filters['to'])
            ? CarbonImmutable::parse($filters['to'])
            : now()->toImmutable();

        $from = ! empty($filters['from'])
            ? CarbonImmutable::parse($filters['from'])
            : $to->subDays(29);

        return [$from, $to];
    }
}
