@php
    $period = $report['period'];
    $summary = $report['summary'];
    $money = fn (int|float|string $value): string => 'Rp'.number_format((float) $value, 2, ',', '.');
    $number = fn (int|float|string $value): string => number_format((float) $value, 4, ',', '.');
    $logoPath = public_path('images/yokprinting-logo.png');
    $logo = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Laporan Laba Rugi</title>
        <style>
            @page { margin: 32px 38px; }
            * { box-sizing: border-box; }
            body { color: #0f172a; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10px; line-height: 1.45; margin: 0; }
            .header { border-bottom: 2px solid #1d4ed8; display: table; margin-bottom: 22px; padding-bottom: 14px; width: 100%; }
            .header-cell { display: table-cell; vertical-align: middle; }
            .header-right { text-align: right; width: 50%; }
            .logo { height: auto; width: 150px; }
            h1 { font-size: 21px; margin: 0 0 6px; }
            .muted { color: #64748b; }
            .period { color: #1d4ed8; font-size: 11px; font-weight: 700; }
            .summary { display: table; margin-bottom: 20px; width: 100%; }
            .summary-cell { border: 1px solid #dbe2ea; display: table-cell; padding: 12px; width: 33.333%; }
            .summary-cell.middle { border-left: 0; border-right: 0; }
            .label { color: #64748b; font-size: 8px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
            .value { font-size: 15px; font-weight: 700; margin-top: 4px; }
            table { border-collapse: collapse; width: 100%; }
            td { border-bottom: 1px solid #e2e8f0; padding: 9px 10px; }
            td.amount { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
            tr.section td { background: #f8fafc; color: #475569; font-size: 8px; font-weight: 700; letter-spacing: .06em; padding-top: 12px; text-transform: uppercase; }
            tr.total td { font-weight: 700; }
            tr.gross td { background: #eff6ff; color: #1e3a8a; font-size: 11px; font-weight: 700; }
            tr.net td { border-bottom: 2px solid #0f172a; border-top: 2px solid #0f172a; font-size: 13px; font-weight: 800; padding-bottom: 12px; padding-top: 12px; }
            .negative { color: #b91c1c; }
            .note { background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; margin-top: 18px; padding: 10px 12px; }
            .footer { border-top: 1px solid #e2e8f0; color: #64748b; font-size: 8px; margin-top: 20px; padding-top: 9px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="header-cell">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="YokPrinting.ID">
                @else
                    <strong>YokPrinting.ID</strong>
                @endif
            </div>
            <div class="header-cell header-right">
                <h1>Laporan Laba Rugi</h1>
                <div class="period">{{ $period['label'] }}</div>
                <div class="muted">{{ $period['date_from'] }} - {{ $period['date_to'] }}</div>
            </div>
        </div>

        <div class="summary">
            <div class="summary-cell">
                <div class="label">Omzet Penjualan</div>
                <div class="value">{{ $money($summary['sales_revenue']) }}</div>
            </div>
            <div class="summary-cell middle">
                <div class="label">Laba Kotor</div>
                <div class="value">{{ $money($summary['gross_profit']) }}</div>
            </div>
            <div class="summary-cell">
                <div class="label">Rentang Laba Bersih</div>
                <div class="value {{ $summary['net_profit_minimum'] < 0 ? 'negative' : '' }}">{{ $money($summary['net_profit_minimum']) }} – {{ $money($summary['net_profit_maximum']) }}</div>
            </div>
        </div>

        <table>
            <tbody>
                <tr class="section"><td colspan="2">Rekonsiliasi invoice final</td></tr>
                <tr><td>Penjualan barang/jasa</td><td class="amount">{{ $money($summary['gross_sales']) }}</td></tr>
                <tr><td>Diskon penjualan</td><td class="amount">({{ $money($summary['sales_discount']) }})</td></tr>
                <tr class="total"><td>Omzet Penjualan</td><td class="amount">{{ $money($summary['sales_revenue']) }}</td></tr>
                <tr><td>Pajak dipungut (bukan omzet)</td><td class="amount">{{ $money($summary['tax_collected']) }}</td></tr>
                <tr><td>Ongkir ditagihkan kepada pelanggan (bukan omzet)</td><td class="amount">{{ $money($summary['customer_shipping_charged']) }}</td></tr>
                <tr class="total"><td>Total Invoice</td><td class="amount">{{ $money($summary['total_invoice']) }}</td></tr>
                @if ($summary['invoice_reconciliation_difference'] != 0)
                    <tr><td>Selisih rekonsiliasi invoice</td><td class="amount negative">{{ $money($summary['invoice_reconciliation_difference']) }}</td></tr>
                @endif
                <tr><td>Total Harga Modal / HPP</td><td class="amount">({{ $money($summary['total_hpp']) }})</td></tr>
                <tr class="gross"><td>Laba Kotor</td><td class="amount">{{ $money($summary['gross_profit']) }}</td></tr>
                <tr class="section"><td colspan="2">Pengeluaran operasional</td></tr>
                <tr><td>Biaya Ongkir Ditanggung Perusahaan</td><td class="amount">{{ $money($summary['shipping_expenses']) }}</td></tr>
                <tr><td>Total Biaya Produksi</td><td class="amount">{{ $money($summary['production_expenses']) }}</td></tr>
                <tr><td>Total Biaya Karyawan</td><td class="amount">{{ $money($summary['employee_expenses']) }}</td></tr>
                <tr><td>Total Biaya Tempat</td><td class="amount">{{ $money($summary['premises_expenses']) }}</td></tr>
                <tr><td>Total Belanjaan</td><td class="amount">{{ $money($summary['shopping_expenses']) }}</td></tr>
                <tr class="total"><td>Total Pengeluaran Tercatat</td><td class="amount">{{ $money($summary['recorded_expenses']) }}</td></tr>
                <tr class="total"><td>Pengeluaran Diakui di Laba Rugi</td><td class="amount">({{ $money($summary['recognized_expenses']) }})</td></tr>
                <tr class="net"><td>Laba Bersih Minimum</td><td class="amount {{ $summary['net_profit_minimum'] < 0 ? 'negative' : '' }}">{{ $money($summary['net_profit_minimum']) }}</td></tr>
                <tr class="net"><td>Laba Bersih Maksimum</td><td class="amount {{ $summary['net_profit_maximum'] < 0 ? 'negative' : '' }}">{{ $money($summary['net_profit_maximum']) }}</td></tr>
                <tr class="section"><td colspan="2">Volume</td></tr>
                <tr><td>Qty Penjualan</td><td class="amount">{{ $number($summary['sales_quantity']) }}</td></tr>
                <tr><td>Jumlah Invoice</td><td class="amount">{{ $summary['invoice_count'] }}</td></tr>
                <tr><td>Jumlah Transaksi Pengeluaran</td><td class="amount">{{ $summary['expense_count'] }}</td></tr>
            </tbody>
        </table>

        <div class="note">
            Basis data: hanya invoice final berstatus terkirim. Draft dan invoice dibatalkan tidak dihitung. Pajak dan ongkir pelanggan bukan omzet.
            <br>{{ $report['accounting_policy']['minimum_profit_basis'] }}
            <br>{{ $report['accounting_policy']['maximum_profit_basis'] }}
            @if ($report['accounting_policy']['profit_is_provisional'])
                <br><strong>Keputusan bisnis diperlukan:</strong> {{ $report['accounting_policy']['decision_required'] }}
            @endif
        </div>
        <div class="footer">Dibuat oleh YokPrinting.ID pada {{ now(config('app.timezone'))->translatedFormat('d M Y H:i') }}</div>
    </body>
</html>
