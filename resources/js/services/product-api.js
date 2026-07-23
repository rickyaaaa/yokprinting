const API_MODE = import.meta.env.VITE_PRODUCT_API_MODE ?? 'mock';

const MOCK_PRODUCTS = [
    {
        id: 1,
        name: 'Paket Desain Identitas Brand',
        sku: 'JSA-BRAND-01',
        category: 'Jasa kreatif',
        price: 12500000,
        stock: null,
    },
    {
        id: 2,
        name: 'Website Company Profile',
        sku: 'JSA-WEB-03',
        category: 'Pengembangan web',
        price: 8750000,
        stock: null,
    },
    {
        id: 3,
        name: 'Retainer Konten Bulanan',
        sku: 'JSA-CONTENT-02',
        category: 'Konten',
        price: 3500000,
        stock: null,
    },
    {
        id: 4,
        name: 'Audit Pengalaman Pengguna',
        sku: 'JSA-UX-04',
        category: 'Konsultasi',
        price: 6250000,
        stock: null,
    },
];

export class ProductApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'ProductApiError';
        this.status = status;
    }
}

const mockListProducts = () =>
    new Promise((resolve) => {
        window.setTimeout(() => {
            resolve({ data: MOCK_PRODUCTS });
        }, 500);
    });

export async function listProducts() {
    if (API_MODE !== 'live') {
        return mockListProducts();
    }

    const query = new URLSearchParams({
        status: 'active',
        limit: '100',
    });
    const response = await fetch(`/api/products?${query.toString()}`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ProductApiError(
            body.message ?? 'Data produk belum dapat dimuat.',
            response.status,
        );
    }

    return body;
}
