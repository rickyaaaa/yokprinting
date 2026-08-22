const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 2,
});

const quantity = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 4,
});

const responsePayload = async (response) => response.json().catch(() => ({}));

const filenameFromDisposition = (disposition, fallback) => {
    const match = disposition?.match(/filename="?([^";]+)"?/i);

    return match?.[1] ?? fallback;
};

export const registerProfitLossComponents = (Alpine) => {
    Alpine.data('profitLossReportPage', (config) => ({
        config,
        loading: true,
        exporting: '',
        error: '',
        selectedPeriod: 'monthly',
        dateFrom: config.periodPresets.monthly.date_from,
        dateTo: config.periodPresets.monthly.date_to,
        report: {
            period: {
                label: '',
                date_from: config.periodPresets.monthly.date_from,
                date_to: config.periodPresets.monthly.date_to,
            },
            accounting_policy: {
                profit_is_provisional: false,
                decision_required: '',
            },
            summary: {
                gross_sales: 0,
                sales_discount: 0,
                sales_revenue: 0,
                tax_collected: 0,
                customer_shipping_charged: 0,
                expected_invoice_total: 0,
                total_invoice: 0,
                invoice_reconciliation_difference: 0,
                total_hpp: 0,
                shipping_expenses: 0,
                expedition_expenses: 0,
                production_expenses: 0,
                employee_expenses: 0,
                premises_expenses: 0,
                shopping_expenses: 0,
                unclassified_expenses: 0,
                recognized_expenses: 0,
                recorded_expenses: 0,
                gross_profit: 0,
                net_profit_minimum: 0,
                net_profit_maximum: 0,
                profit_range: 0,
                minimum_profit_reconciliation_difference: 0,
                maximum_profit_reconciliation_difference: 0,
                sales_quantity: 0,
                invoice_count: 0,
                expense_count: 0,
            },
        },

        init() {
            this.loadReport();
        },

        async selectPeriod() {
            if (this.selectedPeriod === 'custom') {
                return;
            }

            const preset = this.config.periodPresets[this.selectedPeriod];
            this.dateFrom = preset.date_from;
            this.dateTo = preset.date_to;
            await this.loadReport();
        },

        async applyCustomRange() {
            if (!this.dateFrom || !this.dateTo) {
                this.error = 'Tanggal awal dan akhir wajib diisi.';
                return;
            }

            if (this.dateTo < this.dateFrom) {
                this.error = 'Tanggal akhir tidak boleh sebelum tanggal awal.';
                return;
            }

            await this.loadReport();
        },

        queryString() {
            const parameters = new URLSearchParams({ period: this.selectedPeriod });

            if (this.selectedPeriod === 'custom') {
                parameters.set('date_from', this.dateFrom);
                parameters.set('date_to', this.dateTo);
            }

            return parameters.toString();
        },

        async loadReport() {
            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(`${this.config.dataUrl}?${this.queryString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await responsePayload(response);

                if (!response.ok) {
                    throw payload;
                }

                this.report = payload.data;
                this.dateFrom = payload.data.period.date_from;
                this.dateTo = payload.data.period.date_to;
            } catch (error) {
                this.error = error?.message ?? 'Laporan laba rugi belum berhasil dimuat.';
            } finally {
                this.loading = false;
            }
        },

        async download(format) {
            if (this.exporting) {
                return;
            }

            this.exporting = format;
            this.error = '';

            try {
                const endpoint = format === 'pdf' ? this.config.pdfUrl : this.config.excelUrl;
                const response = await fetch(`${endpoint}?${this.queryString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
                });

                if (!response.ok) {
                    throw await responsePayload(response);
                }

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = filenameFromDisposition(
                    response.headers.get('Content-Disposition'),
                    `laporan-laba-rugi.${format === 'pdf' ? 'pdf' : 'xlsx'}`,
                );
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(objectUrl);
            } catch (error) {
                this.error = error?.message ?? 'Laporan belum berhasil diunduh.';
            } finally {
                this.exporting = '';
            }
        },

        formatMoney(value) {
            return rupiah.format(Number(value ?? 0));
        },

        formatQuantity(value) {
            return quantity.format(Number(value ?? 0));
        },

        profitTone(value) {
            return Number(value) >= 0
                ? 'text-green-700 bg-green-50 border-green-200'
                : 'text-red-700 bg-red-50 border-red-200';
        },
    }));
};
