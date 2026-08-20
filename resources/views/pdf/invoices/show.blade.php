@php
    $yokPrintingLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/yokprinting-logo.png')));
    $yokPrintingAddress = 'Jl. Karyawan II, RT.005/RW.005, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157';
@endphp
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $invoice->invoice_number }}</title>
        <style>
            @page {
                margin: 18mm 15mm 20mm;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                color: #25251f;
                font-family: "DejaVu Sans", sans-serif;
                font-size: 10px;
                line-height: 1.5;
            }

            .header,
            .summary-grid {
                width: 100%;
                border-collapse: collapse;
            }

            .brand {
                color: #154734;
                font-size: 18px;
                font-weight: 700;
                letter-spacing: -.3px;
            }

            .brand-logo {
                display: block;
                width: 150px;
                height: auto;
                margin-bottom: 6px;
            }

            .brand-address {
                max-width: 320px;
                color: #68685f;
                font-size: 8.5px;
                line-height: 1.45;
            }

            .eyebrow {
                margin-bottom: 5px;
                color: #77776d;
                font-size: 8px;
                font-weight: 700;
                letter-spacing: 1.2px;
                text-transform: uppercase;
            }

            .invoice-title {
                margin: 0;
                color: #154734;
                font-size: 26px;
                line-height: 1;
                text-align: right;
            }

            .invoice-number {
                margin-top: 7px;
                color: #4e4e46;
                font-family: monospace;
                font-size: 10px;
                text-align: right;
            }

            .accent {
                height: 4px;
                margin: 17px 0 20px;
                background: #d6a84b;
            }

            .info-grid {
                width: 100%;
                margin-bottom: 22px;
                border-collapse: separate;
                border-spacing: 0;
            }

            .info-grid td {
                width: 50%;
                vertical-align: top;
            }

            .info-grid td:first-child {
                padding-right: 18px;
            }

            .info-grid td:last-child {
                padding-left: 18px;
                border-left: 1px solid #deded5;
            }

            .customer-name {
                margin: 0 0 4px;
                font-size: 13px;
                font-weight: 700;
            }

            .muted {
                color: #68685f;
            }

            .meta-table {
                width: 100%;
                border-collapse: collapse;
            }

            .meta-table td {
                padding: 2px 0;
                border: 0;
            }

            .meta-table td:last-child {
                font-weight: 700;
                text-align: right;
            }

            .items {
                width: 100%;
                border-collapse: collapse;
            }

            .items thead {
                display: table-header-group;
            }

            .items th {
                padding: 9px 8px;
                border-bottom: 2px solid #154734;
                color: #154734;
                font-size: 8px;
                letter-spacing: .7px;
                text-align: left;
                text-transform: uppercase;
            }

            .items td {
                padding: 10px 8px;
                border-bottom: 1px solid #e7e7df;
                vertical-align: top;
            }

            .items tr {
                page-break-inside: avoid;
            }

            .items .numeric {
                text-align: right;
                white-space: nowrap;
            }

            .item-name {
                font-weight: 700;
            }

            .item-meta {
                margin-top: 2px;
                color: #77776d;
                font-size: 8px;
            }

            .summary-wrap {
                width: 44%;
                margin: 18px 0 0 auto;
            }

            .summary-grid td {
                padding: 4px 0;
            }

            .summary-grid td:last-child {
                text-align: right;
                white-space: nowrap;
            }

            .summary-grid .total td {
                padding-top: 9px;
                border-top: 2px solid #154734;
                color: #154734;
                font-size: 13px;
                font-weight: 700;
            }

            .notes {
                margin-top: 24px;
                padding: 13px 15px;
                border: 1px solid #deded5;
                background: #f7f7f3;
                page-break-inside: avoid;
            }

            .notes p {
                margin: 0;
            }

            .notes p + p {
                margin-top: 8px;
            }

            .payment-summary {
                width: 100%;
                margin-top: 22px;
                border-collapse: collapse;
                page-break-inside: avoid;
            }

            .payment-summary td {
                width: 33.333%;
                padding: 12px 14px;
                border: 1px solid #deded5;
                vertical-align: top;
            }

            .payment-summary .label {
                color: #68685f;
                font-size: 8px;
                font-weight: 700;
                letter-spacing: .8px;
                text-transform: uppercase;
            }

            .payment-summary .amount {
                margin-top: 4px;
                color: #154734;
                font-size: 14px;
                font-weight: 700;
            }

            .payment-summary .remaining .amount {
                color: #9b1c1c;
            }

            .production-box,
            .terms-box {
                margin-top: 16px;
                padding: 13px 15px;
                border: 1px solid #deded5;
                background: #fbfbf7;
                page-break-inside: avoid;
            }

            .terms-box ol {
                margin: 7px 0 0;
                padding-left: 16px;
            }

            .terms-box li {
                margin-bottom: 4px;
            }

            .footer {
                position: fixed;
                right: 0;
                bottom: -12mm;
                left: 0;
                color: #85857b;
                font-size: 8px;
                text-align: center;
            }

            .page-number::after {
                content: counter(page);
            }
        </style>
    </head>
    <body>
        <div class="footer">
            YokPrinting.ID &nbsp;|&nbsp; {{ $invoice->invoice_number }} &nbsp;|&nbsp; Halaman <span class="page-number"></span>
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
                    <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                </td>
            </tr>
        </table>

        <div class="accent"></div>

        <table class="info-grid">
            <tr>
                <td>
                    <div class="eyebrow">Ditagihkan kepada</div>
                    <p class="customer-name">{{ $invoice->customer?->name ?? 'Pelanggan' }}</p>
                    @if ($invoice->customer?->email)
                        <div class="muted">{{ $invoice->customer->email }}</div>
                    @endif
                    @if ($invoice->customer?->phone)
                        <div class="muted">{{ $invoice->customer->phone }}</div>
                    @endif
                    @if ($invoice->customer?->address)
                        <div class="muted">{!! nl2br(e($invoice->customer->address)) !!}</div>
                    @endif
                </td>
                <td>
                    <div class="eyebrow">Detail invoice</div>
                    <table class="meta-table">
                        <tr>
                            <td class="muted">Tanggal invoice</td>
                            <td>{{ $invoice->issue_date->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Status</td>
                            <td>{{ strtoupper($invoice->status) }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Produksi</td>
                            <td>{{ $invoice->productionStatusLabel() }}</td>
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
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>
                            <div class="item-name">{{ $item->product_name }}</div>
                            @if ($item->sku)
                                <div class="item-meta">{{ $item->sku }}</div>
                            @endif
                            @if ($item->description)
                                <div class="item-meta">{{ $item->description }}</div>
                            @endif
                            @if ($item->cup_size || $item->cup_model || $item->grammage)
                                <div class="item-meta">
                                    Spec: {{ collect([$item->cup_size, $item->cup_model, $item->grammage, $item->screen_printing_color ? 'Tinta '.$item->screen_printing_color : null, $item->jenis_cetak])->filter()->join(' / ') }}
                                </div>
                            @endif
                        </td>
                        <td class="numeric">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, ',', '.'), '0'), ',') }}</td>
                        <td class="numeric">{{ $invoice->currency }} {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td class="numeric">{{ $invoice->currency }} {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-wrap">
            <table class="summary-grid">
                <tr>
                    <td class="muted">Subtotal</td>
                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</td>
                </tr>
                @if ((float) $invoice->discount_amount > 0)
                    <tr>
                        <td class="muted">Diskon</td>
                        <td>- {{ $invoice->currency }} {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}</td>
                    </tr>
                @endif
                @if ((float) $invoice->tax_amount > 0)
                    <tr>
                        <td class="muted">Pajak ({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2, ',', '.'), '0'), ',') }}%)</td>
                        <td>{{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}</td>
                    </tr>
                @endif
                @if ($invoice->shipping_type === \App\Models\Invoice::SHIPPING_PAID_BY_CUSTOMER && (float) $invoice->shipping_cost > 0)
                    <tr>
                        <td class="muted">Ongkir</td>
                        <td>{{ $invoice->currency }} {{ number_format((float) $invoice->shipping_cost, 0, ',', '.') }}</td>
                    </tr>
                @elseif ($invoice->is_free_shipping || $invoice->shipping_type === \App\Models\Invoice::SHIPPING_COMPANY_FREE_SHIPPING)
                    <tr>
                        <td class="muted">Ongkir</td>
                        <td>Rp0 - Free Ongkir</td>
                    </tr>
                @endif
                <tr class="total">
                    <td>Total</td>
                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <table class="payment-summary">
            <tr>
                <td>
                    <div class="label">Minimal DP produksi</div>
                    <div class="amount">{{ $invoice->currency }} {{ number_format($invoice->requiredDpAmount(), 0, ',', '.') }}</div>
                    <div class="muted">{{ rtrim(rtrim(number_format((float) $invoice->dp_required_percent, 2, ',', '.'), '0'), ',') }}% dari total invoice</div>
                </td>
                <td>
                    <div class="label">DP / pembayaran masuk</div>
                    <div class="amount">{{ $invoice->currency }} {{ number_format($invoice->verifiedPaidAmount(), 0, ',', '.') }}</div>
                    <div class="muted">Hanya pembayaran terverifikasi</div>
                </td>
                <td class="remaining">
                    <div class="label">Sisa tagihan piutang</div>
                    <div class="amount">{{ $invoice->currency }} {{ number_format($invoice->remainingAmount(), 0, ',', '.') }}</div>
                    <div class="muted">Dilunasi sebelum kirim/ambil barang</div>
                </td>
            </tr>
        </table>

        <div class="production-box">
            <p><strong>Rekening pembayaran:</strong> BCA 012 345 6789 a.n. YokPrinting Indonesia. Konfirmasi transfer via WhatsApp admin setelah pembayaran DP atau pelunasan.</p>
            @if ($invoice->design_notes)
                <p style="margin-top: 8px;"><strong>Catatan desain/produksi:</strong> {{ $invoice->design_notes }}</p>
            @endif
            @if ($invoice->mockup_url)
                <p style="margin-top: 8px;"><strong>Mockup:</strong> {{ $invoice->mockup_url }}</p>
            @endif
        </div>

        @if ($invoice->notes || $invoice->terms)
            <div class="notes">
                @if ($invoice->notes)
                    <p><strong>Catatan:</strong> {{ $invoice->notes }}</p>
                @endif
                @if ($invoice->terms)
                    <p><strong>Ketentuan:</strong> {{ $invoice->terms }}</p>
                @endif
            </div>
        @endif

        <div class="terms-box">
            <strong>Syarat & Ketentuan Percetakan</strong>
            <ol>
                <li>Produksi berjalan setelah DP minimal {{ rtrim(rtrim(number_format((float) $invoice->dp_required_percent, 2, ',', '.'), '0'), ',') }}% diterima dan mockup/desain sudah di-ACC.</li>
                <li>Perubahan desain setelah ACC dapat mengubah estimasi waktu produksi dan biaya.</li>
                <li>Selisih warna minor akibat material cup, tinta, dan proses sablon masih dalam toleransi produksi.</li>
                <li>Barang yang sudah sesuai ACC desain tidak dapat diretur kecuali cacat produksi yang terverifikasi.</li>
                <li>Pelunasan wajib diselesaikan sebelum barang dikirim atau diambil.</li>
            </ol>
        </div>
    </body>
</html>
