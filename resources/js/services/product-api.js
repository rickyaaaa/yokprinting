export class ProductApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'ProductApiError';
        this.status = status;
    }
}

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
    const query = buildQuery({
        status: 'active',
        limit: '150',
        ...params,
    });
    const response = await fetch(`/api/products/options?${query.toString()}`, {
        cache: 'no-store',
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
        cache: 'no-store',
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

export async function bulkUpdateProductStock(items) {
    const response = await fetch('/api/products/bulk-stock', {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify({ items }),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new ProductApiError(
            body.message ?? 'Bulk edit stok belum dapat disimpan.',
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
