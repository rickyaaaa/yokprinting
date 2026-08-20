@php
    $yokPrintingLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/yokprinting-logo.png')));
    $yokPrintingAddress = 'Jl. Karyawan II, RT.005/RW.005, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157';
    $currency = $preview['currency'] ?? 'IDR';
    $money = fn ($amount): string => 'Rp'.number_format((float) $amount, 0, ',', '.');
    $percent = fn ($amount): string => rtrim(rtrim(number_format((float) $amount, 2, ',', '.'), '0'), ',');
    $customer = $preview['customer'] ?? [];
    $statusLabel = $preview['status_label'] ?? 'Draft';
    $discountLabel = ($preview['discount_type'] ?? 'percentage') === 'percentage'
        ? 'Diskon ('.$percent($preview['discount_value'] ?? 0).'%)'
        : 'Diskon';
    $taxLabel = ($preview['tax_enabled'] ?? false)
        ? 'PPN ('.$percent($preview['tax_rate'] ?? 0).'%)'
        : 'PPN';
@endphp
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $preview['invoice_number'] }}</title>
        <style>
            @page { margin: 0; }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                color: #102a4e;
                font-family: "DejaVu Sans", sans-serif;
                font-size: 9px;
                line-height: 1.55;
            }

            .top-accent { height: 5px; background: #2758d6; }
            .page { padding: 28px 28px 20px; }
            .header, .details, .items, .payment-grid, .footer { width: 100%; border-collapse: collapse; }
            .header { border-bottom: 1px solid #d6dfeb; }
            .header td { padding-bottom: 20px; vertical-align: top; }
            .brand-lockup { width: 100%; border-collapse: collapse; }
            .brand-lockup td { padding: 0; vertical-align: middle; }
            .logo-box { width: 128px; padding: 8px; border: 1px solid #d6dfeb; border-radius: 5px; }
            .logo { display: block; width: 112px; height: auto; }
            .brand-name { padding-left: 12px; color: #102a4e; font-size: 12px; font-weight: 700; }
            .brand-subtitle { padding-left: 12px; color: #516781; font-size: 7px; }
            .address { padding-top: 13px; color: #516781; font-size: 8px; line-height: 1.6; }
            .invoice-side { text-align: right; }
            .invoice-title { color: #214bc4; font-size: 20px; font-weight: 700; line-height: 1; }
            .invoice-number { padding-top: 7px; color: #102a4e; font-family: monospace; font-size: 9px; font-weight: 700; }
            .badge { display: inline-block; margin-top: 7px; padding: 3px 10px; border-radius: 12px; background: #e4edff; color: #2854c6; font-size: 8px; font-weight: 700; }
            .details { border-bottom: 1px solid #d6dfeb; }
            .details td { padding: 20px 0; vertical-align: top; }
            .details td:first-child { width: 58%; padding-right: 24px; }
            .details td:last-child { width: 42%; }
            .section-label { color: #384e69; font-size: 8px; font-weight: 700; }
            .customer-name { margin-top: 6px; color: #102a4e; font-size: 13px; font-weight: 700; }
            .customer-address { margin-top: 6px; color: #516781; font-size: 8.5px; line-height: 1.65; }
            .meta { width: 100%; border-collapse: collapse; }
            .meta td { padding: 0 0 12px; }
            .meta td:first-child { width: 58%; color: #516781; }
            .meta td:last-child { color: #102a4e; font-weight: 700; text-align: right; }
            .items { margin-top: 20px; }
            .items thead { display: table-header-group; }
            .items th { padding: 0 0 8px; border-bottom: 2px solid #2854c6; color: #384e69; font-size: 8px; font-weight: 700; text-align: left; }
            .items th.numeric, .items td.numeric { text-align: right; }
            .items th.center, .items td.center { text-align: center; }
            .items td { padding: 13px 0; border-bottom: 1px solid #e0e6ef; vertical-align: top; }
            .items tr { page-break-inside: avoid; }
            .item-name { color: #102a4e; font-size: 9px; font-weight: 700; }
            .item-note { margin-top: 4px; color: #516781; font-size: 7.5px; }
            .muted { color: #516781; }
            .summary { width: 47%; margin: 17px 0 0 auto; border-collapse: collapse; }
            .summary td { padding: 3px 0; }
            .summary td:last-child { color: #102a4e; font-weight: 700; text-align: right; }
            .summary .discount td:last-child { color: #d11b3b; }
            .summary .total td { padding-top: 10px; border-top: 2px solid #2854c6; color: #102a4e; font-size: 10px; font-weight: 700; }
            .summary .total td:last-child { color: #214bc4; font-size: 14px; }
            .dp { width: 47%; margin: 8px 0 0 auto; border-collapse: collapse; background: #eaf2ff; }
            .dp td { padding: 7px 10px; color: #2046ad; font-size: 8px; font-weight: 700; }
            .dp td:last-child { text-align: right; }
            .bottom { margin-top: 26px; padding-top: 20px; border-top: 1px solid #d6dfeb; }
            .payment-grid td { width: 50%; vertical-align: top; }
            .payment-grid td:first-child { padding-right: 14px; }
            .payment-grid td:last-child { padding-left: 14px; }
            .payment-box { margin-top: 8px; padding: 10px; background: #f2f6ff; }
            .payment-box table { width: 100%; border-collapse: collapse; }
            .payment-box td { padding: 2px 0; color: #516781; font-size: 8px; }
            .payment-box td:last-child { color: #102a4e; font-weight: 700; text-align: right; }
            .notes { margin-top: 8px; color: #516781; font-size: 8px; line-height: 1.7; }
            .notes p { margin: 0; }
            .notes p + p { margin-top: 6px; }
            .footer { margin-top: 22px; border-top: 1px solid #d6dfeb; }
            .footer td { padding-top: 12px; color: #516781; font-size: 7px; line-height: 1.4; vertical-align: top; }
            .footer td:first-child { width: 36%; padding-right: 12px; }
            .footer td:last-child { color: #2854c6; font-weight: 700; }
        </style>
    </head>
    <body>
        <div class="top-accent"></div>
        <main class="page">
            <table class="header">
                <tr>
                    <td>
                        <table class="brand-lockup">
                            <tr>
                                <td class="logo-box"><img class="logo" src="{{ $yokPrintingLogo }}" alt="YokPrinting.ID"></td>
                                <td>
                                    <div class="brand-name">YokPrinting.ID</div>
                                    <div class="brand-subtitle">Sablon cup &amp; cetak kemasan F&amp;B</div>
                                </td>
                            </tr>
                        </table>
                        <div class="address">
                            {{ $yokPrintingAddress }}<br>
                            admin@yokprinting.id &middot; @yokprinting.id
                        </div>
                    </td>
                    <td class="invoice-side">
                        <div class="invoice-title">INVOICE</div>
                        <div class="invoice-number">{{ $preview['invoice_number'] }}</div>
                        <span class="badge">{{ $statusLabel }}</span>
                    </td>
                </tr>
            </table>

            <table class="details">
                <tr>
                    <td>
                        <div class="section-label">Ditagihkan kepada</div>
                        <div class="customer-name">{{ $customer['name'] ?? 'Pelanggan belum dipilih' }}</div>
                        <div class="customer-address">
                            {{ $customer['address'] ?? '-' }}<br>
                            {{ collect([$customer['email'] ?? null, $customer['phone'] ?? null])->filter()->join(' · ') ?: '-' }}
                        </div>
                    </td>
                    <td>
                        <table class="meta">
                            <tr>
                                <td>Tanggal invoice</td>
                                <td>{{ $preview['issue_date_label'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Mata uang</td>
                                <td>{{ $currency }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 57%;">Deskripsi</th>
                        <th class="center" style="width: 13%;">Jumlah</th>
                        <th class="numeric" style="width: 15%;">Harga</th>
                        <th class="numeric" style="width: 15%;">Total</th>
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
                            <td class="center">{{ $item['quantity_label'] }}</td>
                            <td class="numeric muted">{{ $money($item['unit_price']) }}</td>
                            <td class="numeric">{{ $money($item['line_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="summary">
                <tr><td class="muted">Subtotal</td><td>{{ $money($preview['subtotal']) }}</td></tr>
                <tr class="discount"><td>{{ $discountLabel }}</td><td>- {{ $money($preview['discount_amount']) }}</td></tr>
                <tr><td class="muted">{{ $taxLabel }}</td><td>{{ $money($preview['tax_amount']) }}</td></tr>
                @if ((float) ($preview['shipping_cost'] ?? 0) > 0 || ($preview['is_free_shipping'] ?? false))
                    <tr><td class="muted">{{ ($preview['is_free_shipping'] ?? false) ? 'Free ongkir' : 'Ongkir' }}</td><td>{{ $money($preview['shipping_cost']) }}</td></tr>
                @endif
                <tr class="total"><td>Total tagihan</td><td>{{ $money($preview['total_amount']) }}</td></tr>
            </table>
            <table class="dp">
                <tr>
                    <td>Minimal DP {{ $percent($preview['dp_required_percent'] ?? 0) }}%</td>
                    <td>{{ $money($preview['dp_amount'] ?? 0) }}</td>
                </tr>
            </table>

            <div class="bottom">
                <table class="payment-grid">
                    <tr>
                        <td>
                            <div class="section-label">Instruksi pembayaran</div>
                            <div class="payment-box">
                                <table>
                                    <tr><td>Bank</td><td>Bank Central Asia</td></tr>
                                    <tr><td>No. rekening</td><td>012 345 6789</td></tr>
                                    <tr><td>Atas nama</td><td>YokPrinting Indonesia</td></tr>
                                </table>
                            </div>
                        </td>
                        <td>
                            <div class="section-label">Catatan</div>
                            <div class="notes">
                                @if (! empty($preview['notes']))
                                    <p>{{ $preview['notes'] }}</p>
                                @endif
                                @if (! empty($preview['terms']))
                                    <p>{{ $preview['terms'] }}</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="footer">
                <tr>
                    <td>Invoice ini dibuat secara elektronik dan sah tanpa tanda tangan.</td>
                    <td>YokPrinting.ID &middot; {{ $yokPrintingAddress }}</td>
                </tr>
            </table>
        </main>
    </body>
</html>
