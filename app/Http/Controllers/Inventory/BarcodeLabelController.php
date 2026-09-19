<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BarcodeLabelRequest;
use App\Models\ProductBarcode;
use App\Support\Code128Svg;
use Illuminate\View\View;

class BarcodeLabelController extends Controller
{
    public function __invoke(
        BarcodeLabelRequest $request,
        ProductBarcode $productBarcode,
        Code128Svg $barcode,
    ): View {
        abort_unless($barcode->supports($productBarcode->barcode), 422, __('ui.barcode_label_unsupported'));

        $productBarcode->load(['product', 'productUnit.unit']);

        return view('inventory.barcodes.labels', [
            'barcode' => $productBarcode,
            'barcodeSvg' => $barcode->render($productBarcode->barcode),
            'quantity' => (int) ($request->validated('quantity') ?? 1),
            'autoprint' => (bool) ($request->validated('autoprint') ?? false),
        ]);
    }
}
