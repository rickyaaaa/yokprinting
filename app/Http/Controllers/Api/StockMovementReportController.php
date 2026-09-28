<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListStockMovementReportRequest;
use App\Services\Reports\BuildStockMutationReport;
use Illuminate\Http\JsonResponse;

class StockMovementReportController extends Controller
{
    public function __invoke(ListStockMovementReportRequest $request, BuildStockMutationReport $report): JsonResponse
    {
        $data = $report->handle($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
