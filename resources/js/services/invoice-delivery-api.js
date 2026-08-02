export class InvoiceDeliveryApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'InvoiceDeliveryApiError';
        this.status = status;
    }
}

export async function sendInvoiceEmail({ invoiceId }) {
    const response = await fetch(`/api/invoices/${encodeURIComponent(invoiceId)}/send`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({}),
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
