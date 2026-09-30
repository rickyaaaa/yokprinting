export const resolveProductMetrics = (product, initialProduct = null) => ({
    fifoHpp: Number(product.fifo_hpp ?? initialProduct?.purchasePriceValue ?? 0) || 0,
    inventoryValue: Number(product.fifo_inventory_value ?? initialProduct?.inventoryValue ?? 0) || 0,
    sales: Number(product.sales ?? initialProduct?.sales ?? 0) || 0,
});

export const formatProductQuantity = (value, unit = 'Pcs') => {
    const quantity = Number(value) || 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 4,
    }).format(quantity);

    return `${formatted} ${unit || 'Pcs'}`;
};
