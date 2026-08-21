@php
    $yokPrintingLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/yokprinting-logo.png')));
    $yokPrintingAddress = 'Jl. Karyawan II, RT.005/RW.005, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157';
    $money = fn ($amount): string => 'Rp'.number_format((float) $amount, 0, ',', '.');
    $percent = fn ($amount): string => rtrim(rtrim(number_format((float) $amount, 2, ',', '.'), '0'), ',');
@endphp
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Cetak Pesanan Detail</title>
        <style>
            @page { margin: 13mm 14mm 16mm; }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                color: #172033;
                font-family: "DejaVu Sans", sans-serif;
                font-size: 9px;
                line-height: 1.4;
            }

            .order + .order { page-break-before: always; }
            .top-accent { height: 4px; background: #2758d6; }
            .header, .meta, .items, .totals, .notes { width: 100%; border-collapse: collapse; }
            .header { margin-top: 10px; }
            .header td { vertical-align: top; }
            .brand-logo { display: block; width: 92px; height: auto; margin-bottom: 4px; }
            .brand { color: #102a4e; font-size: 13px; font-weight: 700; }
            .brand-address { max-width: 300px; margin-top: 2px; color: #516781; font-size: 7.5px; line-height: 1.45; }
            .document { text-align: right; }
            .document-title { color: #102a4e; font-size: 20px; font-weight: 700; line-height: 1.1; }
            .document-number { margin-top: 5px; font-family: monospace; font-size: 10px; font-weight: 700; }
            .period { margin-top: 3px; color: #516781; font-size: 7.5px; }
            .rule { height: 2px; margin: 11px 0 12px; background: #102a4e; }
            .meta { border: 1px solid #9aa8ba; }
            .meta td { width: 25%; padding: 6px 7px; border: 1px solid #cbd5e1; vertical-align: top; }
            .meta .label { color: #516781; font-size: 7px; }
            .meta .value { margin-top: 2px; font-weight: 700; }
            .customer { margin: 13px 0 11px; }
            .customer td { width: 50%; padding-right: 14px; vertical-align: top; }
            .section-label { color: #384e69; font-size: 7px; font-weight: 700; text-transform: uppercase; }
            .customer-name { margin-top: 3px; font-size: 12px; font-weight: 700; }
            .customer-detail { margin-top: 3px; color: #516781; font-size: 8px; line-height: 1.5; }
            .items { margin-top: 9px; border: 1px solid #9aa8ba; }
            .items thead { display: table-header-group; }
            .items th { padding: 6px 7px; border: 1px solid #9aa8ba; background: #eef3f9; font-size: 7.5px; text-align: left; }
            .items td { padding: 6px 7px; border: 1px solid #cbd5e1; vertical-align: top; }
            .items tr { page-break-inside: avoid; }
            .items .center { text-align: center; }
            .items .number { text-align: right; white-space: nowrap; }
            .item-name { font-weight: 700; }
            .item-note { margin-top: 2px; color: #516781; font-size: 7.5px; }
            .totals { width: 42%; margin: 10px 0 0 auto; border: 1px solid #9aa8ba; }
            .totals td { padding: 5px 7px; border: 1px solid #cbd5e1; }
            .totals td:last-child { text-align: right; white-space: nowrap; }
            .totals .grand td { border-top: 2px solid #102a4e; font-size: 10px; font-weight: 700; }
            .notes { margin-top: 12px; border: 1px solid #9aa8ba; }
            .notes td { padding: 7px; vertical-align: top; }
            .notes .content { min-height: 35px; color: #384e69; white-space: pre-line; }
            .footer { margin-top: 12px; color: #516781; font-size: 7px; text-align: center; }
        </style>
    </head>
    <body>
        @forelse ($orders as $order)
            <section class="order">
                <div class="top-accent"></div>
                <table class="header">
                    <tr>
                        <td>
                            <img class="brand-logo" src="{{ $yokPrintingLogo }}" alt="YokPrinting.ID">
                            <div class="brand">YokPrinting.ID</div>
                            <div class="brand-address">{{ $yokPrintingAddress }}<br>Sablon cup &amp; cetak kemasan F&amp;B</div>
                        </td>
                        <td class="document">
                            <div class="document-title">Pesanan Penjualan</div>
                            <div class="document-number">{{ $order['invoice_number'] }}</div>
                            <div class="period">Dicetak dari periode {{ $dateFrom ?: 'semua tanggal' }}@if ($dateTo && $dateTo !== $dateFrom) s/d {{ $dateTo }}@endif</div>
                        </td>
                    </tr>
                </table>

                <div class="rule"></div>

                <table class="meta">
                    <tr>
                        <td><div class="label">Tanggal</div><div class="value">{{ $order['issue_date_label'] }}</div></td>
                        <td><div class="label">Nomor</div><div class="value">{{ $order['invoice_number'] }}</div></td>
                        <td><div class="label">Jatuh tempo</div><div class="value">{{ $order['due_date_label'] }}</div></td>
                        <td><div class="label">Status pembayaran</div><div class="value">{{ $order['payment_status'] }}</div></td>
                    </tr>
                </table>

                <table class="customer">
                    <tr>
                        <td>
                            <div class="section-label">Kepada</div>
                            <div class="customer-name">{{ $order['customer']['name'] ?? 'Pelanggan' }}</div>
                            <div class="customer-detail">
                                {!! nl2br(e($order['customer']['address'] ?? '-')) !!}<br>
                                {{ collect([$order['customer']['email'] ?? null, $order['customer']['phone'] ?? null])->filter()->join(' · ') ?: '-' }}
                            </div>
                        </td>
                        <td>
                            <div class="section-label">Status pesanan</div>
                            <div class="customer-name">{{ $order['order_status'] }}</div>
                            <div class="customer-detail">Detail item dan nilai transaksi sesuai invoice.</div>
                        </td>
                    </tr>
                </table>

                <table class="items">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Kode Barang</th>
                            <th style="width: 39%;">Nama Barang</th>
                            <th class="center" style="width: 10%;">Kts.</th>
                            <th class="number" style="width: 15%;">@Harga</th>
                            <th class="number" style="width: 21%;">Total Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order['items'] as $item)
                            <tr>
                                <td>{{ $item['code'] }}</td>
                                <td>
                                    <div class="item-name">{{ $item['name'] }}</div>
                                    @if (! empty($item['note']))
                                        <div class="item-note">{{ $item['note'] }}</div>
                                    @endif
                                </td>
                                <td class="center">{{ $item['quantity_label'] }}</td>
                                <td class="number">{{ $money($item['unit_price']) }}</td>
                                <td class="number">{{ $money($item['line_total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="totals">
                    <tr><td>Subtotal</td><td>{{ $money($order['subtotal']) }}</td></tr>
                    <tr><td>Diskon</td><td>{{ $money($order['discount_amount']) }}</td></tr>
                    <tr><td>PPN</td><td>{{ $money($order['tax_amount']) }}</td></tr>
                    @if ((float) ($order['shipping_cost'] ?? 0) > 0 || ($order['is_free_shipping'] ?? false))
                        <tr><td>{{ ($order['is_free_shipping'] ?? false) ? 'Free ongkir' : 'Ongkir' }}</td><td>{{ $money($order['shipping_cost']) }}</td></tr>
                    @endif
                    <tr class="grand"><td>Total</td><td>{{ $money($order['total_amount']) }}</td></tr>
                    <tr><td>Uang muka tercatat</td><td>{{ $money($order['paid_amount']) }}</td></tr>
                    <tr><td>Sisa pembayaran</td><td>{{ $money($order['remaining_amount']) }}</td></tr>
                </table>

                <table class="notes">
                    <tr>
                        <td style="width: 25%;"><div class="section-label">Keterangan</div></td>
                        <td class="content">{{ collect([$order['notes'] ?? null, $order['terms'] ?? null])->filter()->join("\n") ?: 'Tidak ada keterangan tambahan.' }}</td>
                    </tr>
                </table>

                <div class="footer">Dokumen pesanan penjualan {{ $order['invoice_number'] }} &middot; YokPrinting.ID</div>
            </section>
        @empty
            <section class="order">
                <div class="top-accent"></div>
                <h1>Pesanan Penjualan</h1>
                <p>Tidak ada pesanan pada periode yang dipilih.</p>
            </section>
        @endforelse
    </body>
</html>
