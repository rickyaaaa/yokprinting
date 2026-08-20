<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 24px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 16px; margin: 0; }
        .meta { color: #526078; margin: 5px 0 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: top; }
        th { background: #eef3f9; font-size: 8px; text-align: left; }
        .number { text-align: right; white-space: nowrap; }
        .details { line-height: 1.35; white-space: pre-line; }
        .status { white-space: nowrap; }
        .empty { color: #526078; padding: 18px; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">
        Periode:
        {{ $dateFrom ? \Carbon\CarbonImmutable::parse($dateFrom)->locale('id')->translatedFormat('d M Y') : 'Semua tanggal' }}
        @if ($dateTo && $dateTo !== $dateFrom)
            sampai {{ \Carbon\CarbonImmutable::parse($dateTo)->locale('id')->translatedFormat('d M Y') }}
        @endif
        &middot; Dicetak {{ \Carbon\CarbonImmutable::now(config('app.timezone'))->locale('id')->translatedFormat('d M Y H:i') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Nomor #</th>
                <th>Tanggal</th>
                <th>Pelanggan</th>
                <th>Keterangan / Detail Invoice</th>
                <th>Status Pesanan</th>
                <th class="number">Total</th>
                <th class="number">Uang Muka</th>
                <th class="number">Sisa</th>
                <th>Status Pembayaran</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['invoice_number'] }}</td>
                    <td>{{ $row['issue_date_label'] }}</td>
                    <td>{{ $row['customer'] }}</td>
                    <td class="details">{{ $row['details'] }}</td>
                    <td class="status">{{ $row['order_status'] }}</td>
                    <td class="number">{{ $row['total_amount_label'] }}</td>
                    <td class="number">{{ $row['paid_amount_label'] }}</td>
                    <td class="number">{{ $row['remaining_amount_label'] }}</td>
                    <td class="status">{{ $row['payment_status'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">Tidak ada invoice pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
