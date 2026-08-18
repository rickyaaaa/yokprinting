export class InvoiceDeliveryApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'InvoiceDeliveryApiError';
        this.status = status;
    }
}

export async function sendInvoiceWhatsApp({ invoiceId, purpose = 'invoice' }) {
    const response = await fetch(`/api/invoices/${encodeURIComponent(invoiceId)}/send-whatsapp`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ purpose }),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new InvoiceDeliveryApiError(
            body.message ?? 'Invoice belum dapat dikirim via WhatsApp. Coba lagi.',
            response.status,
        );
    }

    return body;
}
