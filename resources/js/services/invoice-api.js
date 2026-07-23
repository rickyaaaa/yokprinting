const API_MODE = import.meta.env.VITE_INVOICE_API_MODE ?? 'mock';

export class InvoiceApiError extends Error {
    constructor(message, status = 500, errors = {}) {
        super(message);
        this.name = 'InvoiceApiError';
        this.status = status;
        this.errors = errors;
    }
}

const mockSaveDraft = (payload) =>
    new Promise((resolve, reject) => {
        window.setTimeout(() => {
            if (!payload.customer_id) {
                reject(new InvoiceApiError('Pilih pelanggan sebelum menyimpan draft.', 422));

                return;
            }

            if (payload.items.length === 0) {
                reject(new InvoiceApiError('Tambahkan minimal satu item invoice.', 422));

                return;
            }

            resolve({
                data: {
                    id: 'draft-demo-0079',
                    invoice_number: payload.invoice_number,
                    status: 'draft',
                    saved_at: new Date().toISOString(),
                },
            });
        }, 550);
    });

export async function saveInvoiceDraft(payload) {
    if (API_MODE !== 'live') {
        return mockSaveDraft(payload);
    }

    const response = await fetch('/api/invoices/drafts', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new InvoiceApiError(
            body.message ?? 'Draft belum dapat disimpan. Coba lagi.',
            response.status,
            body.errors ?? {},
        );
    }

    return body;
}
