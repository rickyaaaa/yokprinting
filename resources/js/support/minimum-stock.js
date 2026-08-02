export const DEFAULT_MINIMUM_STOCK = 500;

export const normalizeMinimumStock = (value) => {
    if (value === null || value === undefined || value === '') {
        return DEFAULT_MINIMUM_STOCK;
    }

    const normalized = Number(value);

    return Number.isFinite(normalized) ? normalized : DEFAULT_MINIMUM_STOCK;
};

export const minimumStockForForm = (product = {}) => normalizeMinimumStock(
    product.minimumStock ?? product.minimum_stock,
);

export const minimumStockForPayload = (value, trackStock = true) => (
    trackStock ? normalizeMinimumStock(value) : 0
);

export const isProductLowStock = ({ status, trackStock, stock, minimumStock }) => (
    status !== 'inactive'
    && trackStock !== false
    && (Number(stock) || 0) <= normalizeMinimumStock(minimumStock)
);
