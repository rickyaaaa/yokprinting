import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

import { invoiceDeliveryErrorMessage } from '../../resources/js/services/invoice-delivery-api.js';

test('invoice delivery maps authentication and csrf errors precisely', () => {
    assert.equal(
        invoiceDeliveryErrorMessage(419, {}),
        'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
    );
    assert.equal(
        invoiceDeliveryErrorMessage(401, { message: 'Unauthenticated.' }),
        'Sesi login berakhir. Silakan login kembali.',
    );
    assert.equal(
        invoiceDeliveryErrorMessage(403, { message: 'Forbidden.' }),
        'Anda tidak memiliki izin untuk mengirim pengingat WhatsApp.',
    );
});

test('invoice delivery maps validation throttling and server errors precisely', () => {
    assert.equal(
        invoiceDeliveryErrorMessage(422, { errors: { recipient: ['Nomor WhatsApp tidak valid.'] } }),
        'Nomor WhatsApp tidak valid.',
    );
    assert.equal(
        invoiceDeliveryErrorMessage(429, {}),
        'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
    );
    assert.equal(
        invoiceDeliveryErrorMessage(500, { message: 'SQL details must not leak.' }),
        'Terjadi kesalahan server saat menyiapkan WhatsApp. Silakan coba lagi.',
    );
});

test('dashboard delivery prevents duplicate clicks and validates before opening whatsapp', () => {
    const appSource = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const componentSource = appSource.slice(
        appSource.indexOf("Alpine.data('invoiceWhatsAppDelivery'"),
        appSource.indexOf("Alpine.data('recordPaymentForm'"),
    );

    assert.match(componentSource, /if \(this\.sending \|\| !this\.endpoint\)/);
    assert.match(componentSource, /await sendInvoiceWhatsApp\(/);
    assert.match(componentSource, /window\.open\(whatsAppUrl/);
    assert.doesNotMatch(componentSource, /window\.open\('', '_blank'\)/);
});
