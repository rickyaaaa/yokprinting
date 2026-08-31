<?php

namespace App\Services\Reports;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Build the product catalogue as a formatted XLSX workbook.
 *
 * Written to the client's requested layout: a centred title block naming the
 * report, the company and the date it was pulled, then a filterable header row
 * frozen above the data, then a totals row. Plain CSV cannot carry any of
 * that, which is why the product export moved off it.
 *
 * Mirrors GenerateProfitLossSpreadsheet's hand-rolled OOXML approach (this
 * codebase has no PhpSpreadsheet dependency) rather than introducing a second
 * way of writing workbooks.
 */
class GenerateProductCatalogSpreadsheet
{
    /** Column widths, in Excel character units, matching self::HEADERS. */
    private const COLUMN_WIDTHS = [14, 42, 20, 8, 14, 12, 14, 14, 18];

    private const HEADERS = [
        'SKU', 'Nama Produk', 'Kategori', 'Unit', 'HPP FIFO',
        'Stok', 'Minimum Stok', 'Status', 'Nilai Persediaan',
    ];

    /** Data starts here; rows 1-3 are the title block and row 5 is the header. */
    private const HEADER_ROW = 5;

    public function __construct(private readonly TemporaryReportFileCleanup $temporaryFileCleanup) {}

    /**
     * @param  iterable<array<string, mixed>>  $rows  as produced by ProductCatalogExportController::rows()
     * @param  array<string, mixed>  $totals
     */
    public function generate(iterable $rows, array $totals, ?string $filterSummary = null): GeneratedReportFile
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membuat export Excel.');
        }

        $temporaryDirectory = (string) config('reports.temporary_directory');
        File::ensureDirectoryExists($temporaryDirectory, 0700, true);
        $temporaryPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'produk-'.Str::uuid().'.tmp';

        try {
            $archive = new ZipArchive;

            if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Workbook Excel tidak dapat dibuat.');
            }

            foreach ($this->parts($rows, $totals, $filterSummary) as $path => $contents) {
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
                contents: $contents,
                filename: 'data-produk-'.now(config('app.timezone'))->format('Y-m-d').'.xlsx',
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
        } finally {
            $this->temporaryFileCleanup->delete($temporaryPath);
        }
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     * @return array<string, string>
     */
    private function parts(iterable $rows, array $totals, ?string $filterSummary): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'docProps/core.xml' => $this->coreProperties(),
            'docProps/app.xml' => $this->appProperties(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->worksheet($rows, $totals, $filterSummary),
        ];
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     */
    private function worksheet(iterable $rows, array $totals, ?string $filterSummary): string
    {
        $lastColumn = $this->columnLetter(count(self::HEADERS));
        $xml = [];

        // Title block - merged and centred across the whole table, following
        // the layout the client asked for.
        $xml[] = $this->row(1, [$this->textCell('A1', 'LAPORAN DATA PRODUK', 1)]);
        $xml[] = $this->row(2, [$this->textCell('A2', $this->companyName(), 2)]);
        $xml[] = $this->row(3, [$this->textCell(
            'A3',
            'PERIODE '.strtoupper(now(config('app.timezone'))->locale('id')->translatedFormat('j M Y')),
            2,
        )]);

        if ($filterSummary !== null && $filterSummary !== '') {
            $xml[] = $this->row(4, [$this->textCell('A4', $filterSummary, 3)]);
        }

        $headerCells = [];

        foreach (self::HEADERS as $index => $header) {
            $headerCells[] = $this->textCell($this->columnLetter($index + 1).self::HEADER_ROW, $header, 4);
        }

        $xml[] = $this->row(self::HEADER_ROW, $headerCells);

        $rowNumber = self::HEADER_ROW;

        foreach ($rows as $row) {
            $rowNumber++;
            $stockIsNegative = $row['stock'] !== null && (float) $row['stock'] < 0;

            $xml[] = $this->row($rowNumber, [
                $this->textCell('A'.$rowNumber, (string) $row['sku'], 5),
                $this->textCell('B'.$rowNumber, (string) $row['name'], 5),
                $this->textCell('C'.$rowNumber, (string) $row['category'], 5),
                $this->textCell('D'.$rowNumber, (string) $row['unit'], 5),
                $this->numberCell('E'.$rowNumber, $row['fifo_unit_cost'], 6),
                $row['stock'] === null
                    ? $this->textCell('F'.$rowNumber, 'Tidak dilacak', 5)
                    : $this->numberCell('F'.$rowNumber, $row['stock'], $stockIsNegative ? 8 : 7),
                $row['minimum_stock'] === null
                    ? $this->textCell('G'.$rowNumber, 'Tidak dilacak', 5)
                    : $this->numberCell('G'.$rowNumber, $row['minimum_stock'], 7),
                $this->textCell('H'.$rowNumber, (string) $row['status'], 5),
                $this->numberCell('I'.$rowNumber, $row['inventory_value'], 6),
            ]);
        }

        $totalRowNumber = $rowNumber + 1;
        $xml[] = $this->row($totalRowNumber, [
            $this->textCell('A'.$totalRowNumber, 'TOTAL ('.(int) ($totals['product_count'] ?? 0).' produk)', 9),
            $this->numberCell('I'.$totalRowNumber, $totals['inventory_value'] ?? 0, 10),
        ]);

        $merges = ['A1:'.$lastColumn.'1', 'A2:'.$lastColumn.'2', 'A3:'.$lastColumn.'3'];

        if ($filterSummary !== null && $filterSummary !== '') {
            $merges[] = 'A4:'.$lastColumn.'4';
        }

        $merges[] = 'A'.$totalRowNumber.':H'.$totalRowNumber;

        $cols = [];

        foreach (self::COLUMN_WIDTHS as $index => $width) {
            $cols[] = '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // Freeze everything above the first data row so the title block
            // and column headers stay visible while scrolling.
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="'.self::HEADER_ROW.'" topLeftCell="A'.(self::HEADER_ROW + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="16"/>'
            .'<cols>'.implode('', $cols).'</cols>'
            .'<sheetData>'.implode('', $xml).'</sheetData>'
            // Filter dropdowns on the header row, over the data only - the
            // totals row must stay out of the filter range.
            .'<autoFilter ref="A'.self::HEADER_ROW.':'.$lastColumn.($rowNumber > self::HEADER_ROW ? $rowNumber : self::HEADER_ROW).'"/>'
            .'<mergeCells count="'.count($merges).'">'
            .implode('', array_map(fn (string $ref): string => '<mergeCell ref="'.$ref.'"/>', $merges))
            .'</mergeCells>'
            .'<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function companyName(): string
    {
        $profile = CompanyProfile::query()->first();

        return $profile?->business_name
            ?: (string) config('app.name', 'YokPrinting.ID');
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
        return '<c r="'.$address.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape($value).'</t></is></c>';
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
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView activeTab="0"/></bookViews>'
            .'<sheets><sheet name="Data Produk" sheetId="1" r:id="rId1"/></sheets>'
            .'<calcPr calcId="191029" calcMode="auto" fullCalcOnLoad="1" forceFullCalc="1"/>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * Style indices used above:
     * 1 title, 2 subtitle, 3 filter note, 4 header, 5 text cell,
     * 6 rupiah, 7 quantity, 8 negative quantity, 9 total label, 10 total value.
     */
    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2">'
            .'<numFmt numFmtId="164" formatCode="[$Rp-421] #,##0;[Red]-[$Rp-421] #,##0"/>'
            .'<numFmt numFmtId="165" formatCode="#,##0;[Red]-#,##0"/>'
            .'</numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><sz val="9"/><color rgb="FF526071"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF172033"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="165" fontId="4" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'<xf numFmtId="164" fontId="4" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function coreProperties(): string
    {
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>Laporan Data Produk</dc:title><dc:creator>YokPrinting.ID</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$timestamp.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>YokPrinting.ID</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop>'
            .'<HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>1</vt:i4></vt:variant></vt:vector></HeadingPairs>'
            .'<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Data Produk</vt:lpstr></vt:vector></TitlesOfParts>'
            .'</Properties>';
    }
}
