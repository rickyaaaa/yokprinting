import assert from 'node:assert/strict';
import test from 'node:test';

import {
    defaultReceivableSort,
    initialDirectionFor,
    sortReceivables,
    toDateKey,
    withinInvoiceDateRange,
} from '../../resources/js/support/receivable-table.js';

const rows = [
    { id: 1, invoice: 'INV-001', issuedSort: 20260101, dueSort: 20260115, outstandingValue: 900 },
    { id: 2, invoice: 'INV-002', issuedSort: 20260905, dueSort: 20260919, outstandingValue: 100 },
    { id: 3, invoice: 'INV-003', issuedSort: 20260601, dueSort: 20260615, outstandingValue: 500 },
];

const order = (sorted) => sorted.map((row) => row.invoice);

test('the table opens newest invoice first, not nearest due date', () => {
    assert.deepEqual(defaultReceivableSort, { sortKey: 'issuedSort', sortDirection: 'desc' });

    const sorted = sortReceivables(rows, defaultReceivableSort.sortKey, defaultReceivableSort.sortDirection);
    assert.deepEqual(order(sorted), ['INV-002', 'INV-003', 'INV-001']);
});

test('oldest first is the same column toggled', () => {
    assert.deepEqual(order(sortReceivables(rows, 'issuedSort', 'asc')), ['INV-001', 'INV-003', 'INV-002']);
});

test('nearest due date and largest receivable are still reachable', () => {
    assert.deepEqual(order(sortReceivables(rows, 'dueSort', 'asc')), ['INV-001', 'INV-003', 'INV-002']);
    assert.deepEqual(order(sortReceivables(rows, 'outstandingValue', 'desc')), ['INV-001', 'INV-003', 'INV-002']);
});

test('invoices raised the same day fall back to newest id, deterministically', () => {
    const sameDay = [
        { id: 7, invoice: 'INV-A', issuedSort: 20260905 },
        { id: 9, invoice: 'INV-B', issuedSort: 20260905 },
        { id: 8, invoice: 'INV-C', issuedSort: 20260905 },
    ];

    const first = order(sortReceivables(sameDay, 'issuedSort', 'desc'));
    assert.deepEqual(first, ['INV-B', 'INV-C', 'INV-A']);
    // Stable across repeated renders.
    assert.deepEqual(order(sortReceivables(sameDay, 'issuedSort', 'desc')), first);
});

test('sorting never mutates the source rows', () => {
    const before = rows.map((row) => row.invoice);
    sortReceivables(rows, 'outstandingValue', 'asc');
    assert.deepEqual(rows.map((row) => row.invoice), before);
});

test('first click on a date or money column shows the largest first', () => {
    assert.equal(initialDirectionFor('issuedSort'), 'desc');
    assert.equal(initialDirectionFor('dueSort'), 'desc');
    assert.equal(initialDirectionFor('outstandingValue'), 'desc');
    assert.equal(initialDirectionFor('customer'), 'asc');
});

test('an open range keeps every row', () => {
    assert.equal(withinInvoiceDateRange(rows[0], '', ''), true);
    assert.equal(withinInvoiceDateRange(rows[1], '', ''), true);
});

test('only one end of the range may be given', () => {
    // From September onwards.
    assert.equal(withinInvoiceDateRange(rows[1], '2026-09-01', ''), true);
    assert.equal(withinInvoiceDateRange(rows[0], '2026-09-01', ''), false);

    // Up to the end of June.
    assert.equal(withinInvoiceDateRange(rows[0], '', '2026-06-30'), true);
    assert.equal(withinInvoiceDateRange(rows[1], '', '2026-06-30'), false);
});

test('both ends are inclusive', () => {
    const row = { issuedSort: 20260905 };
    assert.equal(withinInvoiceDateRange(row, '2026-09-05', '2026-09-05'), true);
    assert.equal(withinInvoiceDateRange(row, '2026-09-06', '2026-09-30'), false);
    assert.equal(withinInvoiceDateRange(row, '2026-09-01', '2026-09-04'), false);
});

test('a half-typed date never hides every row', () => {
    // Date inputs report partial values while the user is still typing; those
    // must be ignored rather than filtering the table down to nothing.
    assert.equal(toDateKey('2026-09'), null);
    assert.equal(toDateKey(''), null);
    assert.equal(toDateKey(undefined), null);
    assert.equal(withinInvoiceDateRange(rows[0], '2026-09', 'rubbish'), true);
});

test('September 2026 selects only the September invoice', () => {
    const inSeptember = rows.filter((row) => withinInvoiceDateRange(row, '2026-09-01', '2026-09-30'));
    assert.deepEqual(inSeptember.map((row) => row.invoice), ['INV-002']);
});
