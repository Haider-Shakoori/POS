<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Lookups\ReportLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportLookupController extends Controller
{
    public function __invoke(
        Request $request,
        string $type,
        ReportLookupService $lookups,
    ): JsonResponse {
        $request->validate([
            'q' => ['nullable', 'string', 'max:191'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        validator(
            ['type' => $type],
            ['type' => ['required', Rule::in(ReportLookupService::TYPES)]],
        )->validate();

        return response()->json([
            'data' => $lookups->search(
                $type,
                (string) $request->input('q', ''),
                $request->integer('limit', 15),
            ),
        ]);
    }
}
