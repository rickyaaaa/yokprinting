const API_MODE = import.meta.env.VITE_CUSTOMER_API_MODE ?? 'mock';

const MOCK_CUSTOMERS = [
    {
        id: 1,
        name: 'PT Sinar Nusantara',
        email: 'finance@sinarnusantara.co.id',
        phone: '+62 21 555 0198',
        address: 'Jl. Jenderal Sudirman No. 88, Jakarta Selatan',
        initials: 'SN',
    },
    {
        id: 2,
        name: 'CV Arunika Kreatif',
        email: 'halo@arunikakreatif.id',
        phone: '+62 812 3388 1042',
        address: 'Jl. Ciumbuleuit No. 42, Bandung',
        initials: 'AK',
    },
    {
        id: 3,
        name: 'Kopi Pagi Indonesia',
        email: 'billing@kopipagi.id',
        phone: '+62 31 5567 220',
        address: 'Jl. Raya Darmo No. 15, Surabaya',
        initials: 'KP',
    },
    {
        id: 4,
        name: 'Bumi Rasa Catering',
        email: 'keuangan@bumirasa.co.id',
        phone: '+62 274 481 920',
        address: 'Jl. Kaliurang KM 7, Yogyakarta',
        initials: 'BR',
    },
];

export class CustomerApiError extends Error {
    constructor(message, status = 500) {
        super(message);
        this.name = 'CustomerApiError';
        this.status = status;
    }
}

const mockListCustomers = (search) =>
    new Promise((resolve) => {
        window.setTimeout(() => {
            const keyword = search.trim().toLocaleLowerCase('id');
            const customers = keyword
                ? MOCK_CUSTOMERS.filter((customer) =>
                    `${customer.name} ${customer.email} ${customer.phone}`
                        .toLocaleLowerCase('id')
                        .includes(keyword),
                )
                : MOCK_CUSTOMERS;

            resolve({ data: customers });
        }, 450);
    });

export async function listCustomers({ search = '' } = {}) {
    if (API_MODE !== 'live') {
        return mockListCustomers(search);
    }

    const query = new URLSearchParams();

    if (search) {
        query.set('search', search);
    }

    const response = await fetch(`/api/customers?${query.toString()}`, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new CustomerApiError(
            body.message ?? 'Data pelanggan belum dapat dimuat.',
            response.status,
        );
    }

    return body;
}
