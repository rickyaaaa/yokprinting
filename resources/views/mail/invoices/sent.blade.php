<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $invoice->invoice_number }}</title>
    </head>
    <body style="margin: 0; background: #f5f5f0; color: #25251f; font-family: Arial, sans-serif;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f5f5f0; padding: 32px 16px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 640px; overflow: hidden; border: 1px solid #deded5; border-radius: 12px; background: #ffffff;">
                        <tr>
                            <td style="padding: 28px 32px; background: #154734; color: #ffffff;">
                                <div style="font-size: 13px; letter-spacing: .08em; text-transform: uppercase;">YokPrinting.ID</div>
                                <h1 style="margin: 8px 0 0; font-size: 24px;">Invoice {{ $invoice->invoice_number }}</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 32px;">
                                <p style="margin: 0 0 16px;">Halo {{ $invoice->customer?->name ?? 'Pelanggan' }},</p>
                                <p style="margin: 0 0 24px; line-height: 1.6;">Berikut adalah invoice yang tersimpan pada sistem kami.</p>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="margin-bottom: 24px; border-collapse: collapse; font-size: 13px;">
                                    <thead>
                                        <tr>
                                            <th align="left" style="border-bottom: 2px solid #154734; color: #154734;">Produk</th>
                                            <th align="right" style="border-bottom: 2px solid #154734; color: #154734;">Qty</th>
                                            <th align="right" style="border-bottom: 2px solid #154734; color: #154734;">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($invoice->items as $item)
                                            <tr>
                                                <td style="border-bottom: 1px solid #e8e8e1;">{{ $item->product_name }}</td>
                                                <td align="right" style="border-bottom: 1px solid #e8e8e1;">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                                                <td align="right" style="border-bottom: 1px solid #e8e8e1;">
                                                    {{ $invoice->currency }} {{ number_format((float) $item->total_amount, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse: collapse; font-size: 14px;">
                                    <tr>
                                        <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Tanggal invoice</td>
                                        <td align="right" style="border-bottom: 1px solid #e8e8e1;">{{ $invoice->issue_date->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Jatuh tempo</td>
                                        <td align="right" style="border-bottom: 1px solid #e8e8e1;">{{ $invoice->due_date->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Subtotal</td>
                                        <td align="right" style="border-bottom: 1px solid #e8e8e1;">
                                            {{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                    @if ((float) $invoice->discount_amount > 0)
                                        <tr>
                                            <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Diskon</td>
                                            <td align="right" style="border-bottom: 1px solid #e8e8e1;">
                                                - {{ $invoice->currency }} {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif
                                    @if ((float) $invoice->tax_amount > 0)
                                        <tr>
                                            <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Pajak</td>
                                            <td align="right" style="border-bottom: 1px solid #e8e8e1;">
                                                {{ $invoice->currency }} {{ number_format((float) $invoice->tax_amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif
                                    @if (! $invoice->is_free_shipping && (float) $invoice->shipping_cost > 0)
                                        <tr>
                                            <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Ongkir</td>
                                            <td align="right" style="border-bottom: 1px solid #e8e8e1;">
                                                {{ $invoice->currency }} {{ number_format((float) $invoice->shipping_cost, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td style="padding-top: 16px; font-weight: 700;">Total</td>
                                        <td align="right" style="padding-top: 16px; font-size: 18px; font-weight: 700;">
                                            {{ $invoice->currency }} {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </table>

                                @if ($invoice->notes)
                                    <p style="margin: 24px 0 0; padding: 16px; border-radius: 8px; background: #f7f7f3; line-height: 1.6;">
                                        {{ $invoice->notes }}
                                    </p>
                                @endif

                                @if ($invoice->terms)
                                    <p style="margin: 12px 0 0; padding: 16px; border-radius: 8px; background: #f7f7f3; line-height: 1.6;">
                                        <strong>Ketentuan:</strong> {{ $invoice->terms }}
                                    </p>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
