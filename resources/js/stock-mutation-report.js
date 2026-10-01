const quantity = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });

export const buildStockMutationExportUrl = (endpoint, filters = {}) => {
    if (!endpoint) {
        return '#';
    }

    return `${endpoint}?${new URLSearchParams(filters)}`;
};

export const registerStockMutationReportComponents = (Alpine) => {
    Alpine.data('stockMutationReportPage', (config = {}) => ({
        config,
        loading: false,
        exporting: '',
        error: '',
        filters: {
            start_date: config.dateFrom ?? '',
            end_date: config.dateTo ?? '',
            product_id: '',
        },
        productSearch: '',
        productMenuOpen: false,
        report: {
            product: null,
            mutations: [],
            detail_summary: null,
        },

        init() {
            this.$watch('filters.product_id', () => {
                if (this.filters.product_id) {
                    this.load();
                }
            });
        },

        get selectedProduct() {
            return this.config.products.find((product) => String(product.id) === String(this.filters.product_id));
        },

        get filteredProducts() {
            const search = this.productSearch.trim().toLowerCase();

            if (!search) {
                return this.config.products.slice(0, 30);
            }

            return this.config.products
                .filter((product) => `${product.sku} ${product.name}`.toLowerCase().includes(search))
                .slice(0, 30);
        },

        chooseProduct(product) {
            this.filters.product_id = String(product.id);
            this.productSearch = `${product.sku} — ${product.name}`;
            this.productMenuOpen = false;
        },

        clearProduct() {
            this.filters.product_id = '';
            this.productSearch = '';
            this.productMenuOpen = false;
            this.report = { product: null, mutations: [], detail_summary: null };
            this.error = '';
        },

        async load() {
            if (!this.filters.product_id) {
                this.error = 'Pilih barang terlebih dahulu untuk melihat mutasi per barang.';
                return;
            }

            if (!this.filters.start_date || !this.filters.end_date) {
                this.error = 'Tanggal awal dan akhir wajib diisi.';
                return;
            }

            if (this.filters.end_date < this.filters.start_date) {
                this.error = 'Tanggal akhir tidak boleh sebelum tanggal awal.';
                return;
            }

            this.loading = true;
            this.error = '';
            const params = new URLSearchParams(this.filters);

            try {
                const response = await fetch(`${this.config.endpoint}?${params}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw payload;
                }

                this.report = payload.data ?? this.report;
            } catch (error) {
                this.error = Object.values(error?.errors ?? {}).flat()[0]
                    ?? error?.message
                    ?? 'Mutasi barang belum berhasil dimuat.';
            } finally {
                this.loading = false;
            }
        },

        exportUrl(format) {
            return buildStockMutationExportUrl(this.config.exportEndpoints?.[format], this.filters);
        },

        exportFile(format) {
            if (!this.filters.product_id) {
                return;
            }

            this.exporting = format;

            // Use a real download link instead of navigating the report page.
            // This keeps the selected product and date range intact when the
            // server responds with Content-Disposition: attachment.
            const link = document.createElement('a');
            link.href = this.exportUrl(format);
            link.download = '';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            link.remove();

            window.setTimeout(() => { this.exporting = ''; }, 1200);
        },

        formatQuantity(value) {
            return quantity.format(Number(value ?? 0));
        },

        reconciliationLabel(status) {
            return {
                balanced: 'Saldo produk dan ledger seimbang',
                needs_reconciliation: 'Perlu rekonsiliasi stok',
                historical_period: 'Periode historis — rekonsiliasi live tidak ditampilkan',
                not_applicable: 'Rekonsiliasi ledger belum tersedia untuk produk ini',
            }[status] ?? 'Status rekonsiliasi tidak diketahui';
        },

        formatDate(value) {
            if (!value) return '-';

            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            }).format(new Date(`${value}T00:00:00`));
        },
    }));
};
