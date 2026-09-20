<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Lookups\InventoryTargetLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryTargetSearchController extends Controller
{
    public function __invoke(
        Request $request,
        InventoryTargetLookupService $lookups,
    ): JsonResponse {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:191'],
            'mode' => ['required', Rule::in(InventoryTargetLookupService::MODES)],
        ]);

        return response()->json([
            'data' => $lookups->search(
                (string) $validated['q'],
                (string) $validated['mode'],
            ),
        ]);
    }
}
