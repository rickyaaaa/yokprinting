import assert from 'node:assert/strict';
import test from 'node:test';

import { printedItemNoun } from '../../resources/js/support/invoice-item-label.js';

test('lid products are described as a lid, not a cup', () => {
    assert.equal(printedItemNoun('Tutup / Lid'), 'Tutup');
    assert.equal(printedItemNoun('tutup / lid'), 'Tutup');
});

test('bowl products keep their own noun', () => {
    assert.equal(printedItemNoun('Paper Bowl'), 'Bowl');
});

test('every cup category still reads as a cup', () => {
    for (const category of ['Cup PP', 'Cup PET', 'Cup Injection', 'Paper Cup']) {
        assert.equal(printedItemNoun(category), 'Cup');
    }
});

test('an unknown or missing category falls back to cup', () => {
    assert.equal(printedItemNoun('Produk Kemasan F&B'), 'Cup');
    assert.equal(printedItemNoun(''), 'Cup');
    assert.equal(printedItemNoun(undefined), 'Cup');
    assert.equal(printedItemNoun(null), 'Cup');
});
