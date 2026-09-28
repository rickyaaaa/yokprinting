<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #172033; }
h1 { font-size: 15px; margin: 0 0 4px; } p { color: #526071; margin: 0 0 8px; }
.meta { margin: 12px 0 10px; } .meta td { border: 0; padding: 2px 10px 2px 0; }
table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #d8dee8; padding: 4px; } th { background: #eef3f9; text-align: left; } .number { text-align: right; }
</style></head><body>
<h1>Mutasi per Barang</h1>
<p>Periode {{ $start->toDateString() }} s/d {{ $end->toDateString() }}</p>
<table class="meta"><tr><td><strong>Kode Barang</strong></td><td>{{ $data['product']['sku'] }}</td><td><strong>Nama Barang</strong></td><td>{{ $data['product']['name'] }}</td></tr></table>
<table><thead><tr><th>Nomor Dokumen</th><th>Tanggal</th><th>Deskripsi</th><th>Customer/Supplier</th><th>Masuk</th><th>Keluar</th><th>Saldo</th></tr></thead><tbody>
@foreach ($data['mutations'] as $row)<tr><td>{{ $row['document_number'] ?: '-' }}</td><td>{{ $row['date'] ?: '-' }}</td><td>{{ $row['description'] }}</td><td>{{ $row['party'] ?: '-' }}</td><td class="number">{{ number_format($row['incoming'], 0, ',', '.') }}</td><td class="number">{{ number_format($row['outgoing'], 0, ',', '.') }}</td><td class="number">{{ number_format($row['balance'], 0, ',', '.') }}</td></tr>@endforeach
<tr><th colspan="4">Total periode</th><th class="number">{{ number_format($data['detail_summary']['incoming'], 0, ',', '.') }}</th><th class="number">{{ number_format($data['detail_summary']['outgoing'], 0, ',', '.') }}</th><th class="number">{{ number_format($data['detail_summary']['closing_balance'], 0, ',', '.') }}</th></tr>
</tbody></table></body></html>
