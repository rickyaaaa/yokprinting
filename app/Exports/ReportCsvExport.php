<?php

namespace App\Exports;

use Illuminate\Http\Response;

class ReportCsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows): Response
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_map($this->sanitizeCell(...), $headers));

        foreach ($rows as $row) {
            fputcsv($handle, array_map($this->sanitizeCell(...), $row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\u{FEFF}".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Prevent spreadsheet applications from evaluating user-controlled cells as formulas.
     */
    private function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}
