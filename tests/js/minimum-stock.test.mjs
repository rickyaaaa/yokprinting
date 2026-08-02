import test from 'node:test';
import assert from 'node:assert/strict';

import {
    isProductLowStock,
    minimumStockForForm,
    minimumStockForPayload,
    normalizeMinimumStock,
} from '../../resources/js/support/minimum-stock.js';

test('minimum stock normalization preserves zero', () => {
    assert.equal(normalizeMinimumStock(0), 0);
    assert.equal(normalizeMinimumStock('0'), 0);
    assert.equal(minimumStockForForm({ minimum_stock: 0 }), 0);
    assert.equal(minimumStockForPayload(0), 0);
});

test('null and undefined minimum stock use the 500 default', () => {
    assert.equal(normalizeMinimumStock(null), 500);
    assert.equal(normalizeMinimumStock(undefined), 500);
    assert.equal(minimumStockForForm({ minimum_stock: null }), 500);
    assert.equal(minimumStockForForm({}), 500);
    assert.equal(minimumStockForPayload(null), 500);
    assert.equal(minimumStockForPayload(undefined), 500);
});

test('positive minimum stock remains unchanged', () => {
    assert.equal(normalizeMinimumStock(1500), 1500);
    assert.equal(minimumStockForForm({ minimumStock: 1000 }), 1000);
    assert.equal(minimumStockForPayload(2000), 2000);
});

test('stock status uses zero as the actual minimum', () => {
    assert.equal(isProductLowStock({ status: 'active', trackStock: true, stock: 0, minimumStock: 0 }), true);
    assert.equal(isProductLowStock({ status: 'active', trackStock: true, stock: 1, minimumStock: 0 }), false);
    assert.equal(isProductLowStock({ status: 'active', trackStock: false, stock: 0, minimumStock: 500 }), false);
});
