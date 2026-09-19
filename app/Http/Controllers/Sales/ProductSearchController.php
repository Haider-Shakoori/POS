<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    public function __invoke(Request $request, ProductSearchService $search): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'max:191'],
        ]);

        return response()->json([
            'data' => $search->search((string) $request->input('q')),
        ]);
    }
}
