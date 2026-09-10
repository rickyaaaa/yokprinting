@php
    /**
     * Printed invoice / sales order.
     *
     * Every value comes from BuildInvoiceDocument, which is the only thing that
     * decides what this document says. A stored invoice and an unsaved draft
     * both arrive here in the same shape, so there is nothing to read
     * conditionally and no way for the two to render differently.
     *
     * Laid out as a ruled business form rather than an app screen: monochrome,
     * boxed fields, no accent colour. Dompdf has no flexbox or grid, so the
     * columns are tables.
     */
    $company = $document['company'];
    $customer = $document['customer'];

    $money = fn ($amount): string => 'Rp'.number_format((float) $amount, 0, ',', '.');
    $percent = fn ($amount): string => rtrim(rtrim(number_format((float) $amount, 2, ',', '.'), '0'), ',');

    $discountLabel = ($document['discount_type'] ?? null) === 'percentage' && $document['discount_value'] > 0
        ? 'Diskon ('.$percent($document['discount_value']).'%)'
        : 'Diskon';

    $taxLabel = $document['tax_rate'] > 0
        ? 'PPN ('.$percent($document['tax_rate']).'%)'
        : 'PPN';

    // Only rows with something to say. An empty label on a printed form reads
    // as missing data rather than as absent data.
    $infoRows = collect([
        ['Tanggal', $document['issue_date_label']],
        ['Nomor', $document['number'] ?: null],
        ['Jatuh Tempo', $document['due_date_label']],
        ['Pengiriman', $document['shipping_label']],
        ['Penjual', $document['seller_name']],
    ])->filter(fn (array $row): bool => ! empty($row[1]))->values();

    $showShippingRow = (float) $document['shipping_cost'] > 0 || $document['is_free_shipping'];
    $hasKeterangan = $document['design_notes'] || $document['customer_notes'] || $document['terms'] || $company['bank_account'];
@endphp
<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <title>Invoice {{ $document['number'] }}</title>
        <style>
            @page { margin: 0; }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                padding: 26px 30px 22px;
                color: #111;
                font-family: "DejaVu Sans", sans-serif;
                font-size: 9px;
                line-height: 1.5;
            }

            table { width: 100%; border-collapse: collapse; }
            td, th { vertical-align: top; }

            .header td { padding-bottom: 14px; vertical-align: top; }
            .header .logo { display: block; width: 92px; height: auto; }
            .company-name { font-size: 15px; font-weight: 700; letter-spacing: 0.2px; }
            .company-legal { font-size: 8px; }
            .company-meta { padding-top: 4px; font-size: 8px; line-height: 1.55; }
            .doc-title { font-size: 20px; font-weight: 700; letter-spacing: 1px; text-align: right; }
            .doc-status { padding-top: 3px; font-size: 8px; text-align: right; }

            .rule { height: 1px; background: #111; font-size: 0; line-height: 0; }

            .parties { margin-top: 12px; }
            .parties > tbody > tr > td { padding: 0; }
            .parties .to { width: 52%; padding-right: 18px; }
            .parties .info { width: 48%; }

            .block-label { font-size: 8px; font-weight: 700; letter-spacing: 0.4px; }
            .customer-name { padding-top: 5px; font-size: 11px; font-weight: 700; }
            .customer-line { padding-top: 3px; font-size: 8.5px; line-height: 1.55; }

            .info-grid { border: 1px solid #111; }
            .info-grid td { padding: 5px 8px; border-bottom: 1px solid #111; }
            .info-grid tr:last-child td { border-bottom: 0; }
            .info-grid .k { width: 38%; border-right: 1px solid #111; font-size: 8px; }
            .info-grid .v { font-size: 8.5px; font-weight: 700; }

            .items { margin-top: 14px; border: 1px solid #111; }
            .items thead { display: table-header-group; }
            .items th {
                padding: 6px 8px;
                border-right: 1px solid #111;
                border-bottom: 1px solid #111;
                font-size: 8px;
                font-weight: 700;
                text-align: left;
            }
            .items td { padding: 6px 8px; border-right: 1px solid #111; border-bottom: 1px solid #111; }
            .items th:last-child, .items td:last-child { border-right: 0; }
            .items tbody tr:last-child td { border-bottom: 0; }
            .items tr { page-break-inside: avoid; }
            .items .num { text-align: right; }
            .item-name { font-size: 9px; font-weight: 700; }
            .item-desc { padding-top: 2px; font-size: 7.5px; line-height: 1.45; }

            .terbilang { margin-top: 10px; border: 1px solid #111; }
            .terbilang td { padding: 5px 8px; }
            .terbilang .k { width: 15%; border-right: 1px solid #111; font-size: 8px; font-weight: 700; }
            .terbilang .v { font-size: 8.5px; font-style: italic; }

            .lower { margin-top: 10px; }
            .lower > tbody > tr > td { padding: 0; }
            .lower .left { width: 55%; padding-right: 14px; }
            .lower .right { width: 45%; }

            .keterangan { border: 1px solid #111; }
            .keterangan td { padding: 6px 8px; }
            .note-label { font-size: 7.5px; font-weight: 700; letter-spacing: 0.3px; }
            .note-body { padding-top: 1px; font-size: 8px; line-height: 1.5; }
            .note-group + .note-group { padding-top: 7px; }

            .totals { border: 1px solid #111; }
            .totals td { padding: 5px 8px; border-bottom: 1px solid #111; }
            .totals tr:last-child td { border-bottom: 0; }
            .totals .k { width: 55%; border-right: 1px solid #111; font-size: 8.5px; }
            .totals .v { font-size: 8.5px; font-weight: 700; text-align: right; }
            .totals .grand td { border-top: 1px solid #111; font-size: 10px; font-weight: 700; }
            .totals .grand .k { font-weight: 700; }

            .dp { margin-top: 6px; border: 1px solid #111; }
            .dp td { padding: 5px 8px; font-size: 8.5px; font-weight: 700; }
            .dp .v { text-align: right; }

            .approval { margin-top: 26px; }
            .approval td { width: 33%; font-size: 8.5px; }
            .approval .sign-space { height: 46px; }
            .sign-rule { border-top: 1px solid #111; padding-top: 3px; font-size: 8px; }

            .foot { margin-top: 18px; font-size: 7px; line-height: 1.4; }
        </style>
    </head>
    <body>
        {{-- Header: identity on the left, document title on the right. --}}
        <table class="header">
            <tr>
                <td style="width: 62%;">
                    <table>
                        <tr>
                            @if ($company['logo_data_uri'])
                                <td style="width: 100px; padding: 0 10px 0 0; vertical-align: middle;">
                                    <img class="logo" src="{{ $company['logo_data_uri'] }}" alt="{{ $company['name'] }}">
                                </td>
                            @endif
                            <td style="padding: 0; vertical-align: middle;">
                                <div class="company-name">{{ $company['name'] }}</div>
                                @if ($company['legal_name'] && $company['legal_name'] !== $company['name'])
                                    <div class="company-legal">{{ $company['legal_name'] }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                    <div class="company-meta">
                        @if ($company['address'])
                            {{ $company['address'] }}<br>
                        @endif
                        {{ collect([$company['phone'], $company['email'], $company['website']])->filter()->join(' · ') }}
                        @if ($company['tax_number'])
                            <br>NPWP: {{ $company['tax_number'] }}
                        @endif
                    </div>
                </td>
                <td style="width: 38%;">
                    <div class="doc-title">INVOICE</div>
                    <div class="doc-status">{{ $document['status_label'] }}</div>
                </td>
            </tr>
        </table>
        <div class="rule"></div>

        {{-- Customer on the left, transaction fields boxed on the right. --}}
        <table class="parties">
            <tr>
                <td class="to">
                    <div class="block-label">KEPADA</div>
                    <div class="customer-name">{{ $customer['name'] ?? 'Pelanggan belum dipilih' }}</div>
                    @if ($customer['address'])
                        <div class="customer-line">{{ $customer['address'] }}</div>
                    @endif
                    @php
                        $contact = collect([$customer['phone'], $customer['email']])->filter()->join(' · ');
                    @endphp
                    @if ($contact)
                        <div class="customer-line">{{ $contact }}</div>
                    @endif
                </td>
                <td class="info">
                    <table class="info-grid">
                        @foreach ($infoRows as $row)
                            <tr>
                                <td class="k">{{ $row[0] }}</td>
                                <td class="v">{{ $row[1] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        </table>

        {{-- Item table. --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 14%;">Kode Barang</th>
                    <th style="width: 38%;">Nama Barang</th>
                    <th class="num" style="width: 10%;">Kts.</th>
                    <th class="num" style="width: 13%;">@Harga</th>
                    <th class="num" style="width: 11%;">Diskon</th>
                    <th class="num" style="width: 14%;">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($document['items'] as $item)
                    <tr>
                        <td>{{ $item['code'] ?? '-' }}</td>
                        {{-- Product name only. The generated "Sablon 12 Oz
                             Datar (8gr) …" description is working detail that
                             the client already decided stays on the in-app
                             Rincian tagihan; the new layout adds a Kode Barang
                             column but does not reopen that. It is carried in
                             the view-model, so showing it is a one-line change
                             if that decision ever turns around. --}}
                        <td><div class="item-name">{{ $item['name'] }}</div></td>
                        <td class="num">{{ $item['quantity_label'] }}{{ $item['unit'] ? ' '.$item['unit'] : '' }}</td>
                        <td class="num">{{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($item['discount_amount'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($item['line_total'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Belum ada item.</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- The grand total in words, directly under the table as in the reference. --}}
        <table class="terbilang">
            <tr>
                <td class="k">Terbilang</td>
                <td class="v">{{ $document['total_in_words'] }}</td>
            </tr>
        </table>

        {{-- Notes on the left, totals on the right. --}}
        <table class="lower">
            <tr>
                <td class="left">
                    @if ($hasKeterangan)
                        <table class="keterangan">
                            <tr>
                                <td>
                                    <div class="block-label" style="padding-bottom: 4px;">KETERANGAN</div>

                                    {{-- Design notes are internal production
                                         instructions; customer notes are
                                         addressed outward. They are labelled
                                         separately so neither can be mistaken
                                         for the other. --}}
                                    @if ($document['design_notes'])
                                        <div class="note-group">
                                            <div class="note-label">Catatan desain/produksi:</div>
                                            <div class="note-body">{!! nl2br(e($document['design_notes'])) !!}</div>
                                        </div>
                                    @endif

                                    @if ($document['customer_notes'])
                                        <div class="note-group">
                                            <div class="note-label">Catatan untuk pelanggan:</div>
                                            <div class="note-body">{!! nl2br(e($document['customer_notes'])) !!}</div>
                                        </div>
                                    @endif

                                    @if ($document['terms'])
                                        <div class="note-group">
                                            <div class="note-label">Syarat &amp; ketentuan:</div>
                                            <div class="note-body">{!! nl2br(e($document['terms'])) !!}</div>
                                        </div>
                                    @endif

                                    @if ($company['bank_account'])
                                        <div class="note-group">
                                            <div class="note-label">Pembayaran:</div>
                                            <div class="note-body">
                                                {{ collect([$company['bank_name'], $company['bank_account']])->filter()->join(' ') }}
                                                @if ($company['bank_holder'])
                                                    <br>a.n. {{ $company['bank_holder'] }}
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    @endif
                </td>
                <td class="right">
                    <table class="totals">
                        <tr>
                            <td class="k">Sub Total</td>
                            <td class="v">{{ $money($document['subtotal']) }}</td>
                        </tr>
                        <tr>
                            <td class="k">{{ $discountLabel }}</td>
                            <td class="v">{{ $document['discount_amount'] > 0 ? '- '.$money($document['discount_amount']) : $money(0) }}</td>
                        </tr>
                        <tr>
                            <td class="k">{{ $taxLabel }}</td>
                            <td class="v">{{ $money($document['tax_amount']) }}</td>
                        </tr>
                        @if ($showShippingRow)
                            <tr>
                                <td class="k">{{ $document['is_free_shipping'] ? 'Ongkir (gratis)' : 'Ongkir' }}</td>
                                <td class="v">{{ $document['is_free_shipping'] ? '- '.$money($document['shipping_cost']) : $money($document['shipping_cost']) }}</td>
                            </tr>
                        @endif
                        <tr class="grand">
                            <td class="k">Total</td>
                            <td class="v">{{ $money($document['total_amount']) }}</td>
                        </tr>
                    </table>

                    @if ($document['dp_amount'] > 0)
                        <table class="dp">
                            <tr>
                                <td>Minimal DP {{ $percent($document['dp_required_percent']) }}%</td>
                                <td class="v">{{ $money($document['dp_amount']) }}</td>
                            </tr>
                        </table>
                    @endif
                </td>
            </tr>
        </table>

        {{-- Printable approval area. No workflow behind it. --}}
        <table class="approval">
            <tr>
                <td>
                    <div>Disetujui,</div>
                    <div class="sign-space"></div>
                    <div class="sign-rule">Nama / Tanggal</div>
                </td>
                <td></td>
                <td>
                    <div>Hormat kami,</div>
                    <div class="sign-space"></div>
                    <div class="sign-rule">{{ $document['seller_name'] ?? $company['name'] }}</div>
                </td>
            </tr>
        </table>

        <div class="foot">
            {{ $company['name'] }}@if ($company['address']) · {{ $company['address'] }}@endif
        </div>
    </body>
</html>
