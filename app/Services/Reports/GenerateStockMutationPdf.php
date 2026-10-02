<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;

class GenerateStockMutationPdf
{
    /** @param array<string, mixed> $report */
    public function generate(array $report): GeneratedReportFile
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('pdf.reports.stock-mutations', ['report' => $report])->render(), 'UTF-8');
        $dompdf->render();

        $product = $report['product'] ?? null;
        $period = $report['period'];
        $filename = $product !== null
            ? sprintf('mutasi-%s-%s-sampai-%s.pdf', $product['sku'], $period['start_date'], $period['end_date'])
            : sprintf('laporan-stok-%s-sampai-%s.pdf', $period['start_date'], $period['end_date']);

        return new GeneratedReportFile($dompdf->output(), $filename, 'application/pdf');
    }
}
