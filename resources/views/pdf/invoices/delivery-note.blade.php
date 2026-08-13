@php
    $yokPrintingLogo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/yokprinting-logo.png')));
    $yokPrintingAddress = 'Jl. Karyawan II, RT.005/RW.005, Karang Tengah, Kec. Karang Tengah, Kota Tangerang, Banten 15157';
    $customerAddress = $invoice->customer?->address ?? $invoice->customer_address;
    $customerPhone = $invoice->customer?->phone ?? $invoice->customer_phone;
    $customerName = $invoice->customer?->name ?? $invoice->customer_name ?? 'Pelanggan';
@endphp
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Surat Jalan {{ $invoice->deliveryNoteNumber() }}</title>
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

            .header {
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

            .doc-title {
                margin: 0;
                color: #154734;
                font-size: 26px;
                line-height: 1;
                text-align: right;
            }

            .doc-number {
                margin-top: 7px;
                color: #4e4e46;
                font-family: monospace;
                font-size: 11px;
                font-weight: 700;
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
                margin-bottom: 24px;
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

            .signatures {
                width: 100%;
                margin-top: 40px;
                border-collapse: collapse;
                page-break-inside: avoid;
            }

            .signatures td {
                width: 33.333%;
                text-align: center;
                vertical-align: top;
            }

            .signature-title {
                margin-bottom: 60px;
                color: #4e4e46;
                font-size: 9px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .signature-line {
                font-weight: 700;
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
            YokPrinting.ID &nbsp;|&nbsp; Surat Jalan {{ $invoice->deliveryNoteNumber() }} &nbsp;|&nbsp; Halaman <span class="page-number"></span>
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
                    <h1 class="doc-title">SURAT JALAN</h1>
                    <div class="doc-number">{{ $invoice->deliveryNoteNumber() }}</div>
                </td>
            </tr>
        </table>

        <div class="accent"></div>

        <table class="info-grid">
            <tr>
                <td>
                    <div class="eyebrow">Penerima / Tujuan Pengiriman</div>
                    <p class="customer-name">{{ $customerName }}</p>
                    @if ($customerPhone)
                        <div class="muted">Telp/WA: {{ $customerPhone }}</div>
                    @endif
                    @if ($customerAddress)
                        <div class="muted">{!! nl2br(e($customerAddress)) !!}</div>
                    @endif
                </td>
                <td>
                    <div class="eyebrow">Detail Pengiriman</div>
                    <table class="meta-table">
                        <tr>
                            <td class="muted">No. Surat Jalan</td>
                            <td>{{ $invoice->deliveryNoteNumber() }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Ref. Invoice</td>
                            <td>{{ $invoice->invoice_number }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Tanggal</td>
                            <td>{{ ($invoice->issue_date ?? now())->format('d M Y') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width: 8%;">No</th>
                    <th style="width: 62%;">Nama Produk / Spesifikasi</th>
                    <th class="numeric" style="width: 15%;">Jumlah</th>
                    <th class="numeric" style="width: 15%;">Satuan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <div class="item-name">{{ $item->product_name }}</div>
                            @if ($item->sku)
                                <div class="item-meta">SKU: {{ $item->sku }}</div>
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
                        <td class="numeric">Pcs</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-title">Penerima Barang,</div>
                    <div class="signature-line">( {{ $customerName }} )</div>
                </td>
                <td>
                    <div class="signature-title">Pengirim / Kurir,</div>
                    <div class="signature-line">( ........................ )</div>
                </td>
                <td>
                    <div class="signature-title">Hormat Kami,</div>
                    <div class="signature-line">( YokPrinting.ID )</div>
                </td>
            </tr>
        </table>
    </body>
</html>
