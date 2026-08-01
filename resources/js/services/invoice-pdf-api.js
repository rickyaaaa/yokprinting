export class InvoicePdfApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'InvoicePdfApiError';
        this.status = status;
    }
}

const filenameFromHeader = (header, fallback) => {
    const match = header?.match(/filename="?([^";]+)"?/i);

    return match?.[1] ?? fallback;
};

export async function downloadInvoicePdf(invoiceId) {
    const response = await fetch(`/api/invoices/${encodeURIComponent(invoiceId)}/pdf`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/pdf',
        },
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));

        throw new InvoicePdfApiError(
            body.message ?? 'PDF invoice belum dapat dibuat. Coba lagi.',
            response.status,
        );
    }

    return {
        blob: await response.blob(),
        filename: filenameFromHeader(
            response.headers.get('Content-Disposition'),
            `${invoiceId}.pdf`,
        ),
    };
}

export async function downloadInvoicePreviewPdf(preview) {
    const response = await fetch('/api/invoices/preview/pdf', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/pdf',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(preview),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));

        throw new InvoicePdfApiError(
            body.message ?? 'PDF preview invoice belum dapat dibuat. Coba lagi.',
            response.status,
        );
    }

    return {
        blob: await response.blob(),
        filename: filenameFromHeader(
            response.headers.get('Content-Disposition'),
            `${preview?.invoice_number ?? 'invoice-preview'}.pdf`,
        ),
    };
}
