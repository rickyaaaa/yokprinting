import assert from 'node:assert/strict';
import test from 'node:test';

import { deleteCustomer, listCustomers } from '../../resources/js/services/customer-api.js';

test('customer picker always reads current customer options from the database endpoint', async () => {
    const originalFetch = global.fetch;
    let request;

    global.fetch = async (url, options) => {
        request = { url, options };

        return {
            ok: true,
            json: async () => ({ data: [] }),
        };
    };

    try {
        await listCustomers({ search: 'CUS-123', limit: 100 });
    } finally {
        global.fetch = originalFetch;
    }

    assert.equal(request.url, '/api/customers?search=CUS-123&limit=100');
    assert.equal(request.options.cache, 'no-store');
    assert.equal(request.options.credentials, 'same-origin');
});

test('customer deletion uses the authenticated session and csrf token', async () => {
    const originalFetch = global.fetch;
    const originalDocument = global.document;
    let request;

    global.document = {
        querySelector: () => ({ content: 'csrf-delete-token' }),
    };
    global.fetch = async (url, options) => {
        request = { url, options };

        return {
            ok: true,
            json: async () => ({ data: { history_preserved: true } }),
        };
    };

    try {
        await deleteCustomer(42);
    } finally {
        global.fetch = originalFetch;
        global.document = originalDocument;
    }

    assert.equal(request.url, '/api/customers/42');
    assert.equal(request.options.method, 'DELETE');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'csrf-delete-token');
});
