export const resolveProductMetrics = (product, initialProduct = null) => {
    const metrics = {
        fifoHpp: Number(product.fifo_hpp ?? initialProduct?.purchasePriceValue ?? 0) || 0,
        inventoryValue: Number(product.fifo_inventory_value ?? initialProduct?.inventoryValue ?? 0) || 0,
        sales: Number(product.sales ?? initialProduct?.sales ?? 0) || 0,
    };

    if (Object.prototype.hasOwnProperty.call(product, 'last_purchase_price') || initialProduct?.lastPurchasePrice !== undefined) {
        metrics.lastPurchasePrice = product.last_purchase_price ?? initialProduct?.lastPurchasePrice ?? null;
    }

    if (Object.prototype.hasOwnProperty.call(product, 'average_purchase_cost') || initialProduct?.averagePurchaseCost !== undefined) {
        metrics.averagePurchaseCost = product.average_purchase_cost ?? initialProduct?.averagePurchaseCost ?? null;
    }

    return metrics;
};

export const formatProductQuantity = (value, unit = 'Pcs') => {
    const quantity = Number(value) || 0;
    const formatted = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 4,
    }).format(quantity);

    return `${formatted} ${unit || 'Pcs'}`;
};
