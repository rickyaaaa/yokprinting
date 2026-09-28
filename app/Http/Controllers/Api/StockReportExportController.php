<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListStockMovementReportRequest;
use App\Services\Reports\BuildStockMutationReport;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class StockReportExportController extends Controller
{
    public function csv(
        ListStockMovementReportRequest $request,
        ReportCsvExport $export,
        BuildStockMutationReport $report,
    ): Response {
        $filters = $request->validated();
        $data = $report->handle($filters);
        $start = CarbonImmutable::parse($data['period']['start_date']);
        $end = CarbonImmutable::parse($data['period']['end_date']);

        if ($data['product'] !== null) {
            $rows = collect($data['mutations'])->map(fn (array $row): array => [
                $row['document_number'] ?? '-',
                $row['date'] ?? '-',
                $row['description'],
                $row['party'] ?? '-',
                $row['incoming'],
                $row['outgoing'],
                $row['balance'],
            ])->all();

            return $export->download(
                "mutasi-{$data['product']['sku']}-{$start->toDateString()}-sampai-{$end->toDateString()}.csv",
                ['Nomor Dokumen', 'Tanggal', 'Deskripsi', 'Customer/Supplier', 'Masuk', 'Keluar', 'Saldo'],
                $rows,
            );
        }

        $rows = collect($data['products'])->map(fn (array $row): array => [
            $row['sku'],
            $row['name'],
            $row['category'] ?: '-',
            $row['unit'],
            $row['opening_balance'],
            $row['incoming_quantity'],
            $row['outgoing_quantity'],
            $row['adjustments'],
            $row['closing_balance'],
            $row['fifo_inventory_value'],
        ])->all();

        return $export->download(
            "laporan-stok-{$start->toDateString()}-sampai-{$end->toDateString()}.csv",
            ['SKU', 'Product Name', 'Category', 'Unit', 'Opening Stock', 'Purchase Qty', 'Sales Qty', 'Adjustment', 'Ending Stock', 'FIFO Inventory Value'],
            $rows,
        );
    }

    public function pdf(ListStockMovementReportRequest $request, BuildStockMutationReport $report): Response
    {
        $data = $report->handle($request->validated());
        $start = CarbonImmutable::parse($data['period']['start_date']);
        $end = CarbonImmutable::parse($data['period']['end_date']);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $view = $data['product'] !== null ? 'pdf.reports.stock-mutation-detail' : 'pdf.reports.stock';
        $dompdf->loadHtml(view($view, [
            'data' => $data,
            'rows' => $data['product'] === null ? collect($data['products'])->map(fn (array $row): array => [
                $row['sku'],
                $row['name'],
                $row['category'] ?: '-',
                $row['unit'],
                $row['opening_balance'],
                $row['incoming_quantity'],
                $row['outgoing_quantity'],
                $row['adjustments'],
                $row['closing_balance'],
                $row['fifo_inventory_value'],
            ])->all() : [],
            'start' => $start,
            'end' => $end,
        ])->render(), 'UTF-8');
        $dompdf->render();

        $filename = $data['product'] !== null
            ? "mutasi-{$data['product']['sku']}-{$start->toDateString()}-sampai-{$end->toDateString()}.pdf"
            : "laporan-stok-{$start->toDateString()}-sampai-{$end->toDateString()}.pdf";

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
