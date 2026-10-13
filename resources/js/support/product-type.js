export const productType = (product) => product?.product_type ?? 'goods';

export const isService = (product) => productType(product) === 'service';

export function invoiceProductDefaults(product = null) {
    const service = isService(product);
    const increment = service ? 1 : Math.max(1, Number(product?.package_conversion || product?.order_increment || product?.minimum_order_qty) || 500);
    return {
        productType: productType(product),
        typeFilter: productType(product),
        cupSize: service ? '' : product?.cup_size ?? '12 Oz',
        cupModel: service ? '' : product?.cup_model ?? 'Oval',
        grammage: service ? '' : product?.grammage ?? '8gr',
        screenPrintingColor: product?.screen_printing_color ?? 'Hitam',
        jenisCetak: product?.sides ? `${product.sides} warna` : '1 warna',
        description: service ? product?.description ?? product?.short_description ?? '' : '',
        moqQuantity: increment,
        orderIncrement: increment,
        packagingUnit: product?.unit ?? 'Pcs',
        quantity: increment,
    };
}

export function productsOfType(products, type) {
    return products.filter((product) => !type || productType(product) === type);
}

export function servicePrintDescription(description = '', ink = '', printType = '') {
    return [description, ink ? `Tinta ${ink}` : '', printType].filter(Boolean).join(' · ');
}
