<?php

namespace App\Services\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;

class GenerateProfitLossPdf
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function generate(array $report): GeneratedReportFile
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4');
        $dompdf->loadHtml(view('pdf.reports.profit-loss', ['report' => $report])->render(), 'UTF-8');
        $dompdf->render();

        return new GeneratedReportFile(
            contents: $dompdf->output(),
            filename: $this->filename($report, 'pdf'),
            contentType: 'application/pdf',
        );
    }

    /** @param array<string, mixed> $report */
    private function filename(array $report, string $extension): string
    {
        return sprintf(
            'laporan-laba-rugi-%s-sampai-%s.%s',
            $report['period']['date_from'],
            $report['period']['date_to'],
            $extension,
        );
    }
}
