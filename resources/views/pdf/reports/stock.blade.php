<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #172033; }
h1 { font-size: 15px; margin: 0 0 4px; } p { color: #526071; margin: 0 0 10px; }
table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #d8dee8; padding: 4px; } th { background: #eef3f9; text-align: left; } .number { text-align: right; }
</style></head><body>
<h1>Laporan Stok</h1><p>Periode {{ $start->toDateString() }} sampai {{ $end->toDateString() }}</p>
<table><thead><tr><th>SKU</th><th>Product Name</th><th>Category</th><th>Unit</th><th>Opening</th><th>Purchase</th><th>Sales</th><th>Adjustment</th><th>Ending</th><th>FIFO Value</th></tr></thead><tbody>
@foreach ($rows as $row)<tr>@foreach ($row as $index => $value)<td class="{{ $index >= 4 ? 'number' : '' }}">{{ $index === 9 ? 'Rp'.number_format($value, 0, ',', '.') : $value }}</td>@endforeach</tr>@endforeach
</tbody></table></body></html>
