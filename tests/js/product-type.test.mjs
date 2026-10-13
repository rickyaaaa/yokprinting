import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { invoiceProductDefaults, productsOfType, servicePrintDescription } from '../../resources/js/support/product-type.js';
import { minimumStockForForm, minimumStockForPayload } from '../../resources/js/support/minimum-stock.js';

function component(name, nextName) {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const start = source.indexOf(`Alpine.data('${name}'`);
    const end = source.indexOf(`Alpine.data('${nextName}'`, start);
    let factory;
    const Alpine = { data: (_, value) => { factory = value; } };
    new Function('Alpine', 'invoiceProductDefaults', 'productsOfType', 'minimumStockForForm', 'minimumStockForPayload', source.slice(start, end))(
        Alpine, invoiceProductDefaults, productsOfType, minimumStockForForm, minimumStockForPayload,
    );
    return factory;
}

test('service defaults use quantity one without cup specs or stale inventory rules', () => {
    const service = invoiceProductDefaults({ product_type: 'service', unit: 'Pekerjaan', package_conversion: 500, cup_size: '12 Oz', description: 'Printing desain' });
    assert.equal(service.quantity, 1);
    assert.equal(service.orderIncrement, 1);
    assert.equal(service.cupSize, '');
    assert.equal(service.screenPrintingColor, 'Hitam');
    assert.equal(service.jenisCetak, '1 warna');
    assert.equal(service.packagingUnit, 'Pekerjaan');
    assert.equal(service.description, 'Printing desain');
});

test('switching between goods and services replaces specs quantity unit and description', () => {
    const item = invoiceProductDefaults({ product_type: 'goods' });
    Object.assign(item, invoiceProductDefaults({ product_type: 'service', unit: 'Paket', description: 'Desain' }));
    assert.equal(item.quantity, 1);
    assert.equal(item.cupModel, '');
    Object.assign(item, invoiceProductDefaults({ product_type: 'goods', unit: 'Pcs', package_conversion: 1000 }));
    assert.equal(item.quantity, 1000);
    assert.equal(item.description, '');
    assert.equal(item.packagingUnit, 'Pcs');
});

test('type filters keep legacy non-stock goods visible without classifying them as service', () => {
    const products = [{ id: 1, track_stock: false }, { id: 2, product_type: 'service' }];
    assert.deepEqual(productsOfType(products, 'goods').map(p => p.id), [1]);
    assert.deepEqual(productsOfType(products, 'service').map(p => p.id), [2]);
});

test('product form smoke: selecting service omits hidden stock and manual FIFO cost from payload', () => {
    const form = component('productForm', 'companyProfileSettings')({ name: 'Printing', sku: '', stock: 5000, manualUnitCost: 400 });
    form.form.productType = 'service';
    form.typeChanged();
    form.form.stock = 7000;
    form.manualUnitCost = 900;
    const payload = form.payload();
    assert.equal(payload.product_type, 'service');
    assert.equal(payload.track_stock, false);
    assert.equal(payload.unit, 'Pcs');
    assert.equal(payload.package_conversion, 1);
    assert.ok(!Object.hasOwn(payload, 'stock'));
    assert.ok(!Object.hasOwn(payload, 'minimum_stock'));
    assert.ok(!Object.hasOwn(payload, 'manual_unit_cost'));
    const hydrated = form.normalizeProduct({ id: 1, name: 'Printing', product_type: 'service', unit: 'Paket', track_stock: false, package_conversion: 1 });
    assert.equal(hydrated.productType, 'service');
    assert.equal(hydrated.unit, 'Paket');
});

test('invoice component smoke: switching type, mixed rows and restoring draft preserve service', () => {
    const editor = component('invoiceItems', 'invoicePreviewActions')();
    const goods = { id: 1, name: 'Cup', product_type: 'goods', unit: 'Pcs', package_conversion: 500, screen_printing_color: 'Merah', sides: 3 };
    const service = { id: 2, name: 'Printing', product_type: 'service', unit: 'Paket' };
    editor.products = [goods, service];
    const item = editor.createItem(goods);
    item.price = 1000;
    item.typeFilter = 'service';
    editor.changeItemType(item);
    assert.equal(item.productId, null);
    assert.equal(item.price, 0);
    assert.equal(item.quantity, 1);
    assert.equal(item.packagingUnit, 'Pcs');
    assert.deepEqual(editor.filteredProductsFor(item).map(p => p.id), [2]);
    editor.selectProduct(item, service);
    item.price = 150000;
    item.description = 'Printing sesuai desain';
    editor.items = [editor.createItem(goods), item];
    assert.equal(editor.items[1].quantity, 1);
    assert.equal(editor.cupDescription(item), 'Printing sesuai desain');
    editor.restoreItems([{ product_id: 2, product_type: 'service', unit: 'Paket', packaging_unit: 'Paket', quantity: 1, price: 150000, screen_printing_color: 'Putih', jenis_cetak: '2 warna', description: 'Printing sesuai desain' }]);
    assert.equal(editor.items[0].typeFilter, 'service');
    assert.equal(editor.items[0].productId, 2);
    assert.equal(editor.items[0].quantity, 1);
    assert.equal(editor.items[0].packagingUnit, 'Paket');
    assert.equal(editor.items[0].cupSize, '');
    assert.equal(editor.items[0].description, 'Printing sesuai desain');
    assert.equal(editor.items[0].screenPrintingColor, 'Putih');
    assert.equal(editor.items[0].jenisCetak, '2 warna');
    editor.selectProduct(editor.items[0], goods);
    assert.equal(editor.items[0].price, 0);
    assert.equal(editor.items[0].screenPrintingColor, 'Merah');
    assert.equal(editor.items[0].jenisCetak, '3 warna');
    assert.equal(editor.items[0].description, '');
});

test('new service printing defaults to pcs but does not inherit a 500 pcs minimum', () => {
    const item = invoiceProductDefaults({ product_type: 'service', package_conversion: 500 });
    assert.equal(item.packagingUnit, 'Pcs');
    assert.equal(item.quantity, 1);
    assert.equal(item.orderIncrement, 1);
    assert.equal(item.screenPrintingColor, 'Hitam');
    assert.equal(item.jenisCetak, '1 warna');
    item.quantity = 1000;
    item.price = 200;
    assert.equal(item.quantity * item.price, 200000);
});

test('corrected H-128 stays Pcs after actual selection update and API draft hydration', () => {
    const editor = component('invoiceItems', 'invoicePreviewActions')();
    const service = { id: 130, sku: 'H-128', name: 'Jasa Cetak', product_type: 'service', unit: 'Pcs', track_stock: false, package_conversion: 1 };
    editor.products = [service];
    const item = editor.createItem({ product_type: 'goods', price: 150000, cup_size: '14 Oz' });
    item.typeFilter = 'service';
    editor.changeItemType(item);
    assert.equal(item.packagingUnit, 'Pcs');
    editor.selectProduct(item, service);
    assert.equal(item.productId, 130);
    assert.equal(item.packagingUnit, 'Pcs');
    assert.equal(item.price, 0);
    assert.equal(item.quantity, 1);
    assert.equal(item.cupSize, '');
    item.price = 200;
    item.quantity = 1000;
    assert.equal(editor.itemTotal(item), 200000);
    editor.products = [{ ...service }];
    editor.restoreItems([{ product_id: 130, sku: 'H-128', product_type: 'service', unit: 'Pcs', packaging_unit: 'Pcs', quantity: 1000, price: 200, screen_printing_color: 'Hitam', jenis_cetak: '1 warna' }]);
    assert.equal(editor.items[0].packagingUnit, 'Pcs');
    assert.equal(editor.items[0].quantity, 1000);
    assert.equal(editor.items[0].price, 200);
    assert.equal(editor.items[0].screenPrintingColor, 'Hitam');
    assert.equal(editor.items[0].jenisCetak, '1 warna');
    editor.updateProduct(editor.items[0]);
    assert.equal(editor.items[0].packagingUnit, 'Pcs');
    assert.equal(editor.items[0].orderIncrement, 1);
    assert.equal(editor.items[0].price, 0);
});

test('legacy service restore uses snapshot unit and empty specs instead of new master defaults', () => {
    const editor = component('invoiceItems', 'invoicePreviewActions')();
    editor.products = [{ id: 2, product_type: 'service', name: 'Printing', unit: 'Pcs', screen_printing_color: 'Hitam', sides: 1 }];
    editor.restoreItems([{ product_id: 2, product_type: 'service', unit: 'Pekerjaan', quantity: 1, price: 150000, screen_printing_color: null, jenis_cetak: null }]);
    assert.equal(editor.items[0].packagingUnit, 'Pekerjaan');
    assert.equal(editor.items[0].quantity, 1);
    assert.equal(editor.items[0].price, 150000);
    assert.equal(editor.items[0].screenPrintingColor, '');
    assert.equal(editor.items[0].jenisCetak, '');
});

test('service preview description includes ink and print type without mutating raw notes', () => {
    const item = { description: 'Printing desain pelanggan', screen_printing_color: 'Hitam', jenis_cetak: '1 warna' };
    const formatted = servicePrintDescription(item.description, item.screen_printing_color, item.jenis_cetak);
    assert.equal(formatted, 'Printing desain pelanggan · Tinta Hitam · 1 warna');
    assert.equal(item.description, 'Printing desain pelanggan');
    assert.equal(servicePrintDescription(), '');
});
