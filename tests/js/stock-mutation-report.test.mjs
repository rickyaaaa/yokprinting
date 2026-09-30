import test from 'node:test';
import assert from 'node:assert/strict';
import { buildStockMutationExportUrl } from '../../resources/js/stock-mutation-report.js';

test('stock mutation Excel export URL keeps the selected product and date filters', () => {
    const url = buildStockMutationExportUrl('/api/reports/stock-mutations/excel', {
        start_date: '2026-09-01',
        end_date: '2026-09-30',
        product_id: '8',
    });

    assert.equal(
        url,
        '/api/reports/stock-mutations/excel?start_date=2026-09-01&end_date=2026-09-30&product_id=8',
    );
});
