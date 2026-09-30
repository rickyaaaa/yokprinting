<?php

namespace App\Services\Reports;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Generate the customer sales report in the same workbook layout as the
 * product catalogue export: title block, filter metadata, frozen/filterable
 * header row, real numeric cells, and a totals row.
 */
class GenerateCustomerSalesSpreadsheet
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const HEADERS = [
        'Customer', 'Tanggal', 'Invoice', 'Tipe Transaksi',
        'Penjualan', 'HPP FIFO', 'Laba Kotor', 'Margin %',
    ];

    public function __construct(private readonly TemporaryReportFileCleanup $temporaryFileCleanup) {}

    /** @param array<string, mixed> $report */
    public function generate(array $report, ?string $filterSummary = null): GeneratedReportFile
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membuat export Excel.');
        }

        $temporaryDirectory = (string) config('reports.temporary_directory');
        File::ensureDirectoryExists($temporaryDirectory, 0700, true);
        $temporaryPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'penjualan-pelanggan-'.Str::uuid().'.tmp';

        try {
            $archive = new ZipArchive;
            if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Workbook Excel tidak dapat dibuat.');
            }

            foreach ($this->parts($report, $filterSummary) as $path => $contents) {
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

            return new GeneratedReportFile(
                $contents,
                sprintf(
                    'penjualan-per-pelanggan-%s-sampai-%s.xlsx',
                    $report['period']['date_from'],
                    $report['period']['date_to'],
                ),
                self::CONTENT_TYPE,
            );
        } finally {
            $this->temporaryFileCleanup->delete($temporaryPath);
        }
    }

    /** @param array<string, mixed> $report */
    private function parts(array $report, ?string $filterSummary): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'docProps/core.xml' => $this->coreProperties(),
            'docProps/app.xml' => $this->appProperties(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->worksheet($report, $filterSummary),
        ];
    }

    /** @param array<string, mixed> $report */
    private function worksheet(array $report, ?string $filterSummary): string
    {
        $rows = [
            $this->row(1, [$this->textCell('A1', 'LAPORAN PENJUALAN PER PELANGGAN', 1)]),
            $this->row(2, [$this->textCell('A2', $this->companyName(), 2)]),
            $this->row(3, [$this->textCell('A3', 'PERIODE '.$report['period']['date_from'].' s.d. '.$report['period']['date_to'], 2)]),
        ];

        $headerRow = 5;
        if ($filterSummary !== null && $filterSummary !== '') {
            $rows[] = $this->row(4, [$this->textCell('A4', $filterSummary, 3)]);
        }

        $rows[] = $this->headerRow($headerRow);
        $rowNumber = $headerRow;
        $invoiceCount = 0;

        foreach ($report['customers'] as $customer) {
            foreach ($customer['invoices'] as $invoice) {
                $rowNumber++;
                $invoiceCount++;
                $rows[] = $this->row($rowNumber, [
                    $this->textCell('A'.$rowNumber, (string) $customer['customer']),
                    $this->textCell('B'.$rowNumber, (string) $invoice['issue_date']),
                    $this->textCell('C'.$rowNumber, (string) $invoice['invoice_number']),
                    $this->textCell('D'.$rowNumber, (string) $invoice['transaction_type']),
                    $this->numberCell('E'.$rowNumber, $invoice['sales'] ?? 0, 5),
                    $this->numberCell('F'.$rowNumber, $invoice['fifo_hpp'] ?? 0, 5),
                    $this->numberCell('G'.$rowNumber, $invoice['gross_profit'] ?? 0, 5),
                    $this->numberCell('H'.$rowNumber, $invoice['margin_percent'] ?? 0, 6),
                ]);
            }
        }

        $totalRow = $rowNumber + 1;
        $summary = $report['summary'];
        $rows[] = $this->row($totalRow, [
            $this->textCell('A'.$totalRow, 'TOTAL ('.$invoiceCount.' invoice)', 7),
            $this->numberCell('E'.$totalRow, $summary['sales'] ?? 0, 8),
            $this->numberCell('F'.$totalRow, $summary['fifo_hpp'] ?? 0, 8),
            $this->numberCell('G'.$totalRow, $summary['gross_profit'] ?? 0, 8),
            $this->numberCell('H'.$totalRow, $summary['margin_percent'] ?? 0, 9),
        ]);

        $lastDataRow = max($headerRow, $rowNumber);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols>'.$this->columns().'</cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData>'
            .'<autoFilter ref="A5:H'.$lastDataRow.'"/>'
            .'<mergeCells count="4"><mergeCell ref="A1:H1"/><mergeCell ref="A2:H2"/><mergeCell ref="A3:H3"/><mergeCell ref="A'.$totalRow.':D'.$totalRow.'"/></mergeCells>'
            .'<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function headerRow(int $row): string
    {
        $cells = [];
        foreach (self::HEADERS as $index => $header) {
            $cells[] = $this->textCell($this->columnLetter($index + 1).$row, $header, 4);
        }

        return $this->row($row, $cells);
    }

    private function companyName(): string
    {
        return CompanyProfile::query()->first()?->business_name ?: (string) config('app.name', 'YokPrinting.ID');
    }

    private function columns(): string
    {
        $widths = [30, 14, 24, 18, 18, 18, 18, 14];
        $columns = [];
        foreach ($widths as $index => $width) {
            $columns[] = '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return implode('', $columns);
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
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/package/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Penjualan Pelanggan" sheetId="1" r:id="rId1"/></sheets><calcPr calcId="191029" calcMode="auto" fullCalcOnLoad="1" forceFullCalc="1"/></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="2"><numFmt numFmtId="164" formatCode="[$Rp-421] #,##0;[Red]-[$Rp-421] #,##0"/><numFmt numFmtId="165" formatCode="0.00"/></numFmts><fonts count="5"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><sz val="9"/><color rgb="FF526071"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FF172033"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><cellXfs count="10"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/><xf numFmtId="164" fontId="4" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf><xf numFmtId="165" fontId="4" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf></cellXfs></styleSheet>';
    }

    private function coreProperties(): string
    {
        $timestamp = now('UTC')->format('Y-m-d\\TH:i:s\\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Penjualan per Pelanggan</dc:title><dc:creator>YokPrinting.ID</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified></cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>YokPrinting.ID</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Penjualan Pelanggan</vt:lpstr></vt:vector></TitlesOfParts></Properties>';
    }
}
