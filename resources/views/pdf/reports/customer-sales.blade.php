<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #172033; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p { margin: 0 0 12px; color: #526071; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #d8dee8; padding: 5px; }
        th { background: #eef3f9; text-align: left; }
        .number { text-align: right; }
        .subtotal td { background: #f7f9fb; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Penjualan per Pelanggan</h1>
    <p>Periode {{ $report['period']['date_from'] }} sampai {{ $report['period']['date_to'] }}</p>
    @foreach ($report['customers'] as $customer)
        <table>
            <thead>
                <tr><th colspan="8">{{ $customer['customer'] }}</th></tr>
                <tr><th>Tanggal</th><th>Invoice</th><th>Tipe</th><th class="number">Penjualan</th><th class="number">HPP FIFO</th><th class="number">Laba Kotor</th><th class="number">Margin %</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($customer['invoices'] as $row)
                    <tr>
                        <td>{{ $row['issue_date'] }}</td><td>{{ $row['invoice_number'] }}</td><td>{{ $row['transaction_type'] }}</td>
                        <td class="number">Rp{{ number_format($row['sales'], 0, ',', '.') }}</td>
                        <td class="number">Rp{{ number_format($row['fifo_hpp'], 0, ',', '.') }}</td>
                        <td class="number">Rp{{ number_format($row['gross_profit'], 0, ',', '.') }}</td>
                        <td class="number">{{ number_format($row['margin_percent'], 2, ',', '.') }}%</td><td>{{ $row['payment_status'] }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal"><td colspan="3">Subtotal</td><td class="number">Rp{{ number_format($customer['total_sales'], 0, ',', '.') }}</td><td class="number">Rp{{ number_format($customer['total_hpp'], 0, ',', '.') }}</td><td class="number">Rp{{ number_format($customer['gross_profit'], 0, ',', '.') }}</td><td class="number">{{ number_format($customer['margin_percent'], 2, ',', '.') }}%</td><td>-</td></tr>
            </tbody>
        </table>
    @endforeach
</body>
</html>
