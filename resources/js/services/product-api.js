const API_MODE = import.meta.env.VITE_PRODUCT_API_MODE ?? 'mock';

const MOCK_PRODUCTS = [
    {
        id: 1,
        name: 'Sablon Cup 16 Oz Oval 8gr',
        sku: 'CUP-16OV-8G-2S',
        category: 'Sablon cup F&B',
        price: 0,
        purchase_price: 650,
        stock: 32000,
        cup_size: '16 Oz',
        cup_model: 'Oval',
        grammage: '8gr',
        screen_printing_color: 'Hitam',
        sides: 2,
        cup_description: 'Sablon Cup 16 Oz Oval (8gr) - 1 Warna (Tinta Hitam - 2 Sisi)',
        moq_quantity: 1000,
        order_increment: 1000,
        packaging_unit: 'pcs',
    },
    {
        id: 2,
        name: 'Sablon Cup 12 Oz Datar 7gr',
        sku: 'CUP-12DT-7G-1S',
        category: 'Sablon cup F&B',
        price: 0,
        purchase_price: 525,
        stock: 28000,
        cup_size: '12 Oz',
        cup_model: 'Datar',
        grammage: '7gr',
        screen_printing_color: 'Putih',
        sides: 1,
        cup_description: 'Sablon Cup 12 Oz Datar (7gr) - 1 Warna (Tinta Putih - 1 Sisi)',
        moq_quantity: 1000,
        order_increment: 1000,
        packaging_unit: 'pcs',
    },
    {
        id: 3,
        name: 'Sablon Cup 22 Oz Oval 9.5gr',
        sku: 'CUP-22OV-95G-2S',
        category: 'Sablon cup F&B',
        price: 0,
        purchase_price: 875,
        stock: 18000,
        cup_size: '22 Oz',
        cup_model: 'Oval',
        grammage: '9.5gr',
        screen_printing_color: 'Custom',
        sides: 2,
        cup_description: 'Sablon Cup 22 Oz Oval (9.5gr) - 1 Warna (Tinta Custom - 2 Sisi)',
        moq_quantity: 1000,
        order_increment: 1000,
        packaging_unit: 'pcs',
    },
    {
        id: 4,
        name: 'Dus Kemasan Cup 16 Oz',
        sku: 'BOX-CUP16-01',
        category: 'Kemasan pendukung',
        price: 0,
        purchase_price: 9500,
        stock: 240,
        cup_size: '16 Oz',
        cup_model: 'Datar',
        grammage: '8gr',
        screen_printing_color: 'Custom',
        sides: 1,
        cup_description: 'Kemasan dus untuk cup 16 Oz',
        moq_quantity: 10,
        order_increment: 10,
        packaging_unit: 'dus',
    },
];

export class ProductApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'ProductApiError';
        this.status = status;
    }
}

const mockListProducts = () =>
    new Promise((resolve) => {
        window.setTimeout(() => {
            resolve({ data: MOCK_PRODUCTS });
        }, 500);
    });

export async function listProducts() {
    if (API_MODE !== 'live') {
        return mockListProducts();
    }

    const query = new URLSearchParams({
        status: 'active',
        limit: '100',
    });
    const response = await fetch(`/api/products/options?${query.toString()}`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });
    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new ProductApiError(
            body.message ?? 'Data produk belum dapat dimuat.',
            response.status,
        );
    }

    return body;
}
