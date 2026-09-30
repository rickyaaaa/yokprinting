import test from 'node:test';
import assert from 'node:assert/strict';
import { formatProductQuantity, resolveProductMetrics } from '../../resources/js/support/product-catalog.js';

test('catalog hydration keeps server metrics when an older API omits them', () => {
    assert.deepEqual(
        resolveProductMetrics(
            { id: 2 },
            { id: 2, purchasePriceValue: 600, inventoryValue: 600000, sales: 3 },
        ),
        { fifoHpp: 600, inventoryValue: 600000, sales: 3 },
    );
});

test('catalog API values remain authoritative including explicit zero', () => {
    assert.deepEqual(
        resolveProductMetrics(
            { fifo_hpp: 0, fifo_inventory_value: 0, sales: 0 },
            { purchasePriceValue: 600, inventoryValue: 600000, sales: 3 },
        ),
        { fifoHpp: 0, inventoryValue: 0, sales: 0 },
    );
});

test('catalog sales quantity is formatted as pcs, not transactions', () => {
    assert.equal(formatProductQuantity(1500), '1.500 Pcs');
    assert.equal(formatProductQuantity(1250.5), '1.250,5 Pcs');
});
