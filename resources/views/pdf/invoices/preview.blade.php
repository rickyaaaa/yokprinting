@php
    $yokPrintingLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/yokprinting-logo.png')));
    $yokPrintingAddress = 'Jl. Karyawan II, RT.005/RW.005, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157';
    $currency = $preview['currency'] ?? 'IDR';
    $money = fn ($amount) => $currency.' '.number_format((float) $amount, 0, ',', '.');
    $percent = fn ($amount) => rtrim(rtrim(number_format((float) $amount, 2, ',', '.'), '0'), ',');
    $customer = $preview['customer'] ?? [];
@endphp
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $preview['invoice_number'] }}</title>
        <style>
            @page { margin: 18mm 15mm 20mm; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                color: #25251f;
                font-family: "DejaVu Sans", sans-serif;
                font-size: 10px;
                line-height: 1.5;
            }
            .header, .info-grid, .items, .summary-grid, .payment-summary { width: 100%; border-collapse: collapse; }
            .brand-logo { display: block; width: 150px; height: auto; margin-bottom: 6px; }
            .brand { color: #154734; font-size: 18px; font-weight: 700; letter-spacing: -.3px; }
            .brand-address, .muted { color: #68685f; }
            .brand-address { max-width: 320px; font-size: 8.5px; line-height: 1.45; }
            .invoice-title { margin: 0; color: #154734; font-size: 26px; line-height: 1; text-align: right; }
            .invoice-number { margin-top: 7px; color: #4e4e46; font-family: monospace; font-size: 10px; text-align: right; }
            .accent { height: 4px; margin: 17px 0 20px; background: #d6a84b; }
            .eyebrow { margin-bottom: 5px; color: #77776d; font-size: 8px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; }
            .info-grid { margin-bottom: 22px; border-collapse: separate; border-spacing: 0; }
            .info-grid td { width: 50%; vertical-align: top; }
            .info-grid td:first-child { padding-right: 18px; }
            .info-grid td:last-child { padding-left: 18px; border-left: 1px solid #deded5; }
            .customer-name { margin: 0 0 4px; font-size: 13px; font-weight: 700; }
            .meta-table { width: 100%; border-collapse: collapse; }
            .meta-table td { padding: 2px 0; border: 0; }
            .meta-table td:last-child { font-weight: 700; text-align: right; }
            .items thead { display: table-header-group; }
            .items th { padding: 9px 8px; border-bottom: 2px solid #154734; color: #154734; font-size: 8px; letter-spacing: .7px; text-align: left; text-transform: uppercase; }
            .items td { padding: 10px 8px; border-bottom: 1px solid #e7e7df; vertical-align: top; }
            .items tr { page-break-inside: avoid; }
            .numeric { text-align: right; white-space: nowrap; }
            .item-name { font-weight: 700; }
            .item-note { margin-top: 2px; color: #77776d; font-size: 8px; }
            .summary-wrap { width: 44%; margin: 18px 0 0 auto; }
            .summary-grid td { padding: 4px 0; }
            .summary-grid td:last-child { text-align: right; white-space: nowrap; }
            .summary-grid .total td { padding-top: 9px; border-top: 2px solid #154734; color: #154734; font-size: 13px; font-weight: 700; }
            .payment-summary { margin-top: 22px; page-break-inside: avoid; }
            .payment-summary td { width: 50%; padding: 12px 14px; border: 1px solid #deded5; vertical-align: top; }
            .label { color: #68685f; font-size: 8px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
            .amount { margin-top: 4px; color: #154734; font-size: 14px; font-weight: 700; }
            .notes, .terms-box { margin-top: 16px; padding: 13px 15px; border: 1px solid #deded5; background: #fbfbf7; page-break-inside: avoid; }
            .notes p { margin: 0; }
            .notes p + p { margin-top: 8px; }
            .terms-box ol { margin: 7px 0 0; padding-left: 16px; }
            .terms-box li { margin-bottom: 4px; }
            .footer { position: fixed; right: 0; bottom: -12mm; left: 0; color: #85857b; font-size: 8px; text-align: center; }
            .page-number::after { content: counter(page); }
        </style>
    </head>
    <body>
        <div class="footer">
            YokPrinting.ID | {{ $preview['invoice_number'] }} | Halaman <span class="page-number"></span>
        </div>

        <table class="header">
            <tr>
                <td>
                    <img class="brand-logo" src="{{ $yokPrintingLogo }}" alt="YokPrinting.ID">
                    <div class="brand">YokPrinting.ID</div>
                    <div class="brand-address">{{ $yokPrintingAddress }}</div>
                    <div class="muted">Sablon cup & cetak kemasan F&B</div>
                </td>
                <td>
                    <h1 class="invoice-title">INVOICE</h1>
                    <div class="invoice-number">{{ $preview['invoice_number'] }}</div>
                </td>
            </tr>
        </table>

        <div class="accent"></div>

        <table class="info-grid">
            <tr>
                <td>
                    <div class="eyebrow">Ditagihkan kepada</div>
                    <p class="customer-name">{{ $customer['name'] ?? 'Pelanggan' }}</p>
                    @if (! empty($customer['email']))
                        <div class="muted">{{ $customer['email'] }}</div>
                    @endif
                    @if (! empty($customer['phone']))
                        <div class="muted">{{ $customer['phone'] }}</div>
                    @endif
                    @if (! empty($customer['address']))
                        <div class="muted">{!! nl2br(e($customer['address'])) !!}</div>
                    @endif
                </td>
                <td>
                    <div class="eyebrow">Detail invoice</div>
                    <table class="meta-table">
                        <tr>
                            <td class="muted">Tanggal invoice</td>
                            <td>{{ $preview['issue_date_label'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Status</td>
                            <td>DRAFT</td>
                        </tr>
                        <tr>
                            <td class="muted">Mata uang</td>
                            <td>{{ $currency }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 44%;">Produk / layanan</th>
                    <th class="numeric" style="width: 12%;">Qty</th>
                    <th class="numeric" style="width: 21%;">Harga</th>
                    <th class="numeric" style="width: 23%;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($preview['items'] as $item)
                    <tr>
                        <td>
                            <div class="item-name">{{ $item['name'] }}</div>
                            @if (! empty($item['note']))
                                <div class="item-note">{{ $item['note'] }}</div>
                            @endif
                        </td>
                        <td class="numeric">{{ $item['quantity_label'] }}</td>
                        <td class="numeric">{{ $money($item['unit_price']) }}</td>
                        <td class="numeric">{{ $money($item['line_total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-wrap">
            <table class="summary-grid">
                <tr>
                    <td class="muted">Subtotal</td>
                    <td>{{ $money($preview['subtotal']) }}</td>
                </tr>
                @if ((float) ($preview['discount_amount'] ?? 0) > 0)
                    <tr>
                        <td class="muted">Diskon</td>
                        <td>- {{ $money($preview['discount_amount']) }}</td>
                    </tr>
                @endif
                @if ((float) ($preview['tax_amount'] ?? 0) > 0)
                    <tr>
                        <td class="muted">PPN ({{ $percent($preview['tax_rate'] ?? 0) }}%)</td>
                        <td>{{ $money($preview['tax_amount']) }}</td>
                    </tr>
                @endif
                @if (! ($preview['is_free_shipping'] ?? false) && (float) ($preview['shipping_cost'] ?? 0) > 0)
                    <tr>
                        <td class="muted">Ongkir</td>
                        <td>{{ $money($preview['shipping_cost']) }}</td>
                    </tr>
                @endif
                <tr class="total">
                    <td>Total</td>
                    <td>{{ $money($preview['total_amount']) }}</td>
                </tr>
            </table>
        </div>

        <table class="payment-summary">
            <tr>
                <td>
                    <div class="label">Minimal DP produksi</div>
                    <div class="amount">{{ $money($preview['dp_amount'] ?? 0) }}</div>
                    <div class="muted">{{ $percent($preview['dp_required_percent'] ?? 0) }}% dari total invoice</div>
                </td>
                <td>
                    <div class="label">Sisa setelah DP</div>
                    <div class="amount">{{ $money($preview['remaining_amount']) }}</div>
                    <div class="muted">Grand total dikurangi minimal DP</div>
                </td>
            </tr>
        </table>

        @if (! empty($preview['notes']) || ! empty($preview['terms']))
            <div class="notes">
                @if (! empty($preview['notes']))
                    <p><strong>Catatan:</strong> {{ $preview['notes'] }}</p>
                @endif
                @if (! empty($preview['terms']))
                    <p><strong>Ketentuan:</strong> {{ $preview['terms'] }}</p>
                @endif
            </div>
        @endif

        <div class="terms-box">
            <strong>Syarat & Ketentuan Percetakan</strong>
            <ol>
                <li>Produksi berjalan setelah DP minimal {{ $percent($preview['dp_required_percent'] ?? 0) }}% diterima dan mockup/desain sudah di-ACC.</li>
                <li>Perubahan desain setelah ACC dapat mengubah estimasi waktu produksi dan biaya.</li>
                <li>Selisih warna minor akibat material cup, tinta, dan proses sablon masih dalam toleransi produksi.</li>
                <li>Barang yang sudah sesuai ACC desain tidak dapat diretur kecuali cacat produksi yang terverifikasi.</li>
                <li>Pelunasan wajib diselesaikan sebelum barang dikirim atau diambil.</li>
            </ol>
        </div>
    </body>
</html>
