<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class GenerateProfitLossSpreadsheet
{
    public function __construct(private readonly TemporaryReportFileCleanup $temporaryFileCleanup) {}

    /**
     * Generate a standards-compliant XLSX workbook without moving report calculations
     * out of the server-side report service.
     *
     * @param  array<string, mixed>  $report
     */
    public function generate(array $report): GeneratedReportFile
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membuat export Excel.');
        }

        $temporaryDirectory = (string) config('reports.temporary_directory');
        File::ensureDirectoryExists($temporaryDirectory, 0700, true);
        $temporaryPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'profit-loss-'.Str::uuid().'.tmp';

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

            return new GeneratedReportFile(
                contents: $contents,
                filename: sprintf(
                    'laporan-laba-rugi-%s-sampai-%s.xlsx',
                    $report['period']['date_from'],
                    $report['period']['date_to'],
                ),
                contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
        } finally {
            $this->temporaryFileCleanup->delete($temporaryPath);
        }
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, string>
     */
    private function parts(array $report): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'docProps/app.xml' => $this->appProperties(),
            'docProps/core.xml' => $this->coreProperties(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $this->worksheet($report),
        ];
    }

    /** @param array<string, mixed> $report */
    private function worksheet(array $report): string
    {
        $summary = $report['summary'];
        $rows = [
            $this->row(1, [$this->textCell('A1', 'Laporan Laba Rugi', 1)]),
            $this->row(2, [
                $this->textCell('A2', 'Periode', 6),
                $this->textCell('B2', $report['period']['label'], 6),
            ]),
            $this->row(3, [
                $this->textCell('A3', 'Basis data', 6),
                $this->textCell('B3', 'Hanya invoice final berstatus terkirim. Pajak dan ongkir pelanggan bukan omzet.', 6),
            ]),
            $this->row(5, [
                $this->textCell('A5', 'Komponen', 2),
                $this->textCell('B5', 'Nilai', 2),
            ]),
            $this->row(6, [$this->textCell('A6', 'Penjualan barang/jasa'), $this->numberCell('B6', $summary['gross_sales'], 4)]),
            $this->row(7, [$this->textCell('A7', 'Diskon penjualan'), $this->numberCell('B7', $summary['sales_discount'], 4)]),
            $this->row(8, [$this->textCell('A8', 'Omzet Penjualan', 3), $this->numberCell('B8', $summary['sales_revenue'], 5)]),
            $this->row(9, [$this->textCell('A9', 'Pajak dipungut (bukan omzet)'), $this->numberCell('B9', $summary['tax_collected'], 4)]),
            $this->row(10, [$this->textCell('A10', 'Ongkir ditagihkan kepada pelanggan (bukan omzet)'), $this->numberCell('B10', $summary['customer_shipping_charged'], 4)]),
            $this->row(11, [$this->textCell('A11', 'Total Invoice menurut komponen'), $this->numberCell('B11', $summary['expected_invoice_total'], 4)]),
            $this->row(12, [$this->textCell('A12', 'Total Invoice tersimpan', 3), $this->numberCell('B12', $summary['total_invoice'], 5)]),
            $this->row(13, [$this->textCell('A13', 'Total Harga Modal / HPP'), $this->numberCell('B13', $summary['total_hpp'], 4)]),
            $this->row(14, [$this->textCell('A14', 'Laba Kotor', 3), $this->numberCell('B14', $summary['gross_profit'], 5)]),
            $this->row(15, [$this->textCell('A15', 'Biaya Ongkir Ditanggung Perusahaan'), $this->numberCell('B15', $summary['shipping_expenses'], 4)]),
            $this->row(16, [$this->textCell('A16', 'Total Biaya Produksi'), $this->numberCell('B16', $summary['production_expenses'], 4)]),
            $this->row(17, [$this->textCell('A17', 'Total Biaya Karyawan'), $this->numberCell('B17', $summary['employee_expenses'], 4)]),
            $this->row(18, [$this->textCell('A18', 'Total Biaya Tempat'), $this->numberCell('B18', $summary['premises_expenses'], 4)]),
            $this->row(19, [$this->textCell('A19', 'Total Belanjaan'), $this->numberCell('B19', $summary['shopping_expenses'], 4)]),
            $this->row(20, [$this->textCell('A20', 'Pengeluaran belum diklasifikasikan terhadap HPP'), $this->numberCell('B20', $summary['unclassified_expenses'], 4)]),
            $this->row(21, [$this->textCell('A21', 'Total Pengeluaran Tercatat', 3), $this->numberCell('B21', $summary['recorded_expenses'], 5)]),
            $this->row(22, [$this->textCell('A22', 'Pengeluaran Diakui di Laba Rugi', 3), $this->numberCell('B22', $summary['recognized_expenses'], 5)]),
            $this->row(23, [$this->textCell('A23', 'Laba Bersih Minimum', 3), $this->numberCell('B23', $summary['net_profit_minimum'], 5)]),
            $this->row(24, [$this->textCell('A24', 'Laba Bersih Maksimum', 3), $this->numberCell('B24', $summary['net_profit_maximum'], 5)]),
            $this->row(25, [$this->textCell('A25', 'Qty Penjualan'), $this->numberCell('B25', $summary['sales_quantity'], 7)]),
            $this->row(26, [$this->textCell('A26', 'Jumlah Invoice'), $this->numberCell('B26', $summary['invoice_count'], 8)]),
            $this->row(27, [$this->textCell('A27', 'Jumlah Transaksi Pengeluaran'), $this->numberCell('B27', $summary['expense_count'], 8)]),
            $this->row(28, [$this->textCell('A28', 'Selisih rekonsiliasi invoice'), $this->numberCell('B28', $summary['invoice_reconciliation_difference'], 4)]),
            $this->row(29, [$this->textCell('A29', 'Selisih rekonsiliasi laba minimum'), $this->numberCell('B29', $summary['minimum_profit_reconciliation_difference'], 4)]),
            $this->row(30, [$this->textCell('A30', 'Selisih rekonsiliasi laba maksimum'), $this->numberCell('B30', $summary['maximum_profit_reconciliation_difference'], 4)]),
            $this->row(32, [
                $this->textCell('A32', 'Keputusan bisnis diperlukan', 6),
                $this->textCell('B32', $report['accounting_policy']['decision_required'], 6),
            ]),
            $this->row(33, [$this->textCell('A33', 'Basis laba minimum', 6), $this->textCell('B33', $report['accounting_policy']['minimum_profit_basis'], 6)]),
            $this->row(34, [$this->textCell('A34', 'Basis laba maksimum', 6), $this->textCell('B34', $report['accounting_policy']['maximum_profit_basis'], 6)]),
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<cols><col min="1" max="1" width="40" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $rows).'</sheetData>'
            .'<mergeCells count="1"><mergeCell ref="A1:B1"/></mergeCells>'
            .'<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="portrait" paperSize="9" fitToWidth="1" fitToHeight="1"/>'
            .'</worksheet>';
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
            .'<sheets><sheet name="Laba Rugi" sheetId="1" r:id="rId1"/></sheets>'
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

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="[$Rp-421] #,##0.00;[Red]-[$Rp-421] #,##0.00"/><numFmt numFmtId="165" formatCode="#,##0.####"/></numFmts>'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Aptos"/></font>'
            .'<font><b/><sz val="11"/><name val="Aptos"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Aptos"/></font>'
            .'</fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="9">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
            .'<xf numFmtId="164" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function coreProperties(): string
    {
        $timestamp = now('UTC')->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>Laporan Laba Rugi</dc:title><dc:creator>YokPrinting.ID</dc:creator>'
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
            .'<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Laba Rugi</vt:lpstr></vt:vector></TitlesOfParts>'
            .'</Properties>';
    }
}
