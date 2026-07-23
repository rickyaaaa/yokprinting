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
                border-left: 3px solid #d6a84b;
                background: #f7f7f3;
                page-break-inside: avoid;
            }

            .notes p {
                margin: 0;
            }

            .notes p + p {
                margin-top: 8px;
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
                    <div class="brand">YokPrinting.ID</div>
                    <div class="muted">Dokumen invoice resmi</div>
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
                            <td class="muted">Jatuh tempo</td>
                            <td>{{ $invoice->due_date->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="muted">Status</td>
                            <td>{{ strtoupper($invoice->status) }}</td>
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
                <tr class="total">
                    <td>Total</td>
                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
                </tr>
            </table>
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
    </body>
</html>
