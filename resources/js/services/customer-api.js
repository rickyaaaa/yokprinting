export class CustomerApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'CustomerApiError';
        this.status = status;
    }
}

export async function listCustomers({ search = '', limit = 100 } = {}) {
    const query = new URLSearchParams();

    if (search) {
        query.set('search', search);
    }

    query.set('limit', String(limit));

    const response = await fetch(`/api/customers?${query.toString()}`, {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new CustomerApiError(
            body.message ?? 'Data pelanggan belum dapat dimuat.',
            response.status,
        );
    }

    return body;
}

const jsonHeaders = () => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
});

export async function createCustomer(payload) {
    const response = await fetch('/api/customers', {
        method: 'POST',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new CustomerApiError(
            body.message ?? 'Pelanggan belum dapat disimpan.',
            response.status,
        );
        error.errors = body.errors ?? {};
        throw error;
    }

    return body;
}

export async function updateCustomer(id, payload) {
    const response = await fetch(`/api/customers/${id}`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: jsonHeaders(),
        body: JSON.stringify(payload),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new CustomerApiError(
            body.message ?? 'Perubahan pelanggan belum dapat disimpan.',
            response.status,
        );
        error.errors = body.errors ?? {};
        throw error;
    }

    return body;
}

export async function deleteCustomer(id) {
    const response = await fetch(`/api/customers/${id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: jsonHeaders(),
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new CustomerApiError(
            body.message ?? 'Pelanggan belum dapat dihapus.',
            response.status,
        );
    }

    return body;
}
