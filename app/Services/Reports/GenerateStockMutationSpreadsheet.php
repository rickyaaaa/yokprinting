<?php

namespace App\Services\Reports;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Generate the stock mutation report as a real XLSX workbook.
 *
 * The project intentionally does not depend on PhpSpreadsheet. The other
 * report exports therefore use a small OOXML writer, which is also used here
 * so the download is readable by Excel, LibreOffice, and Google Sheets.
 */
class GenerateStockMutationSpreadsheet
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private readonly TemporaryReportFileCleanup $temporaryFileCleanup) {}

    /**
     * @param  array<string, mixed>  $report
     */
    public function generate(array $report): GeneratedReportFile
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membuat export Excel.');
        }

        $temporaryDirectory = (string) config('reports.temporary_directory');
        File::ensureDirectoryExists($temporaryDirectory, 0700, true);
        $temporaryPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'mutasi-stok-'.Str::uuid().'.tmp';

        try {
            $archive = new ZipArchive;

            if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Workbook Excel tidak dapat dibuat.');
            }

            foreach ($this->parts($report) as $path => $contents) {
                if (! $archive->addFromString($path, $contents)) {
                    $archive->close();
                    throw new RuntimeException("Bagian workbook {$path} tidak dapat ditulis.");
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Workbook Excel tidak dapat diselesaikan.');
            }

            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('Workbook Excel tidak dapat dibaca kembali.');
            }

            $period = $report['period'];
            $product = $report['product'] ?? null;
            $filename = $product !== null
                ? sprintf('mutasi-%s-%s-sampai-%s.xlsx', $product['sku'], $period['start_date'], $period['end_date'])
                : sprintf('laporan-stok-%s-sampai-%s.xlsx', $period['start_date'], $period['end_date']);

            return new GeneratedReportFile($contents, $filename, self::CONTENT_TYPE);
        } finally {
            $this->temporaryFileCleanup->delete($temporaryPath);
        }
    }

    /** @param array<string, mixed> $report */
    private function parts(array $report): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'docProps/core.xml' => $this->coreProperties(),
            'docProps/app.xml' => $this->appProperties(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->worksheet($report),
        ];
    }

    /** @param array<string, mixed> $report */
    private function worksheet(array $report): string
    {
        $product = $report['product'] ?? null;
        $period = $report['period'];
        $summary = $report['detail_summary'] ?? null;
        $rows = [];
        $lastColumn = $product !== null ? 'H' : 'N';

        $rows[] = $this->row(1, [$this->textCell('A1', 'LAPORAN MUTASI STOK', 1)]);
        $rows[] = $this->row(2, [$this->textCell('A2', $this->companyName(), 2)]);
        $rows[] = $this->row(3, [$this->textCell('A3', 'PERIODE '.$period['start_date'].' s.d. '.$period['end_date'], 2)]);

        if ($product !== null) {
            $rows[] = $this->row(4, [$this->textCell('A4', 'Produk', 3), $this->textCell('B4', (string) $product['name'], 3)]);
            $rows[] = $this->row(5, [$this->textCell('A5', 'SKU', 3), $this->textCell('B5', (string) $product['sku'], 3)]);
            $rows[] = $this->row(6, [$this->textCell('A6', 'Unit', 3), $this->textCell('B6', (string) $product['unit'], 3)]);

            $summary = $summary ?: [];
            $rows[] = $this->row(8, [$this->textCell('A8', 'Ringkasan', 2), $this->textCell('B8', 'Jumlah', 2)]);
            $rows[] = $this->row(9, [$this->textCell('A9', 'Saldo awal'), $this->numberCell('B9', $summary['opening_balance'] ?? 0, 6)]);
            $rows[] = $this->row(10, [$this->textCell('A10', 'Barang masuk / penerimaan'), $this->numberCell('B10', $summary['incoming'] ?? 0, 6)]);
            $rows[] = $this->row(11, [$this->textCell('A11', 'Barang keluar / penjualan'), $this->numberCell('B11', $summary['outgoing'] ?? 0, 6)]);
            $rows[] = $this->row(12, [$this->textCell('A12', 'Penyesuaian'), $this->numberCell('B12', $summary['adjustments'] ?? 0, 6)]);
            $rows[] = $this->row(13, [$this->textCell('A13', 'Saldo akhir'), $this->numberCell('B13', $summary['closing_balance'] ?? 0, 6)]);
            $rows[] = $this->row(14, [$this->textCell('A14', 'Saldo ledger saat ini'), $this->numberCell('B14', $summary['ledger_stock'] ?? 0, 6)]);
            $rows[] = $this->row(15, [$this->textCell('A15', 'Stok produk saat ini'), $this->numberCell('B15', $summary['product_stock'] ?? 0, 6)]);
            $rows[] = $this->row(16, [$this->textCell('A16', 'Selisih rekonsiliasi'), $this->numberCell('B16', $summary['difference'] ?? 0, 6)]);
            $rows[] = $this->row(17, [$this->textCell('A17', 'Status rekonsiliasi'), $this->textCell('B17', $this->reconciliationLabel($summary['reconciliation_status'] ?? 'not_applicable'))]);

            $headerRow = 19;
            $headers = ['Nomor Dokumen', 'Tanggal', 'Tipe Mutasi', 'Deskripsi / Keterangan', 'Customer / Supplier', 'Masuk', 'Keluar', 'Saldo'];
            $rows[] = $this->headerRow($headerRow, $headers);

            $rowNumber = $headerRow;
            foreach ($report['mutations'] ?? [] as $mutation) {
                $rowNumber++;
                $rows[] = $this->row($rowNumber, [
                    $this->textCell('A'.$rowNumber, (string) ($mutation['document_number'] ?? '-')),
                    $this->textCell('B'.$rowNumber, (string) ($mutation['date'] ?? '-')),
                    $this->textCell('C'.$rowNumber, $this->typeLabel((string) ($mutation['type'] ?? ''))),
                    $this->textCell('D'.$rowNumber, (string) ($mutation['description'] ?? '-')),
                    $this->textCell('E'.$rowNumber, (string) ($mutation['party'] ?? '-')),
                    $this->numberCell('F'.$rowNumber, $mutation['incoming'] ?? 0, 6),
                    $this->numberCell('G'.$rowNumber, $mutation['outgoing'] ?? 0, 6),
                    $this->numberCell('H'.$rowNumber, $mutation['balance'] ?? 0, 6),
                ]);
            }
        } else {
            $headerRow = 5;
            $headers = ['SKU', 'Nama Produk', 'Kategori', 'Unit', 'Saldo Awal', 'Pembelian / Masuk', 'Penjualan / Keluar', 'Penyesuaian', 'Saldo Akhir', 'Nilai Persediaan FIFO', 'Stok Produk', 'Saldo Ledger', 'Selisih', 'Status Rekonsiliasi'];
            $rows[] = $this->headerRow($headerRow, $headers);

            $rowNumber = $headerRow;
            foreach ($report['products'] ?? [] as $productRow) {
                $rowNumber++;
                $rows[] = $this->row($rowNumber, [
                    $this->textCell('A'.$rowNumber, (string) $productRow['sku']),
                    $this->textCell('B'.$rowNumber, (string) $productRow['name']),
                    $this->textCell('C'.$rowNumber, (string) ($productRow['category'] ?: '-')),
                    $this->textCell('D'.$rowNumber, (string) $productRow['unit']),
                    $this->numberCell('E'.$rowNumber, $productRow['opening_balance'] ?? 0, 6),
                    $this->numberCell('F'.$rowNumber, $productRow['incoming_quantity'] ?? 0, 6),
                    $this->numberCell('G'.$rowNumber, $productRow['outgoing_quantity'] ?? 0, 6),
                    $this->numberCell('H'.$rowNumber, $productRow['adjustments'] ?? 0, 6),
                    $this->numberCell('I'.$rowNumber, $productRow['closing_balance'] ?? 0, 6),
                    $this->numberCell('J'.$rowNumber, $productRow['fifo_inventory_value'] ?? 0, 7),
                    $this->numberCell('K'.$rowNumber, $productRow['current_stock'] ?? 0, 6),
                    $this->numberCell('L'.$rowNumber, $productRow['ledger_current_stock'] ?? 0, 6),
                    $this->numberCell('M'.$rowNumber, $productRow['stock_difference'] ?? 0, 6),
                    $this->textCell('N'.$rowNumber, $this->reconciliationLabel($productRow['reconciliation_status'] ?? 'not_applicable')),
                ]);
            }
        }

        $columns = $product !== null
            ? [22, 14, 18, 48, 28, 14, 14, 14]
            : [18, 40, 22, 12, 16, 18, 18, 16, 16, 22, 16, 16, 14, 22];
        $widths = [];
        foreach ($columns as $index => $width) {
            $widths[] = '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $rowCount = $rowNumber;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="'.($product !== null ? 19 : 5).'" topLeftCell="A'.($product !== null ? 20 : 6).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols>'.implode('', $widths).'</cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData>'
            .'<autoFilter ref="A'.($product !== null ? 15 : 5).':'.$lastColumn.$rowCount.'"/>'
            .'<mergeCells count="3"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/><mergeCell ref="A3:'.$lastColumn.'3"/></mergeCells>'
            .'<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    /** @param list<string> $headers */
    private function headerRow(int $row, array $headers): string
    {
        $cells = [];
        foreach ($headers as $index => $header) {
            $cells[] = $this->textCell($this->columnLetter($index + 1).$row, $header, 4);
        }

        return $this->row($row, $cells);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'opening_balance' => 'Saldo awal',
            'purchase' => 'Pembelian / penerimaan',
            'sale' => 'Penjualan',
            'adjustment' => 'Penyesuaian',
            'stock_opname' => 'Stok opname',
            'return' => 'Retur barang',
            default => 'Mutasi stok',
        };
    }

    private function reconciliationLabel(string $status): string
    {
        return match ($status) {
            'balanced' => 'Seimbang',
            'needs_reconciliation' => 'Perlu rekonsiliasi',
            'historical_period' => 'Periode historis',
            default => 'Tidak tersedia',
        };
    }

    private function companyName(): string
    {
        return CompanyProfile::query()->first()?->business_name ?: (string) config('app.name', 'YokPrinting.ID');
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    /** @param list<string> $cells */
    private function row(int $number, array $cells): string
    {
        return '<row r="'.$number.'">'.implode('', $cells).'</row>';
    }

    private function textCell(string $address, string $value, int $style = 0): string
    {
        return '<c r="'.$address.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    private function numberCell(string $address, int|float|string $value, int $style): string
    {
        return '<c r="'.$address.'" s="'.$style.'"><v>'.(float) $value.'</v></c>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/package/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Mutasi Stok" sheetId="1" r:id="rId1"/></sheets><calcPr calcId="191029" calcMode="auto" fullCalcOnLoad="1" forceFullCalc="1"/></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.####"/></numFmts><fonts count="3"><font><sz val="11"/><name val="Aptos"/></font><font><b/><sz val="16"/><name val="Aptos"/></font><font><b/><sz val="11"/><name val="Aptos"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function coreProperties(): string
    {
        $timestamp = now('UTC')->format('Y-m-d\\TH:i:s\\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Laporan Mutasi Stok</dc:title><dc:creator>YokPrinting.ID</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified></cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>YokPrinting.ID</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Mutasi Stok</vt:lpstr></vt:vector></TitlesOfParts></Properties>';
    }
}
