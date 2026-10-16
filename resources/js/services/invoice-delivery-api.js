export class InvoiceDeliveryApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'InvoiceDeliveryApiError';
        this.status = status;
    }
}

export function invoiceDeliveryErrorMessage(status, body = {}) {
    const validationMessage = Object.values(body.errors ?? {}).flat()[0];

    if (status === 419) {
        return body.message ?? 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    }

    if (status === 401) {
        return 'Sesi login berakhir. Silakan login kembali.';
    }

    if (status === 403) {
        return 'Anda tidak memiliki izin untuk mengirim pengingat WhatsApp.';
    }

    if (status === 422) {
        return validationMessage ?? body.message ?? 'Nomor WhatsApp atau data invoice tidak valid.';
    }

    if (status === 429) {
        return body.message ?? 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.';
    }

    if (status >= 500) {
        return 'Terjadi kesalahan server saat menyiapkan WhatsApp. Silakan coba lagi.';
    }

    return body.message ?? 'Invoice belum dapat dikirim via WhatsApp. Coba lagi.';
}

export async function sendInvoiceWhatsApp({ invoiceId = null, endpoint = null, purpose = 'invoice' }) {
    const requestEndpoint = endpoint
        ?? (invoiceId === null ? null : `/api/invoices/${encodeURIComponent(invoiceId)}/send-whatsapp`);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    if (!requestEndpoint) {
        throw new InvoiceDeliveryApiError('Endpoint WhatsApp invoice tidak tersedia.', 422);
    }

    if (!csrfToken) {
        throw new InvoiceDeliveryApiError(
            'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
            419,
        );
    }

    const response = await fetch(requestEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ purpose }),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new InvoiceDeliveryApiError(
            invoiceDeliveryErrorMessage(response.status, body),
            response.status,
        );
    }

    return body;
}
