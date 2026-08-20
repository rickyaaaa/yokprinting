<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #172033; } h1 { font-size: 16px; margin: 0 0 10px; }
table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid #d8dee8; padding: 5px; } th { background: #eef3f9; text-align: left; } .number { text-align: right; }
</style></head><body><h1>Daftar Invoice</h1><table><thead><tr><th>Invoice</th><th>Customer</th><th>Tanggal</th><th>Jatuh Tempo</th><th>Total</th><th>Status</th></tr></thead><tbody>
@foreach ($invoices as $invoice)<tr><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->customer?->name ?? 'Pelanggan tidak tersedia' }}</td><td>{{ $invoice->issue_date->toDateString() }}</td><td>{{ $invoice->due_date->toDateString() }}</td><td class="number">Rp{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td><td>{{ $invoice->payment_status }}</td></tr>@endforeach
</tbody></table></body></html>
