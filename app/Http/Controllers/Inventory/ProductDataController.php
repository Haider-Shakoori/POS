<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ImportProductsRequest;
use App\Services\Catalog\ProductCsvService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductDataController extends Controller
{
    public function importForm(): View
    {
        return view('inventory.products.import', [
            'headers' => ProductCsvService::HEADERS,
        ]);
    }

    public function template(ProductCsvService $csv): StreamedResponse
    {
        return response()->streamDownload(function () use ($csv): void {
            $out = fopen('php://output', 'wb');
            $csv->writeTemplate($out);
            fclose($out);
        }, 'product-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function export(ProductCsvService $csv): StreamedResponse
    {
        return response()->streamDownload(function () use ($csv): void {
            $out = fopen('php://output', 'wb');
            $csv->writeExport($out);
            fclose($out);
        }, 'products-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(ImportProductsRequest $request, ProductCsvService $csv): RedirectResponse
    {
        $count = $csv->import($request->file('file')->getRealPath(), $request->user());

        return redirect()
            ->route('inventory.products.index')
            ->with('status', __('ui.products_imported', ['count' => $count]));
    }
}
