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
                                <h1 style="margin: 8px 0 0; font-size: 24px;">
                                    @if ($notificationStatus === 'overdue')
                                        Invoice lewat jatuh tempo
                                    @else
                                        Pengingat jatuh tempo invoice
                                    @endif
                                </h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 32px;">
                                <p style="margin: 0 0 16px;">Halo {{ $invoice->customer?->name ?? 'Pelanggan' }},</p>
                                <p style="margin: 0 0 24px; line-height: 1.6;">
                                    @if ($notificationStatus === 'overdue')
                                        Invoice {{ $invoice->invoice_number }} sudah melewati tanggal jatuh tempo. Mohon lakukan pembayaran atau hubungi tim kami bila pembayaran sudah diproses.
                                    @else
                                        Invoice {{ $invoice->invoice_number }} akan segera jatuh tempo. Berikut ringkasannya agar pembayaran bisa diproses tepat waktu.
                                    @endif
                                </p>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="border-collapse: collapse; font-size: 14px;">
                                    <tr>
                                        <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Nomor invoice</td>
                                        <td align="right" style="border-bottom: 1px solid #e8e8e1;">{{ $invoice->invoice_number }}</td>
                                    </tr>
                                    <tr>
                                        <td style="border-bottom: 1px solid #e8e8e1; color: #68685f;">Tanggal jatuh tempo</td>
                                        <td align="right" style="border-bottom: 1px solid #e8e8e1;">{{ $invoice->due_date->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-top: 16px; font-weight: 700;">Sisa tagihan</td>
                                        <td align="right" style="padding-top: 16px; font-size: 18px; font-weight: 700;">
                                            {{ $invoice->currency }} {{ number_format($outstandingAmount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </table>

                                <p style="margin: 24px 0 0; padding: 16px; border-radius: 8px; background: #f7f7f3; line-height: 1.6;">
                                    Abaikan pesan ini bila pembayaran sudah dilakukan dan sedang menunggu verifikasi.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
