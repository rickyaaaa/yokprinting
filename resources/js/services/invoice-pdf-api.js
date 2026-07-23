const API_MODE = import.meta.env.VITE_INVOICE_PDF_API_MODE ?? 'mock';

export class InvoicePdfApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'InvoicePdfApiError';
        this.status = status;
    }
}

const createMockPdfBlob = (invoiceId) => {
    const content = [
        'BT',
        '/F1 18 Tf',
        '72 760 Td',
        `(YokPrinting.ID - ${invoiceId}) Tj`,
        '0 -34 Td',
        '/F1 11 Tf',
        '(Dokumen PDF tiruan untuk validasi alur unduh frontend.) Tj',
        '0 -24 Td',
        '(Total: Rp 22.408.125) Tj',
        'ET',
    ].join('\n');
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        `<< /Length ${content.length} >>\nstream\n${content}\nendstream`,
    ];
    let document = '%PDF-1.4\n';
    const offsets = [0];

    objects.forEach((object, index) => {
        offsets.push(document.length);
        document += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });

    const xrefOffset = document.length;
    document += `xref\n0 ${objects.length + 1}\n`;
    document += '0000000000 65535 f \n';
    document += offsets
        .slice(1)
        .map((offset) => `${String(offset).padStart(10, '0')} 00000 n \n`)
        .join('');
    document += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\n`;
    document += `startxref\n${xrefOffset}\n%%EOF`;

    return new Blob([document], { type: 'application/pdf' });
};

const mockDownloadInvoicePdf = (invoiceId) =>
    new Promise((resolve, reject) => {
        window.setTimeout(() => {
            if (!invoiceId) {
                reject(new InvoicePdfApiError('Nomor invoice tidak tersedia.', 422));
                return;
            }

            resolve({
                blob: createMockPdfBlob(invoiceId),
                filename: `${invoiceId}.pdf`,
            });
        }, 550);
    });

const filenameFromHeader = (header, fallback) => {
    const match = header?.match(/filename="?([^";]+)"?/i);

    return match?.[1] ?? fallback;
};

export async function downloadInvoicePdf(invoiceId) {
    if (API_MODE !== 'live') {
        return mockDownloadInvoicePdf(invoiceId);
    }

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
