import assert from 'node:assert/strict';
import test from 'node:test';

import { businessDate } from '../../resources/js/cash-bank.js';

test('businessDate uses the configured business timezone instead of UTC', () => {
    const afterMidnightInJakarta = new Date('2026-08-13T17:30:00.000Z');

    assert.equal(businessDate('Asia/Jakarta', afterMidnightInJakarta), '2026-08-14');
    assert.equal(businessDate('UTC', afterMidnightInJakarta), '2026-08-13');
});
