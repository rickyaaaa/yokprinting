const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    timeZone: 'UTC',
});

const parseJsonResponse = async (response) => {
    if (response.status === 204) {
        return {};
    }

    return response.json().catch(() => ({}));
};

const currentCsrfToken = () => document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') ?? '';

const jsonHeaders = () => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': currentCsrfToken(),
});

export const registerSupplierPriceComponents = (Alpine) => {
    Alpine.data('supplierPriceIndexPage', (config = {}) => ({
        priceLists: [],
        loading: true,
        error: '',
        statusLabels: {},
        suppliers: [],
        products: [],
        filters: {
            search: '',
            supplier_id: '',
            product_id: config.productId ?? '',
            status: '',
            date_from: '',
            date_to: '',
        },
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },

        async init() {
            try {
                const [suppliersResponse, productsResponse] = await Promise.all([
                    fetch('/api/suppliers?limit=200', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
                    fetch('/api/products/options?limit=200', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
                ]);
                this.suppliers = (await parseJsonResponse(suppliersResponse)).data ?? [];
                this.products = (await parseJsonResponse(productsResponse)).data ?? [];
            } catch (error) {
                // Filters degrade to plain text search if options fail to load.
            }

            this.loadPriceLists(1);
        },

        async loadPriceLists(page = 1) {
            this.loading = true;
            this.error = '';

            const parameters = new URLSearchParams({ page: String(page), per_page: String(this.meta.per_page) });

            Object.entries(this.filters).forEach(([key, value]) => {
                if (String(value).trim() !== '') {
                    parameters.set(key, value);
                }
            });

            try {
                const response = await fetch(`/api/supplier-prices?${parameters.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.priceLists = payload.data ?? [];
                this.meta = { ...this.meta, ...(payload.meta ?? {}) };
                this.statusLabels = payload.reference?.statuses ?? {};
            } catch (error) {
                this.error = error?.message ?? 'Daftar harga supplier belum berhasil dimuat.';
                this.priceLists = [];
            } finally {
                this.loading = false;
            }
        },

        applyFilters() {
            this.loadPriceLists(1);
        },

        resetFilters() {
            this.filters = { search: '', supplier_id: '', product_id: '', status: '', date_from: '', date_to: '' };
            this.loadPriceLists(1);
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) {
                return;
            }

            this.loadPriceLists(page);
        },

        statusClass(status) {
            return {
                active: 'bg-green-100 text-green-800',
                upcoming: 'bg-blue-100 text-blue-800',
                expired: 'bg-red-100 text-red-800',
            }[status] ?? 'bg-canvas text-muted';
        },

        expiryWarning(priceList) {
            if (priceList.status !== 'active' || !priceList.valid_until) {
                return '';
            }

            const today = new Date().toISOString().slice(0, 10);
            const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

            if (priceList.valid_until === today) {
                return 'Berlaku sampai hari ini';
            }

            if (priceList.valid_until === tomorrow) {
                return 'Berlaku sampai besok';
            }

            return '';
        },

        formatRupiah(value) {
            return rupiahFormatter.format(Number(value ?? 0));
        },

        formatDate(value) {
            if (!value) {
                return '-';
            }

            return dateFormatter.format(new Date(`${value}T00:00:00Z`));
        },
    }));

    Alpine.data('supplierPriceFormPage', (config = {}) => ({
        config,
        loadingOptions: true,
        saving: false,
        generalError: '',
        warning: '',
        errors: {},
        suppliers: [],
        products: [],
        form: {
            supplier_id: config.supplierId ?? '',
            product_id: config.productId ?? '',
            price: '',
            valid_from: config.today,
            valid_until: '',
            notes: '',
        },

        async init() {
            this.loadingOptions = true;

            try {
                const [suppliersResponse, productsResponse] = await Promise.all([
                    fetch('/api/suppliers?limit=200', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
                    fetch('/api/products/options?limit=200', { credentials: 'same-origin', headers: { Accept: 'application/json' } }),
                ]);
                const suppliersPayload = await parseJsonResponse(suppliersResponse);
                const productsPayload = await parseJsonResponse(productsResponse);

                if (!suppliersResponse.ok || !productsResponse.ok) {
                    throw suppliersPayload.errors || productsPayload.errors || suppliersPayload;
                }

                this.suppliers = suppliersPayload.data ?? [];
                this.products = productsPayload.data ?? [];
            } catch (error) {
                this.generalError = 'Daftar supplier/produk belum berhasil dimuat. Muat ulang halaman.';
            } finally {
                this.loadingOptions = false;
            }
        },

        clearError(field) {
            if (!this.errors[field]) {
                return;
            }

            const { [field]: removed, ...remaining } = this.errors;
            this.errors = remaining;
        },

        validate() {
            const errors = {};

            if (!this.form.supplier_id) errors.supplier_id = 'Supplier wajib dipilih.';
            if (!this.form.product_id) errors.product_id = 'Produk wajib dipilih.';
            if (!(Number(this.form.price) > 0)) errors.price = 'Harga harus lebih dari 0.';
            if (!this.form.valid_from) errors.valid_from = 'Tanggal mulai berlaku wajib diisi.';
            if (this.form.valid_until && this.form.valid_until < this.form.valid_from) {
                errors.valid_until = 'Tanggal berakhir tidak boleh sebelum tanggal mulai berlaku.';
            }

            return errors;
        },

        async submit() {
            if (this.saving) {
                return;
            }

            this.errors = this.validate();
            this.generalError = '';
            this.warning = '';

            if (Object.keys(this.errors).length > 0) {
                return;
            }

            this.saving = true;

            try {
                const response = await fetch('/api/supplier-prices', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: jsonHeaders(),
                    body: JSON.stringify({
                        supplier_id: this.form.supplier_id,
                        product_id: this.form.product_id,
                        price: this.form.price,
                        valid_from: this.form.valid_from,
                        valid_until: this.form.valid_until || null,
                        notes: this.form.notes || null,
                    }),
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors = Object.fromEntries(
                            Object.entries(payload.errors ?? {}).map(([field, messages]) => [
                                field,
                                Array.isArray(messages) ? messages[0] : String(messages),
                            ]),
                        );
                    }

                    throw payload;
                }

                if (payload.warnings?.overlap) {
                    window.sessionStorage.setItem('yokprinting.supplierPrice.warning', payload.warnings.overlap);
                }

                window.location.assign(this.config.indexUrl);
            } catch (error) {
                this.generalError = error?.message ?? 'Harga supplier belum berhasil disimpan.';
            } finally {
                this.saving = false;
            }
        },
    }));
};
