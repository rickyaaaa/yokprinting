<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListStockMovementReportRequest;
use App\Services\Reports\BuildStockMutationReport;
use App\Services\Reports\GeneratedReportFile;
use App\Services\Reports\GenerateStockMutationPdf;
use App\Services\Reports\GenerateStockMutationSpreadsheet;
use Illuminate\Http\Response;

class StockReportExportController extends Controller
{
    public function excel(
        ListStockMovementReportRequest $request,
        GenerateStockMutationSpreadsheet $spreadsheet,
        BuildStockMutationReport $report,
    ): Response {
        return $this->download($spreadsheet->generate($report->handle($request->validated())));
    }

    public function pdf(
        ListStockMovementReportRequest $request,
        GenerateStockMutationPdf $pdf,
        BuildStockMutationReport $report,
    ): Response {
        return $this->download($pdf->generate($report->handle($request->validated())));
    }

    private function download(GeneratedReportFile $file): Response
    {
        return response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => 'attachment; filename="'.$file->filename.'"',
            'Content-Length' => (string) strlen($file->contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
