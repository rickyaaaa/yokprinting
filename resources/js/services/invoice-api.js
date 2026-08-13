export class InvoiceApiError extends Error {
    constructor(message, status = 500, errors = {}) {
        super(message);
        this.name = 'InvoiceApiError';
        this.status = status;
        this.errors = errors;
    }
}

export async function persistInvoiceDraft(payload) {
    const response = await fetch('/api/invoices/drafts', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(payload),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new InvoiceApiError(
            body.message ?? 'Invoice belum dapat disimpan. Coba lagi.',
            response.status,
            body.errors ?? {},
        );
    }

    return {
        ...body,
        persisted: true,
    };
}

export async function saveInvoiceDraft(payload) {
    return persistInvoiceDraft(payload);
}
