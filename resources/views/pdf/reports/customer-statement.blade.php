@php
    $customer = $statement['customer'] ?? [];
    $period = $statement['period'] ?? [];
    $summary = $statement['summary'] ?? [];
    $transactions = collect($statement['transactions'] ?? []);
    $logoPath = public_path('images/yokprinting-logo.png');
    $yokPrintingLogo = file_exists($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : null;
    $yokPrintingAddress = 'Jl. Karyawan II, RT.005/RW.005, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157';
    $formatDate = fn (?string $date): string => $date ? \Carbon\Carbon::parse($date)->translatedFormat('d M Y') : '-';
    $formatDateTime = fn (?string $date): string => $date ? \Carbon\Carbon::parse($date)->translatedFormat('d M Y H:i') : '-';
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Laporan Rekening Koran Customer</title>
        <style>
            @page {
                margin: 28px;
            }

            * {
                box-sizing: border-box;
            }

            body {
                color: #1f2933;
                font-family: "DejaVu Sans", Arial, sans-serif;
                font-size: 10px;
                line-height: 1.45;
                margin: 0;
            }

            .header {
                border-bottom: 2px solid #0b5f93;
                display: table;
                margin-bottom: 18px;
                padding-bottom: 14px;
                width: 100%;
            }

            .header-cell {
                display: table-cell;
                vertical-align: top;
            }

            .header-right {
                text-align: right;
                width: 38%;
            }

            .logo {
                display: block;
                height: auto;
                margin-bottom: 8px;
                width: 154px;
            }

            .brand-name {
                color: #0b5f93;
                font-size: 13px;
                font-weight: 700;
                letter-spacing: .02em;
                margin: 0 0 3px;
            }

            .muted {
                color: #667085;
            }

            .title {
                color: #111827;
                font-size: 18px;
                font-weight: 800;
                margin: 0 0 6px;
                text-transform: uppercase;
            }

            .period {
                border: 1px solid #c9d8e5;
                border-radius: 8px;
                color: #0b5f93;
                display: inline-block;
                font-weight: 700;
                padding: 6px 10px;
            }

            .info-grid {
                display: table;
                margin-bottom: 16px;
                width: 100%;
            }

            .info-card {
                border: 1px solid #d9dee7;
                border-radius: 10px;
                display: table-cell;
                padding: 12px;
                vertical-align: top;
                width: 50%;
            }

            .info-spacer {
                display: table-cell;
                width: 12px;
            }

            .label {
                color: #667085;
                font-size: 8px;
                font-weight: 700;
                letter-spacing: .08em;
                margin-bottom: 4px;
                text-transform: uppercase;
            }

            .customer-name {
                color: #111827;
                font-size: 13px;
                font-weight: 800;
                margin-bottom: 4px;
            }

            .summary-grid {
                display: table;
                margin-bottom: 18px;
                width: 100%;
            }

            .summary-card {
                background: #f8fafc;
                border: 1px solid #e1e7ef;
                display: table-cell;
                padding: 10px;
                width: 25%;
            }

            .summary-card:first-child {
                border-radius: 10px 0 0 10px;
            }

            .summary-card:last-child {
                border-radius: 0 10px 10px 0;
            }

            .summary-value {
                color: #111827;
                font-size: 13px;
                font-weight: 800;
                margin-top: 3px;
            }

            .summary-value.primary {
                color: #0b5f93;
                font-size: 15px;
            }

            table {
                border-collapse: collapse;
                width: 100%;
            }

            thead th {
                background: #0b5f93;
                color: #ffffff;
                font-size: 8px;
                letter-spacing: .04em;
                padding: 8px 6px;
                text-align: left;
                text-transform: uppercase;
            }

            tbody td {
                border-bottom: 1px solid #e5e7eb;
                padding: 8px 6px;
                vertical-align: top;
            }

            tbody tr:nth-child(even) td {
                background: #f9fafb;
            }

            .amount {
                font-variant-numeric: tabular-nums;
                text-align: right;
                white-space: nowrap;
            }

            .debit {
                color: #991b1b;
                font-weight: 700;
            }

            .credit {
                color: #166534;
                font-weight: 700;
            }

            .balance {
                color: #0b5f93;
                font-weight: 800;
            }

            .ref {
                color: #111827;
                font-weight: 700;
                white-space: nowrap;
            }

            .type-badge {
                background: #eef6ff;
                border-radius: 999px;
                color: #0b5f93;
                display: inline-block;
                font-size: 8px;
                font-weight: 700;
                padding: 2px 7px;
            }

            .empty {
                border: 1px dashed #cbd5e1;
                border-radius: 10px;
                color: #667085;
                padding: 22px;
                text-align: center;
            }

            .footer {
                border-top: 1px solid #d9dee7;
                color: #667085;
                font-size: 8px;
                margin-top: 18px;
                padding-top: 10px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="header-cell">
                @if ($yokPrintingLogo)
                    <img class="logo" src="{{ $yokPrintingLogo }}" alt="YokPrinting.ID">
                @endif
                <p class="brand-name">YokPrinting.ID</p>
                <div class="muted">Sablon cup & cetak kemasan F&B</div>
                <div class="muted">{{ $yokPrintingAddress }}</div>
            </div>
            <div class="header-cell header-right">
                <h1 class="title">Rekening Koran Customer</h1>
                <div class="period">
                    {{ $formatDate($period['start_date'] ?? null) }} - {{ $formatDate($period['end_date'] ?? null) }}
                </div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <div class="label">Customer</div>
                <div class="customer-name">{{ $customer['name'] ?? '-' }}</div>
                <div class="muted">
                    {{ $customer['code'] ?? '-' }}
                    @if (! empty($customer['activity_status']))
                        - {{ $customer['activity_status'] }}
                    @endif
                </div>
                <div class="muted">{{ $customer['email'] ?? '-' }} - {{ $customer['phone'] ?? '-' }}</div>
                <div class="muted">{{ $customer['address'] ?? '-' }}</div>
            </div>
            <div class="info-spacer"></div>
            <div class="info-card">
                <div class="label">Ringkasan Saldo</div>
                <div class="muted">Laporan ini menampilkan faktur sebagai debit dan pembayaran terverifikasi sebagai kredit.</div>
                <div style="height: 8px;"></div>
                <div class="label">Sisa Piutang</div>
                <div class="summary-value primary">{{ $summary['outstanding_amount_formatted'] ?? 'Rp0' }}</div>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="label">Saldo Awal</div>
                <div class="summary-value">{{ $summary['opening_balance_formatted'] ?? 'Rp0' }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Total Tagihan</div>
                <div class="summary-value debit">{{ $summary['total_debit_formatted'] ?? 'Rp0' }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Total Pembayaran</div>
                <div class="summary-value credit">{{ $summary['total_credit_formatted'] ?? 'Rp0' }}</div>
            </div>
            <div class="summary-card">
                <div class="label">Saldo Akhir</div>
                <div class="summary-value primary">{{ $summary['outstanding_amount_formatted'] ?? 'Rp0' }}</div>
            </div>
        </div>

        @if ($transactions->isEmpty())
            <div class="empty">Tidak ada mutasi piutang pada periode ini.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width: 14%;">Tanggal & Waktu</th>
                        <th style="width: 15%;">No. Referensi</th>
                        <th style="width: 27%;">Keterangan</th>
                        <th style="width: 14%;" class="amount">Tagihan / Debit (+)</th>
                        <th style="width: 14%;" class="amount">Pembayaran / Kredit (-)</th>
                        <th style="width: 16%;" class="amount">Sisa Saldo Piutang</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td>{{ $formatDateTime($transaction['transaction_at'] ?? null) }}</td>
                            <td class="ref">{{ $transaction['reference_number'] ?? '-' }}</td>
                            <td>
                                <span class="type-badge">{{ $transaction['type_label'] ?? '-' }}</span>
                                <div>{{ $transaction['description'] ?? '-' }}</div>
                            </td>
                            <td class="amount debit">
                                {{ ((float) ($transaction['debit'] ?? 0)) > 0 ? $transaction['debit_formatted'] : '-' }}
                            </td>
                            <td class="amount credit">
                                {{ ((float) ($transaction['credit'] ?? 0)) > 0 ? $transaction['credit_formatted'] : '-' }}
                            </td>
                            <td class="amount balance">{{ $transaction['running_balance_formatted'] ?? 'Rp0' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="footer">
            Dicetak dari YokPrinting ERP Lite - {{ now()->translatedFormat('d M Y H:i') }} - {{ $yokPrintingAddress }}
        </div>
    </body>
</html>
