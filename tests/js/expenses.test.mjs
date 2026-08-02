import assert from 'node:assert/strict';
import test from 'node:test';

import { csrfRequestHeaders } from '../../resources/js/expenses.js';

test('expense mutation headers include the Laravel session CSRF token', () => {
    assert.deepEqual(csrfRequestHeaders('session-token'), {
        Accept: 'application/json',
        'X-CSRF-TOKEN': 'session-token',
    });
});

test('expense request headers do not invent a token when the page has none', () => {
    assert.deepEqual(csrfRequestHeaders('', { 'Content-Type': 'application/json' }), {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    });
});
