export const resolveProductMetrics = (product, initialProduct = null) => ({
    fifoHpp: Number(product.fifo_hpp ?? initialProduct?.purchasePriceValue ?? 0) || 0,
    inventoryValue: Number(product.fifo_inventory_value ?? initialProduct?.inventoryValue ?? 0) || 0,
    sales: Number(product.sales ?? initialProduct?.sales ?? 0) || 0,
});
