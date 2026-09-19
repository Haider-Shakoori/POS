<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Reports\ReportingService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        ReportFilterRequest $request,
        ReportingService $reports,
    ): View {
        return view('reports.index', [
            'report' => $reports->build($request->validated()),
            'products' => Product::query()->where('is_active', true)->orderBy('name_en')->get(['id','name_en','name_fa','name_ps']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name_en')->get(['id','name_en','name_fa','name_ps']),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(['id','name']),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id','name']),
            'canViewProfit' => $request->user()->hasPermission('reports.profit'),
        ]);
    }

    public function salesCsv(
        ReportFilterRequest $request,
        ReportingService $reports,
    ): StreamedResponse {
        $rows = $reports->salesCsv($request->validated());
        $canViewProfit = $request->user()->hasPermission('reports.profit');

        return response()->streamDownload(function () use ($rows, $canViewProfit): void {
            $out = fopen('php://output', 'wb');

            $headers = [
                'Sale Number','Sold At','Customer','Subtotal','Line Discount','Sale Discount',
                'Net Total','Returned Total',
            ];

            if ($canViewProfit) {
                $headers[] = 'COGS';
                $headers[] = 'Gross Profit';
            }

            $headers[] = 'Paid';
            $headers[] = 'Balance Due';
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                $values = [
                    $row->number,
                    $row->sold_at,
                    $row->customer_name ?: $row->customer_name_snapshot,
                    $row->subtotal,
                    $row->line_discount_total,
                    $row->sale_discount_amount,
                    $row->net_total,
                    $row->returned_total,
                ];

                if ($canViewProfit) {
                    $values[] = $row->cogs_total;
                    $values[] = $row->gross_profit;
                }

                $values[] = $row->paid_amount;
                $values[] = $row->balance_due;
                fputcsv($out, $values);
            }

            fclose($out);
        }, 'sales-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
