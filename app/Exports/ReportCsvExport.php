<?php

namespace App\Exports;

use Illuminate\Http\Response;

class ReportCsvExport
{
    /**
     * Excel picks its CSV delimiter from the machine's regional list
     * separator, which is ";" on Indonesian Windows - so a comma-delimited
     * file opened by double-clicking landed entirely in column A, quotes and
     * all. This one line tells Excel which delimiter the file actually uses;
     * Google Sheets and LibreOffice detect it and skip the line. The file
     * stays standards-compliant comma-delimited either way.
     */
    private const EXCEL_DELIMITER_HINT = "sep=,\r\n";

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

        // BOM first (Excel needs it before anything else to read UTF-8), then
        // the delimiter hint, then the data.
        return response("\u{FEFF}".self::EXCEL_DELIMITER_HINT.$csv, 200, [
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
