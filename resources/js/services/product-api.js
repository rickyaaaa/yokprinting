const API_MODE = import.meta.env.VITE_PRODUCT_API_MODE ?? 'live';

const MOCK_PRODUCTS = [
    {
        id: 1,
        name: 'Sablon Cup 12 Oz Oval 8gr - 2 warna Hitam',
        sku: 'H-001',
        code: 'H-001',
        category: 'Sablon cup F&B',
        price: 0,
        purchase_price: 650,
        stock: 32000,
        cup_size: '12 Oz',
        cup_model: 'Oval',
        grammage: '8gr',
        screen_printing_color: 'Hitam',
        sides: 2,
        cup_description: 'Sablon Cup 12 Oz Oval (8gr) - 2 warna (Tinta Hitam)',
        moq_quantity: 1000,
        order_increment: 500,
        packaging_unit: 'Pcs',
    },
    {
        id: 2,
        name: 'Sablon Cup 12 Oz Datar 7gr',
        sku: 'H-002',
        code: 'H-002',
        category: 'Sablon cup F&B',
        price: 0,
        purchase_price: 525,
        stock: 28000,
        cup_size: '12 Oz',
        cup_model: 'Datar',
        grammage: '7gr',
        screen_printing_color: 'Putih',
        sides: 1,
        cup_description: 'Sablon Cup 12 Oz Datar (7gr) - 1 warna (Tinta Putih)',
        moq_quantity: 1000,
        order_increment: 500,
        packaging_unit: 'Pcs',
    },
    {
        id: 3,
        name: 'Sablon Cup 12 Oz Oval 9.5gr - 2 warna Custom',
        sku: 'H-003',
        code: 'H-003',
        category: 'Sablon cup F&B',
        price: 0,
        purchase_price: 875,
        stock: 18000,
        cup_size: '12 Oz',
        cup_model: 'Oval',
        grammage: '9.5gr',
        screen_printing_color: 'Custom',
        sides: 2,
        cup_description: 'Sablon Cup 12 Oz Oval (9.5gr) - 2 warna (Tinta Custom)',
        moq_quantity: 1000,
        order_increment: 500,
        packaging_unit: 'Pcs',
    },
    {
        id: 4,
        name: 'Dus Kemasan Cup 12 Oz',
        sku: 'H-004',
        code: 'H-004',
        category: 'Kemasan pendukung',
        price: 0,
        purchase_price: 9500,
        stock: 240,
        cup_size: '12 Oz',
        cup_model: 'Datar',
        grammage: '8gr',
        screen_printing_color: 'Custom',
        sides: 1,
        cup_description: 'Kemasan dus untuk cup 12 Oz',
        moq_quantity: 10,
        order_increment: 500,
        packaging_unit: 'Pcs',
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

const jsonHeaders = () => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
});

const buildQuery = (params = {}) => new URLSearchParams(
    Object.entries(params)
        .filter(([, value]) => value !== undefined && value !== null && value !== '')
        .map(([key, value]) => [key, String(value)]),
);

export async function listProducts(params = {}) {
    if (API_MODE !== 'live') {
        return mockListProducts();
    }

    const query = buildQuery({
        status: 'active',
        limit: '150',
        ...params,
    });
    const response = await fetch(`/api/products/options?${query.toString()}`, {
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

export async function listProductCatalog(params = {}) {
    const query = buildQuery({
        status: 'all',
        limit: '150',
        sort: 'sku',
        ...params,
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
            body.message ?? 'Data katalog produk belum dapat dimuat.',
            response.status,
        );
    }

    return body;
}

export async function createProduct(payload) {
    const response = await fetch('/api/products', {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new ProductApiError(
            body.message ?? 'Produk belum dapat disimpan.',
            response.status,
        );
        error.errors = body.errors ?? {};
        throw error;
    }

    return body;
}

export async function getProduct(id) {
    const response = await fetch(`/api/products/${id}`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ProductApiError(
            body.message ?? 'Detail produk belum dapat dimuat.',
            response.status,
        );
    }

    return body;
}

export async function updateProduct(id, payload) {
    const response = await fetch(`/api/products/${id}`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new ProductApiError(
            body.message ?? 'Perubahan produk belum dapat disimpan.',
            response.status,
        );
        error.errors = body.errors ?? {};
        throw error;
    }

    return body;
}

export async function deleteProduct(id) {
    const response = await fetch(`/api/products/${id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: jsonHeaders(),
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));

        throw new ProductApiError(
            body.message ?? 'Produk belum dapat dihapus.',
            response.status,
        );
    }
}
