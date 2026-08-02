<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitLossReportRequest;
use App\Services\Reports\GeneratedReportFile;
use App\Services\Reports\GenerateProfitLossPdf;
use App\Services\Reports\GenerateProfitLossSpreadsheet;
use App\Services\Reports\ProfitLossReport;
use App\Support\ProfitLossPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProfitLossReportController extends Controller
{
    public function show(ProfitLossReportRequest $request, ProfitLossReport $profitLossReport): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->report($request, $profitLossReport),
        ]);
    }

    public function pdf(
        ProfitLossReportRequest $request,
        ProfitLossReport $profitLossReport,
        GenerateProfitLossPdf $generator,
    ): Response {
        return $this->download($generator->generate($this->report($request, $profitLossReport)));
    }

    public function excel(
        ProfitLossReportRequest $request,
        ProfitLossReport $profitLossReport,
        GenerateProfitLossSpreadsheet $generator,
    ): Response {
        return $this->download($generator->generate($this->report($request, $profitLossReport)));
    }

    /** @return array<string, mixed> */
    private function report(ProfitLossReportRequest $request, ProfitLossReport $profitLossReport): array
    {
        $filters = $request->validated();

        return $profitLossReport->build(
            $filters['period'] ?? ProfitLossPeriod::MONTHLY,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
        );
    }

    private function download(GeneratedReportFile $file): Response
    {
        return response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => "attachment; filename=\"{$file->filename}\"",
            'Content-Length' => (string) strlen($file->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
