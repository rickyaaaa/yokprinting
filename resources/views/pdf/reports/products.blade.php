<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #172033; }
h1 { font-size: 15px; margin: 0 0 4px; }
p.meta { color: #526071; margin: 0 0 10px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #d8dee8; padding: 4px; }
th { background: #eef3f9; text-align: left; }
.number { text-align: right; }
.warn { color: #92400e; }
tr.total td { background: #f5f7fb; font-weight: bold; }
</style></head><body>
<h1>LAPORAN DATA PRODUK</h1>
<p class="meta">
    Per {{ $generatedAt->locale('id')->translatedFormat('j F Y, H:i') }} &middot;
    {{ number_format($totals['product_count'], 0, ',', '.') }} produk
    @if ($totals['shortfall_count'] > 0)
        &middot; <span class="warn">Kekurangan stok Rp{{ number_format($totals['shortfall_value'], 0, ',', '.') }} di {{ $totals['shortfall_count'] }} produk</span>
    @endif
    @if ($filterSummary)
        <br>{{ $filterSummary }}
    @endif
</p>
<table>
    <thead><tr>@foreach ($headers as $header)<th class="{{ in_array($header, ['HPP FIFO', 'Stok', 'Minimum Stok', 'Nilai Persediaan'], true) ? 'number' : '' }}">{{ $header }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td>{{ $row['sku'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['category'] }}</td>
            <td>{{ $row['unit'] }}</td>
            <td class="number">Rp{{ number_format($row['fifo_unit_cost'], 0, ',', '.') }}</td>
            <td class="number {{ $row['stock'] !== null && $row['stock'] < 0 ? 'warn' : '' }}">{{ $row['stock'] === null ? 'Tidak dilacak' : number_format($row['stock'], 0, ',', '.') }}</td>
            <td class="number">{{ $row['minimum_stock'] === null ? 'Tidak dilacak' : number_format($row['minimum_stock'], 0, ',', '.') }}</td>
            <td>{{ $row['status'] }}</td>
            <td class="number">Rp{{ number_format($row['inventory_value'], 0, ',', '.') }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="8">Total nilai persediaan</td>
            <td class="number">Rp{{ number_format($totals['inventory_value'], 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
</body></html>
