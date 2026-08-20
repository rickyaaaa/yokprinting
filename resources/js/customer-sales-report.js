const reportCurrency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

export const registerCustomerSalesReportComponents = (Alpine) => {
    Alpine.data('customerSalesReportPage', (config = {}) => ({
        config,
        loading: true,
        exporting: '',
        error: '',
        report: { period: {}, summary: {}, customers: [] },
        filters: { date_from: config.dateFrom ?? '', date_to: config.dateTo ?? '', customer_id: '', status: 'all' },

        init() { this.load(); },

        async load() {
            this.loading = true;
            this.error = '';
            const params = new URLSearchParams(Object.entries(this.filters).filter(([, value]) => value !== ''));

            try {
                const response = await fetch(`${this.config.endpoint}?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw payload;
                this.report = payload.data ?? this.report;
            } catch (error) {
                this.error = Object.values(error?.errors ?? {}).flat()[0] ?? error?.message ?? 'Laporan belum berhasil dimuat.';
            } finally { this.loading = false; }
        },

        exportFile(type) {
            const params = new URLSearchParams(Object.entries(this.filters).filter(([, value]) => value !== ''));
            window.location.assign(`${this.config.exportEndpoints[type]}?${params}`);
        },

        format(value) { return reportCurrency.format(Number(value ?? 0)); },
    }));
};
