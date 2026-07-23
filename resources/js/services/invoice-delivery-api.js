const API_MODE = import.meta.env.VITE_INVOICE_DELIVERY_API_MODE ?? 'mock';

export class InvoiceDeliveryApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'InvoiceDeliveryApiError';
        this.status = status;
    }
}

const mockSendInvoiceEmail = ({ invoiceId, recipient }) =>
    new Promise((resolve, reject) => {
        window.setTimeout(() => {
            if (!invoiceId || !recipient) {
                reject(new InvoiceDeliveryApiError(
                    'Nomor invoice dan email penerima wajib tersedia.',
                    422,
                ));
                return;
            }

            resolve({
                data: {
                    invoice_id: invoiceId,
                    recipient,
                    status: 'sent',
                    message_id: 'mail-demo-inv-0079',
                    sent_at: new Date().toISOString(),
                },
            });
        }, 600);
    });

export async function sendInvoiceEmail({ invoiceId, recipient }) {
    if (API_MODE !== 'live') {
        return mockSendInvoiceEmail({ invoiceId, recipient });
    }

    const response = await fetch(`/api/invoices/${encodeURIComponent(invoiceId)}/send`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ recipient }),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new InvoiceDeliveryApiError(
            body.message ?? 'Invoice belum dapat dikirim. Coba lagi.',
            response.status,
        );
    }

    return body;
}
