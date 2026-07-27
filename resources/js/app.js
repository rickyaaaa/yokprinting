import './bootstrap';

import Alpine from 'alpinejs';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { listCustomers } from './services/customer-api';
import { sendInvoiceEmail } from './services/invoice-delivery-api';
import { saveInvoiceDraft } from './services/invoice-api';
import { downloadInvoicePdf } from './services/invoice-pdf-api';
import {
    createProduct,
    deleteProduct,
    getProduct,
    listProductCatalog,
    listProducts,
    updateProduct as updateCatalogProduct,
} from './services/product-api';

window.Alpine = Alpine;

Chart.register(BarController, BarElement, CategoryScale, Filler, LinearScale, LineController, LineElement, PointElement, Legend, Tooltip);

const formatShortRupiah = (value) => {
    if (value >= 1000000000) {
        return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value / 1000000000)} M`;
    }

    return `Rp${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value / 1000000)} jt`;
};

const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(value);

const dashboardRevenueDatasets = {
    monthly: {
        label: '6 bulan terakhir',
        headline: 'Rp86,4 jt',
        caption: 'Juli menjadi bulan terkuat dari data saat ini.',
        labels: ['Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'],
        issued: [46000000, 58000000, 52000000, 71000000, 64000000, 86400000],
        paid: [38000000, 42000000, 47000000, 59000000, 52100000, 52100000],
    },
    quarterly: {
        label: '4 kuartal terakhir',
        headline: 'Rp221,4 jt',
        caption: 'Kuartal berjalan naik karena invoice jasa cetak korporat.',
        labels: ['Q4 2025', 'Q1 2026', 'Q2 2026', 'Q3 2026'],
        issued: [142000000, 168000000, 187000000, 221400000],
        paid: [128000000, 149000000, 161000000, 174000000],
    },
    yearly: {
        label: '3 tahun terakhir',
        headline: 'Rp1,18 M',
        caption: 'Simulasi pendapatan tahunan untuk membaca tren besar bisnis.',
        labels: ['2024', '2025', '2026'],
        issued: [720000000, 940000000, 1180000000],
        paid: [665000000, 872000000, 976000000],
    },
};

const minutesAgo = (minutes) => new Date(Date.now() - minutes * 60 * 1000).toISOString();

const buildInvoiceDraftPayload = (form) => {
    const explicitProductFields = [...form.querySelectorAll('[data-product-id-field]')];
    const productFields = explicitProductFields.length > 0
        ? explicitProductFields
        : [...form.querySelectorAll('select[name^="items"][name$="[product_id]"]')];

    return {
        customer_id: Number(form.querySelector('[name="customer_id"]')?.value) || null,
        invoice_number: form.querySelector('[name="invoice_number"]')?.value ?? '',
        issue_date: form.querySelector('[name="issue_date"]')?.value ?? '',
        due_date: form.querySelector('[name="due_date"]')?.value ?? '',
        items: productFields.map((productField, index) => ({
            product_id: Number(productField.value),
            product_name: form.querySelector(`[name="items[${index}][product_name]"]`)?.value ?? '',
            sku: form.querySelector(`[name="items[${index}][sku]"]`)?.value ?? '',
            cup_size: form.querySelector(`[name="items[${index}][cup_size]"]`)?.value ?? '',
            cup_model: form.querySelector(`[name="items[${index}][cup_model]"]`)?.value ?? '',
            grammage: form.querySelector(`[name="items[${index}][grammage]"]`)?.value ?? '',
            screen_printing_color: form.querySelector(`[name="items[${index}][screen_printing_color]"]`)?.value ?? '',
            jenis_cetak: form.querySelector(`[name="items[${index}][jenis_cetak]"]`)?.value ?? '1 warna',
            moq_quantity: Number(form.querySelector(`[name="items[${index}][moq_quantity]"]`)?.value) || null,
            order_increment: Number(form.querySelector(`[name="items[${index}][order_increment]"]`)?.value) || null,
            packaging_unit: form.querySelector(`[name="items[${index}][packaging_unit]"]`)?.value ?? 'pcs',
            description: form.querySelector(`[name="items[${index}][description]"]`)?.value ?? '',
            quantity: Number(form.querySelector(`[name="items[${index}][quantity]"]`)?.value) || 0,
            price: Number(form.querySelector(`[name="items[${index}][price]"]`)?.value) || 0,
        })),
        discount: {
            type: form.querySelector('[name="discount_type"]')?.value ?? 'percentage',
            value: Number(form.querySelector('[name="discount_value"]')?.value) || 0,
        },
        tax: {
            enabled: form.querySelector('[name="tax_enabled"]')?.checked ?? false,
            rate: Number(form.querySelector('[name="tax_rate"]')?.value) || 0,
        },
        notes: form.querySelector('[name="notes"]')?.value ?? '',
        terms: form.querySelector('[name="terms"]')?.value ?? '',
        production_status: form.querySelector('[name="production_status"]')?.value ?? 'draft',
        shipping_cost: Number(form.querySelector('[name="shipping_cost"]')?.value) || 0,
        is_free_shipping: form.querySelector('[name="is_free_shipping"]')?.checked ?? false,
        order_process_status: form.querySelector('[name="order_process_status"]')?.value ?? 'draft',
        design_notes: form.querySelector('[name="design_notes"]')?.value ?? '',
        mockup_url: form.querySelector('[name="mockup_url"]')?.value ?? '',
        dp_required_percent: Number(form.querySelector('[name="dp_required_percent"]')?.value) || 50,
    };
};

const validateInvoiceDraft = (payload) => {
    const errors = {};

    if (!payload.customer_id) {
        errors.customer_id = 'Pilih pelanggan untuk invoice ini.';
    }

    if (!payload.invoice_number.trim()) {
        errors.invoice_number = 'Nomor invoice wajib diisi.';
    }

    if (!payload.issue_date) {
        errors.issue_date = 'Tanggal invoice wajib diisi.';
    }

    if (!payload.due_date) {
        errors.due_date = 'Tanggal jatuh tempo wajib diisi.';
    } else if (payload.issue_date && payload.due_date < payload.issue_date) {
        errors.due_date = 'Jatuh tempo tidak boleh sebelum tanggal invoice.';
    }

    if (payload.items.length === 0) {
        errors.items = 'Tambahkan minimal satu item invoice.';
    } else if (payload.items.some((item) => !item.product_id)) {
        errors.items = 'Setiap baris harus memiliki produk.';
    } else if (payload.items.some((item) => item.quantity < 1)) {
        errors.items = 'Jumlah setiap item minimal 1.';
    } else if (payload.items.some((item) => item.moq_quantity && item.quantity < item.moq_quantity)) {
        const item = payload.items.find((row) => row.moq_quantity && row.quantity < row.moq_quantity);

        errors.items = `Jumlah ${item.product_name || 'item'} minimal ${item.moq_quantity} ${item.packaging_unit || 'pcs'}.`;
    } else if (payload.items.some((item) => item.order_increment && item.quantity % item.order_increment !== 0)) {
        const item = payload.items.find((row) => row.order_increment && row.quantity % row.order_increment !== 0);

        errors.items = `Jumlah ${item.product_name || 'item'} harus kelipatan ${item.order_increment} ${item.packaging_unit || 'pcs'}.`;
    } else if (payload.items.some((item) => item.price <= 0)) {
        errors.items = 'Harga setiap item harus lebih dari Rp0.';
    }

    if (payload.discount.value < 0) {
        errors.discount_value = 'Diskon tidak boleh bernilai negatif.';
    } else if (payload.discount.type === 'percentage' && payload.discount.value > 100) {
        errors.discount_value = 'Diskon persentase maksimal 100%.';
    }

    if (payload.tax.enabled && (payload.tax.rate < 0 || payload.tax.rate > 100)) {
        errors.tax_rate = 'Tarif PPN harus berada di antara 0–100%.';
    }

    if (payload.dp_required_percent < 0 || payload.dp_required_percent > 100) {
        errors.dp_required_percent = 'Minimal DP harus berada di antara 0-100%.';
    }

    return errors;
};

Alpine.data('invoiceDraftForm', () => ({
    savingDraft: false,
    draftSaved: false,
    savedDraftId: '',
    savedAt: '',
    errorMessage: '',
    errorTitle: '',
    fieldErrors: {},

    get validationMessages() {
        return [...new Set(Object.values(this.fieldErrors))];
    },

    clearFieldError(field) {
        if (!field || !this.fieldErrors[field]) {
            return;
        }

        const { [field]: removed, ...remainingErrors } = this.fieldErrors;

        this.fieldErrors = remainingErrors;

        if (this.validationMessages.length === 0 && this.errorTitle === 'Form perlu diperiksa') {
            this.errorMessage = '';
            this.errorTitle = '';
        }
    },

    async submitDraft(event) {
        if (this.savingDraft) {
            return;
        }

        const form = event.currentTarget;
        const payload = buildInvoiceDraftPayload(form);

        this.fieldErrors = validateInvoiceDraft(payload);
        this.savingDraft = true;
        this.draftSaved = false;
        this.errorMessage = '';
        this.errorTitle = '';

        if (this.validationMessages.length > 0) {
            const [firstField] = Object.keys(this.fieldErrors);

            this.savingDraft = false;
            this.errorTitle = 'Form perlu diperiksa';
            this.errorMessage = 'Perbaiki field bertanda merah sebelum menyimpan draft.';
            this.$nextTick(() => {
                form
                    .querySelector(`[data-validation-field="${firstField}"]`)
                    ?.focus();
            });
            return;
        }

        try {
            const response = await saveInvoiceDraft(payload);

            this.savedDraftId = response.data.id;
            this.savedAt = new Intl.DateTimeFormat('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date(response.data.saved_at));
            this.draftSaved = true;
        } catch (error) {
            this.errorTitle = 'Draft gagal disimpan';
            this.errorMessage = error?.message ?? 'Draft belum dapat disimpan. Coba lagi.';
            this.fieldErrors = error?.errors ?? {};
        } finally {
            this.savingDraft = false;
        }
    },

    dismissDraftNotice() {
        this.draftSaved = false;
        this.errorMessage = '';
        this.errorTitle = '';
    },
}));

Alpine.data('dashboardRevenueChart', () => ({
    chart: null,
    selectedPeriod: 'monthly',
    periods: [
        { key: 'monthly', label: 'Bulanan' },
        { key: 'quarterly', label: 'Kuartal' },
        { key: 'yearly', label: 'Tahunan' },
    ],

    get currentDataset() {
        return dashboardRevenueDatasets[this.selectedPeriod];
    },

    init() {
        this.$nextTick(() => this.renderChart());
    },

    selectPeriod(period) {
        if (this.selectedPeriod === period || !dashboardRevenueDatasets[period]) {
            return;
        }

        this.selectedPeriod = period;

        if (!this.chart) {
            this.renderChart();
            return;
        }

        this.chart.data = this.buildChartData();
        this.chart.options.scales.y.suggestedMax = this.suggestedMax();
        this.chart.update();
    },

    renderChart() {
        const canvas = this.$refs.revenueChart;

        if (!canvas) {
            return;
        }

        this.chart = new Chart(canvas, {
            type: 'bar',
            data: this.buildChartData(),
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        align: 'end',
                        labels: {
                            boxHeight: 10,
                            boxWidth: 10,
                            color: '#344036',
                            font: {
                                family: 'Inter, Segoe UI, sans-serif',
                                size: 12,
                                weight: 600,
                            },
                            usePointStyle: true,
                        },
                    },
                    tooltip: {
                        backgroundColor: '#1d281f',
                        borderColor: 'rgba(255, 255, 255, 0.14)',
                        borderWidth: 1,
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${formatShortRupiah(context.parsed.y)}`,
                        },
                        padding: 12,
                        titleColor: '#ffffff',
                        bodyColor: '#eef5ea',
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#647067',
                            font: {
                                family: 'Inter, Segoe UI, sans-serif',
                                size: 12,
                                weight: 600,
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false,
                        },
                        grid: {
                            color: 'rgba(105, 119, 110, 0.18)',
                        },
                        suggestedMax: this.suggestedMax(),
                        ticks: {
                            callback: (value) => formatShortRupiah(Number(value)),
                            color: '#647067',
                            font: {
                                family: 'Inter, Segoe UI, sans-serif',
                                size: 12,
                            },
                            maxTicksLimit: 5,
                        },
                    },
                },
            },
        });
    },

    buildChartData() {
        const dataset = this.currentDataset;

        return {
            labels: dataset.labels,
            datasets: [
                {
                    label: 'Invoice terbit',
                    data: dataset.issued,
                    backgroundColor: '#52772c',
                    borderRadius: 8,
                    maxBarThickness: 42,
                },
                {
                    label: 'Tertagih',
                    data: dataset.paid,
                    backgroundColor: '#234c8c',
                    borderRadius: 8,
                    maxBarThickness: 42,
                },
            ],
        };
    },

    suggestedMax() {
        return Math.max(...this.currentDataset.issued) * 1.2;
    },
}));

Alpine.data('dashboardRecentActivities', () => ({
    activeFilter: 'all',
    filters: [
        { key: 'all', label: 'Semua' },
        { key: 'invoice', label: 'Invoice' },
        { key: 'payment', label: 'Pembayaran' },
        { key: 'reminder', label: 'Pengingat' },
    ],
    activities: [
        {
            id: 'act-001',
            type: 'invoice',
            tone: 'brand',
            title: 'Invoice INV-2026-0084 dikirim',
            description: 'PT Sinar Nusantara menerima invoice desain brand.',
            occurredAt: minutesAgo(10),
        },
        {
            id: 'act-002',
            type: 'payment',
            tone: 'success',
            title: 'Pembayaran diterima',
            description: 'CV Lautan Rasa membayar Rp12.750.000 melalui transfer bank.',
            occurredAt: minutesAgo(42),
        },
        {
            id: 'act-003',
            type: 'reminder',
            tone: 'warning',
            title: 'Invoice mendekati jatuh tempo',
            description: 'INV-2026-0078 perlu ditindaklanjuti dalam 2 hari.',
            occurredAt: minutesAgo(125),
        },
        {
            id: 'act-004',
            type: 'invoice',
            tone: 'muted',
            title: 'Draft invoice baru dibuat',
            description: 'Paket cetak katalog untuk PT Bumi Lestari masuk sebagai draft.',
            occurredAt: minutesAgo(980),
        },
        {
            id: 'act-005',
            type: 'payment',
            tone: 'success',
            title: 'Pembayaran parsial dicatat',
            description: 'PT Cakra Media membayar Rp4.250.000 untuk INV-2026-0076.',
            occurredAt: minutesAgo(1540),
        },
    ],

    get visibleActivities() {
        if (this.activeFilter === 'all') {
            return this.activities;
        }

        return this.activities.filter((activity) => activity.type === this.activeFilter);
    },

    setFilter(filter) {
        this.activeFilter = filter;
    },

    relativeTime(occurredAt) {
        const diffInMinutes = Math.max(1, Math.round((Date.now() - new Date(occurredAt).getTime()) / 60000));

        if (diffInMinutes < 60) {
            return `${diffInMinutes} menit lalu`;
        }

        const diffInHours = Math.round(diffInMinutes / 60);

        if (diffInHours < 24) {
            return `${diffInHours} jam lalu`;
        }

        return `${Math.round(diffInHours / 24)} hari lalu`;
    },

    formattedTime(occurredAt) {
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(occurredAt));
    },

    toneClass(tone) {
        return {
            brand: 'bg-brand-100 text-brand-800',
            success: 'bg-green-100 text-green-800',
            warning: 'bg-yellow-100 text-yellow-900',
            muted: 'bg-canvas text-muted',
        }[tone] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('recordPaymentForm', () => ({
    remainingAmount: 6450000,
    saving: false,
    savedPayment: null,
    fieldErrors: {},
    form: {
        amount: 6450000,
        paidAt: new Date().toISOString().slice(0, 10),
        method: 'Transfer BCA',
        reference: 'BCA-77389',
        notes: 'Pembayaran lanjutan dari pelanggan.',
    },

    get validationMessages() {
        return Object.values(this.fieldErrors);
    },

    get formattedAmount() {
        return formatRupiah(Number(this.form.amount) || 0);
    },

    useRemainingAmount() {
        this.form.amount = this.remainingAmount;
        this.clearFieldError('amount');
    },

    clearFieldError(field) {
        if (!field || !this.fieldErrors[field]) {
            return;
        }

        const { [field]: removed, ...remainingErrors } = this.fieldErrors;

        this.fieldErrors = remainingErrors;
    },

    validate() {
        const errors = {};
        const amount = Number(this.form.amount) || 0;

        if (amount <= 0) {
            errors.amount = 'Nominal pembayaran harus lebih dari Rp0.';
        } else if (amount > this.remainingAmount) {
            errors.amount = 'Nominal tidak boleh melebihi sisa tagihan.';
        }

        if (!this.form.paidAt) {
            errors.paidAt = 'Tanggal pembayaran wajib diisi.';
        }

        if (!this.form.method) {
            errors.method = 'Pilih metode pembayaran.';
        }

        if (!this.form.reference.trim()) {
            errors.reference = 'Nomor referensi wajib diisi.';
        }

        return errors;
    },

    submit() {
        if (this.saving) {
            return;
        }

        this.fieldErrors = this.validate();
        this.savedPayment = null;

        if (this.validationMessages.length > 0) {
            return;
        }

        this.saving = true;

        window.setTimeout(() => {
            this.savedPayment = {
                amount: this.formattedAmount,
                method: this.form.method,
                reference: this.form.reference,
            };
            this.saving = false;
        }, 450);
    },
}));

Alpine.data('rolePermissionsForm', (roleCode) => ({
    roleCode,
    saving: false,
    saved: false,
    savedMessage: '',
    errorMessage: '',

    selectedPermissions(form) {
        return [...form.querySelectorAll('input[name="permissions[]"]:checked')]
            .map((field) => field.value)
            .filter(Boolean);
    },

    async submit(event) {
        if (this.saving) {
            return;
        }

        const form = event.currentTarget;
        const permissions = this.selectedPermissions(form);

        this.saving = true;
        this.saved = false;
        this.errorMessage = '';

        try {
            const response = await fetch(form.action, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ permissions }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const firstError = Object.values(payload?.errors ?? {})?.[0]?.[0];

                throw new Error(firstError || payload?.message || 'Coba ulangi beberapa saat lagi.');
            }

            this.saved = true;
            this.savedMessage = `${payload?.data?.permission_count ?? permissions.length} izin aktif untuk role ${payload?.data?.role?.name ?? this.roleCode}.`;
        } catch (error) {
            this.errorMessage = error?.message ?? 'Coba ulangi beberapa saat lagi.';
        } finally {
            this.saving = false;
        }
    },
}));

Alpine.data('receivablesTable', (receivables = []) => ({
    query: '',
    statusFilter: 'all',
    sortKey: 'dueSort',
    sortDirection: 'asc',
    filters: [
        { key: 'all', label: 'Semua' },
        { key: 'Parsial', label: 'Parsial' },
        { key: 'Overdue', label: 'Overdue' },
    ],
    receivables,

    get filteredReceivables() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        return this.receivables
            .filter((receivable) => {
                const matchesStatus = this.statusFilter === 'all' || receivable.status === this.statusFilter;
                const matchesKeyword = !keyword ||
                    `${receivable.invoice} ${receivable.customer} ${receivable.status}`
                        .toLocaleLowerCase('id')
                        .includes(keyword);

                return matchesStatus && matchesKeyword;
            })
            .sort((first, second) => {
                const firstValue = first[this.sortKey];
                const secondValue = second[this.sortKey];

                if (typeof firstValue === 'number' && typeof secondValue === 'number') {
                    return this.sortDirection === 'asc'
                        ? firstValue - secondValue
                        : secondValue - firstValue;
                }

                return this.sortDirection === 'asc'
                    ? String(firstValue).localeCompare(String(secondValue), 'id')
                    : String(secondValue).localeCompare(String(firstValue), 'id');
            });
    },

    setStatusFilter(filter) {
        this.statusFilter = filter;
    },

    sortBy(key) {
        if (this.sortKey === key) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        this.sortKey = key;
        this.sortDirection = key === 'dueSort' ? 'asc' : 'desc';
    },

    sortIndicator(key) {
        if (this.sortKey !== key) {
            return 'sort';
        }

        return this.sortDirection === 'asc' ? 'asc' : 'desc';
    },

    statusClass(status) {
        return {
            Overdue: 'bg-red-100 text-red-800',
            Parsial: 'bg-brand-100 text-brand-800',
            Menunggu: 'bg-yellow-100 text-yellow-900',
        }[status] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('paymentHistoryTable', (payments = []) => ({
    query: '',
    statusFilter: 'all',
    methodFilter: 'all',
    filters: [
        { key: 'all', label: 'Semua Status' },
        { key: 'Terverifikasi', label: 'Terverifikasi' },
        { key: 'Menunggu', label: 'Menunggu' },
    ],
    methodOptions: [
        { key: 'all', label: 'Semua Metode' },
        { key: 'Transfer BCA', label: 'Transfer BCA' },
        { key: 'Transfer Mandiri', label: 'Transfer Mandiri' },
        { key: 'Kartu kredit', label: 'Kartu kredit' },
        { key: 'Tunai', label: 'Tunai' },
    ],
    payments,

    get isFiltered() {
        return this.query.trim() !== '' || this.statusFilter !== 'all' || this.methodFilter !== 'all';
    },

    get filteredPayments() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        return this.payments.filter((payment) => {
            const matchesStatus = this.statusFilter === 'all' || payment.status === this.statusFilter;
            const matchesMethod = this.methodFilter === 'all' || payment.method === this.methodFilter;
            const matchesKeyword = !keyword ||
                `${payment.invoice} ${payment.customer} ${payment.method} ${payment.reference} ${payment.status}`
                    .toLocaleLowerCase('id')
                    .includes(keyword);

            return matchesStatus && matchesMethod && matchesKeyword;
        });
    },

    setStatusFilter(filter) {
        this.statusFilter = filter;
    },

    resetFilters() {
        this.query = '';
        this.statusFilter = 'all';
        this.methodFilter = 'all';
    },

    statusClass(status) {
        return status === 'Terverifikasi'
            ? 'bg-green-100 text-green-800'
            : 'bg-yellow-100 text-yellow-900';
    },
}));

Alpine.data('customerPicker', () => ({
    open: false,
    query: '',
    selected: null,
    customers: [],
    loading: true,
    errorMessage: '',

    async init() {
        await this.loadCustomers();
    },

    get filteredCustomers() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        if (!keyword) {
            return this.customers;
        }

        return this.customers.filter((customer) =>
            `${customer.name} ${customer.email} ${customer.phone}`
                .toLocaleLowerCase('id')
                .includes(keyword),
        );
    },

    async loadCustomers() {
        this.loading = true;
        this.errorMessage = '';

        try {
            const response = await listCustomers();

            this.customers = response.data;
            this.selected = this.selected ?? this.customers[0] ?? null;
        } catch (error) {
            this.errorMessage = error?.message ?? 'Data pelanggan belum dapat dimuat.';
        } finally {
            this.loading = false;
        }
    },

    show() {
        if (this.loading || this.errorMessage) {
            return;
        }

        this.open = true;
        this.$nextTick(() => this.$refs.search.focus());
    },

    hide() {
        this.open = false;
        this.query = '';
    },

    choose(customer) {
        this.selected = customer;
        this.hide();
        this.$nextTick(() => this.$refs.trigger.focus());
    },
}));

Alpine.data('invoiceItems', () => ({
    products: [],
    items: [],
    nextKey: 1,
    loadingProducts: true,
    productError: '',
    productSearch: '',

    async init() {
        await this.loadProducts();
    },

    async loadProducts() {
        this.loadingProducts = true;
        this.productError = '';

        try {
            const response = await listProducts();

            this.products = response.data;
            this.seedItems();
        } catch (error) {
            this.products = [];
            this.items = [];
            this.productError = error?.message ?? 'Data produk belum dapat dimuat.';
        } finally {
            this.loadingProducts = false;
        }
    },

    seedItems() {
        if (this.items.length > 0 || this.products.length === 0) {
            return;
        }

        const defaults = this.products.slice(0, 2);

        this.items = defaults.map((product) => this.createItem(product));
    },

    createItem(product) {
        return {
            key: this.nextKey++,
            productId: product.id,
            productName: product.name,
            sku: product.sku ?? '',
            productSearch: this.productLabel(product),
            pickerOpen: false,
            cupSize: '12 Oz',
            cupModel: product.cup_model ?? 'Oval',
            grammage: product.grammage ?? '8gr',
            screenPrintingColor: product.screen_printing_color ?? 'Hitam',
            jenisCetak: product.sides ? `${product.sides} warna` : '1 warna',
            moqQuantity: Number(product.minimum_order_qty || product.moq_quantity) || 1,
            orderIncrement: Number(product.package_conversion || product.order_increment) || 1,
            packagingUnit: product.unit ?? product.packaging_unit ?? 'Pcs',
            quantity: Number(product.minimum_order_qty || product.moq_quantity) || 1,
            price: product.price ?? 0,
        };
    },

    productFor(productId) {
        return this.products.find((product) => product.id === Number(productId));
    },

    productName(productId) {
        return this.productFor(productId)?.name ?? 'Item invoice';
    },

    productLabel(product) {
        if (! product) {
            return '';
        }

        return [product.sku ?? product.code, product.name].filter(Boolean).join(' — ');
    },

    productMeta(product) {
        return [
            product.brand,
            product.category,
            product.short_description,
        ].filter(Boolean).join(' · ');
    },

    productSearchText(product) {
        return [
            product.sku,
            product.code,
            product.name,
            product.brand,
            product.category,
            product.short_description,
        ].filter(Boolean).join(' ').toLowerCase();
    },

    filteredProductsFor(item) {
        const keyword = (item.productSearch ?? '').trim().toLowerCase();

        if (! keyword) {
            return this.products.slice(0, 20);
        }

        const matches = this.products
            .filter((product) => this.productSearchText(product).includes(keyword))
            .slice(0, 20);
        const selected = this.productFor(item.productId);

        if (selected && ! matches.some((product) => Number(product.id) === Number(selected.id))) {
            return [selected, ...matches];
        }

        return matches;
    },

    filteredSelectionProducts(item) {
        const selected = this.productFor(item.productId);

        if (! selected) {
            return this.products;
        }

        return [
            selected,
            ...this.products.filter((product) => Number(product.id) !== Number(selected.id)),
        ];
    },

    openProductPicker(item) {
        item.pickerOpen = true;
    },

    toggleProductPicker(item) {
        item.pickerOpen = ! item.pickerOpen;
    },

    selectProduct(item, product) {
        item.productId = product.id;
        item.productSearch = this.productLabel(product);
        item.pickerOpen = false;
        this.updateProduct(item);
    },

    updateProduct(item) {
        const product = this.productFor(item.productId);

        if (product) {
            item.productName = product.name;
            item.sku = product.sku ?? '';
            item.productSearch = this.productLabel(product);
            item.cupSize = '12 Oz';
            item.cupModel = product.cup_model ?? item.cupModel;
            item.grammage = product.grammage ?? item.grammage;
            item.screenPrintingColor = product.screen_printing_color ?? item.screenPrintingColor;
            item.jenisCetak = product.sides ? `${product.sides} warna` : item.jenisCetak;
            item.moqQuantity = Number(product.minimum_order_qty || product.moq_quantity) || item.moqQuantity;
            item.orderIncrement = Number(product.package_conversion || product.order_increment) || item.orderIncrement;
            item.packagingUnit = product.unit ?? product.packaging_unit ?? item.packagingUnit;
            item.quantity = Math.max(Number(item.quantity) || 0, item.moqQuantity || 1);
            item.price = product.price ?? 0;
            this.normalizeQuantity(item);
        }
    },

    normalizeQuantity(item) {
        const minimum = Math.max(1, Number(item.moqQuantity) || 1);
        const increment = Math.max(1, Number(item.orderIncrement) || minimum);
        const requested = Math.max(minimum, Number(item.quantity) || minimum);
        const overflow = requested % increment;

        item.quantity = overflow === 0 ? requested : requested + (increment - overflow);
    },

    cupDescription(item) {
        const specs = [item.cupSize, item.cupModel, item.grammage ? `(${item.grammage})` : '']
            .filter(Boolean)
            .join(' ');
        const details = [
            item.screenPrintingColor ? `Tinta ${item.screenPrintingColor}` : '',
            item.jenisCetak ?? '',
        ].filter(Boolean).join(' - ');

        if (!specs) {
            return item.productName ?? this.productName(item.productId);
        }

        return `Sablon Cup ${specs}${details ? ` (${details})` : ''}`;
    },

    addItem() {
        const product = this.products[2] ?? this.products[0];

        if (product) {
            this.items.push(this.createItem(product));
        }
    },

    removeItem(key) {
        this.items = this.items.filter((item) => item.key !== key);
    },

    itemTotal(item) {
        return Math.max(0, Number(item.quantity) || 0) *
            Math.max(0, Number(item.price) || 0);
    },

    get subtotal() {
        return this.items.reduce((total, item) => total + this.itemTotal(item), 0);
    },

    formatCurrency(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value);
    },
}));

Alpine.data('invoicePreviewActions', () => ({
    invoiceId: 'INV-2026-0079',
    recipient: 'finance@sinarnusantara.co.id',
    savingDraft: false,
    draftSaved: false,
    sendingEmail: false,
    downloadingPdf: false,
    pdfDownloaded: false,
    invoiceStatus: 'Draft',
    notice: null,

    saveDraft() {
        if (this.savingDraft) {
            return;
        }

        this.savingDraft = true;
        this.draftSaved = false;

        window.setTimeout(() => {
            this.savingDraft = false;
            this.draftSaved = true;
            this.notice = {
                type: 'success',
                title: 'Draft berhasil disimpan',
                description: 'Status ini tersimpan untuk validasi alur pembayaran.',
            };
        }, 450);
    },

    async sendEmail() {
        if (this.sendingEmail) {
            return;
        }

        if (this.invoiceStatus === 'Terkirim') {
            this.notice = {
                type: 'success',
                title: 'Invoice sudah berstatus terkirim',
                description: `API telah mengirim invoice ini ke ${this.recipient}.`,
            };
            return;
        }

        this.sendingEmail = true;
        this.notice = null;

        try {
            const response = await sendInvoiceEmail({
                invoiceId: this.invoiceId,
                recipient: this.recipient,
            });

            this.invoiceStatus = response.data.status === 'sent' ? 'Terkirim' : 'Draft';
            this.notice = {
                type: 'success',
                title: 'Invoice berhasil dikirim',
                description: `Email terkirim via API ke ${response.data.recipient}.`,
            };
        } catch (error) {
            this.notice = {
                type: 'error',
                title: 'Invoice gagal dikirim',
                description: error?.message ?? 'Periksa koneksi lalu coba kirim kembali.',
            };
        } finally {
            this.sendingEmail = false;
        }
    },

    async downloadPdf() {
        if (this.downloadingPdf) {
            return;
        }

        this.downloadingPdf = true;
        this.pdfDownloaded = false;
        this.notice = null;

        try {
            const response = await downloadInvoicePdf(this.invoiceId);
            const downloadUrl = URL.createObjectURL(response.blob);
            const anchor = document.createElement('a');

            anchor.href = downloadUrl;
            anchor.download = response.filename;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);

            this.pdfDownloaded = true;
            this.notice = {
                type: 'success',
                title: 'PDF invoice berhasil diunduh',
                description: `${response.filename} dibuat melalui API invoice.`,
            };
        } catch (error) {
            this.notice = {
                type: 'error',
                title: 'PDF invoice gagal diunduh',
                description: error?.message ?? 'Periksa koneksi lalu coba unduh kembali.',
            };
        } finally {
            this.downloadingPdf = false;
        }
    },
}));

Alpine.data('salesReportTable', (initialSales = []) => ({
    query: '',
    statusFilter: 'all',
    categoryFilter: 'all',
    periodPreset: '2026-07',
    startDate: '2026-07-01',
    endDate: '2026-07-31',
    showCustomDate: false,
    exporting: false,
    exportSuccess: false,

    statusOptions: [
        { key: 'all', label: 'Semua Status' },
        { key: 'Lunas', label: 'Lunas' },
        { key: 'Menunggu', label: 'Menunggu' },
        { key: 'Parsial', label: 'Parsial' },
        { key: 'Overdue', label: 'Overdue' },
    ],

    categoryOptions: [
        { key: 'all', label: 'Semua Kategori' },
        { key: 'Jasa desain', label: 'Jasa desain' },
        { key: 'Cetak premium', label: 'Cetak premium' },
        { key: 'Materi promosi', label: 'Materi promosi' },
    ],

    periodOptions: [
        { key: '2026-07', label: 'Juli 2026' },
        { key: '2026-06', label: 'Juni 2026' },
        { key: 'Q2-2026', label: 'Kuartal 2 (Q2 2026)' },
        { key: '2026-YTD', label: 'Tahun 2026 (YTD)' },
        { key: 'custom', label: 'Rentang Kustom...' },
    ],

    sales: initialSales,

    get isFiltered() {
        return this.query.trim() !== '' || this.statusFilter !== 'all' || this.categoryFilter !== 'all' || this.periodPreset !== '2026-07';
    },

    selectPeriod(preset) {
        this.periodPreset = preset;
        if (preset === 'custom') {
            this.showCustomDate = true;
            return;
        }
        this.showCustomDate = false;
        if (preset === '2026-07') {
            this.startDate = '2026-07-01';
            this.endDate = '2026-07-31';
        } else if (preset === '2026-06') {
            this.startDate = '2026-06-01';
            this.endDate = '2026-06-30';
        } else if (preset === 'Q2-2026') {
            this.startDate = '2026-04-01';
            this.endDate = '2026-06-30';
        } else if (preset === '2026-YTD') {
            this.startDate = '2026-01-01';
            this.endDate = '2026-12-31';
        }
    },

    get filteredSales() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        return this.sales.filter((row) => {
            const matchesStatus = this.statusFilter === 'all' || row.status === this.statusFilter;
            const matchesCategory = this.categoryFilter === 'all' || row.category === this.categoryFilter;
            const matchesKeyword = !keyword ||
                `${row.customer} ${row.product} ${row.invoice} ${row.category} ${row.status}`
                    .toLocaleLowerCase('id')
                    .includes(keyword);

            let matchesDate = true;
            if (row.rawDate) {
                if (this.startDate && row.rawDate < this.startDate) matchesDate = false;
                if (this.endDate && row.rawDate > this.endDate) matchesDate = false;
            }

            return matchesStatus && matchesCategory && matchesKeyword && matchesDate;
        });
    },

    get totalSalesAmountFormatted() {
        const sum = this.filteredSales.reduce((acc, r) => acc + (r.rawAmount || 0), 0);
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(sum);
    },

    get totalCount() {
        return this.filteredSales.length;
    },

    setStatusFilter(key) {
        this.statusFilter = key;
    },

    resetFilters() {
        this.query = '';
        this.statusFilter = 'all';
        this.categoryFilter = 'all';
        this.selectPeriod('2026-07');
    },

    exportExcel() {
        if (this.exporting) return;
        this.exporting = true;
        this.exportSuccess = false;

        const headers = ['Pelanggan', 'Produk', 'Kategori', 'Invoice', 'Tanggal', 'Penjualan', 'Margin', 'Status'];
        const rows = this.filteredSales.map((r) => [
            `"${r.customer}"`,
            `"${r.product}"`,
            `"${r.category}"`,
            `"${r.invoice}"`,
            `"${r.date}"`,
            `"${r.amount}"`,
            `"${r.margin}"`,
            `"${r.status}"`,
        ]);

        const csvContent = '\uFEFF' + [headers.join(','), ...rows.map((e) => e.join(','))].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `laporan-penjualan-${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);

        this.exporting = false;
        this.exportSuccess = true;
        window.setTimeout(() => {
            this.exportSuccess = false;
        }, 3000);
    },

    statusClass(status) {
        return {
            Lunas: 'bg-green-100 text-green-800',
            Overdue: 'bg-red-100 text-red-800',
            Parsial: 'bg-brand-100 text-brand-800',
            Menunggu: 'bg-yellow-100 text-yellow-900',
        }[status] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('customerIndexTable', (initialCustomers = []) => ({
    query: '',
    statusFilter: 'all',
    segmentFilter: 'all',
    sortKey: 'lastOrderSort',
    sortDirection: 'desc',
    customers: initialCustomers,

    statusOptions: [
        { key: 'all', label: 'Semua Status' },
        { key: 'Aktif', label: 'Aktif' },
        { key: 'Perlu follow-up', label: 'Follow-up' },
        { key: 'Prospek', label: 'Prospek' },
    ],

    segmentOptions: [
        { key: 'all', label: 'Semua Segmen' },
        { key: 'Enterprise', label: 'Enterprise' },
        { key: 'Corporate', label: 'Corporate' },
        { key: 'UMKM', label: 'UMKM' },
        { key: 'Retail', label: 'Retail' },
    ],

    get isFiltered() {
        return this.query.trim() !== '' ||
            this.statusFilter !== 'all' ||
            this.segmentFilter !== 'all';
    },

    get filteredCustomers() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        return this.customers
            .filter((customer) => {
                const matchesStatus = this.statusFilter === 'all' || customer.status === this.statusFilter;
                const matchesSegment = this.segmentFilter === 'all' || customer.segment === this.segmentFilter;
                const matchesKeyword = !keyword ||
                    `${customer.code} ${customer.name} ${customer.email} ${customer.phone} ${customer.city} ${customer.segment} ${customer.status}`
                        .toLocaleLowerCase('id')
                        .includes(keyword);

                return matchesStatus && matchesSegment && matchesKeyword;
            })
            .sort((first, second) => {
                const firstValue = first[this.sortKey];
                const secondValue = second[this.sortKey];

                if (typeof firstValue === 'number' && typeof secondValue === 'number') {
                    return this.sortDirection === 'asc'
                        ? firstValue - secondValue
                        : secondValue - firstValue;
                }

                return this.sortDirection === 'asc'
                    ? String(firstValue).localeCompare(String(secondValue), 'id')
                    : String(secondValue).localeCompare(String(firstValue), 'id');
            });
    },

    get visibleSalesFormatted() {
        const total = this.filteredCustomers.reduce((sum, customer) => sum + (customer.totalSalesValue || 0), 0);

        return formatRupiah(total);
    },

    get resultSummary() {
        const filters = [];

        if (this.query.trim() !== '') {
            filters.push(`pencarian "${this.query.trim()}"`);
        }

        if (this.statusFilter !== 'all') {
            filters.push(`status ${this.statusFilter}`);
        }

        if (this.segmentFilter !== 'all') {
            filters.push(`segmen ${this.segmentFilter}`);
        }

        if (filters.length === 0) {
            return `${this.filteredCustomers.length} pelanggan ditampilkan.`;
        }

        return `${this.filteredCustomers.length} pelanggan cocok dengan ${filters.join(', ')}.`;
    },

    setStatusFilter(status) {
        this.statusFilter = status;
    },

    sortBy(key) {
        if (this.sortKey === key) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            return;
        }

        this.sortKey = key;
        this.sortDirection = ['name', 'segment'].includes(key) ? 'asc' : 'desc';
    },

    sortIndicator(key) {
        if (this.sortKey !== key) {
            return 'sort';
        }

        return this.sortDirection === 'asc' ? 'asc' : 'desc';
    },

    resetFilters() {
        this.query = '';
        this.statusFilter = 'all';
        this.segmentFilter = 'all';
    },

    statusClass(status) {
        return {
            Aktif: 'bg-green-100 text-green-800',
            'Perlu follow-up': 'bg-yellow-100 text-yellow-900',
            Prospek: 'bg-accent-soft text-accent',
        }[status] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('customerForm', (initialForm = {}, isEditMode = false) => ({
    isEdit: isEditMode,
    saving: false,
    saved: false,
    fieldErrors: {},
    form: {
        code: initialForm.code ?? 'CUS-007',
        name: initialForm.name ?? '',
        segment: initialForm.segment ?? 'UMKM',
        email: initialForm.email ?? '',
        phone: initialForm.phone ?? '',
        taxNumber: initialForm.taxNumber ?? '',
        address: initialForm.address ?? '',
        city: initialForm.city ?? '',
        province: initialForm.province ?? '',
        postalCode: initialForm.postalCode ?? '',
        status: initialForm.status ?? 'Aktif',
        notes: initialForm.notes ?? '',
    },

    get validationMessages() {
        return Object.values(this.fieldErrors);
    },

    get initials() {
        const words = this.form.name.trim().split(/\s+/).filter(Boolean);

        if (words.length === 0) {
            return 'PL';
        }

        return words
            .slice(0, 2)
            .map((word) => word[0]?.toLocaleUpperCase('id') ?? '')
            .join('');
    },

    clearFieldError(field) {
        if (!field || !this.fieldErrors[field]) {
            return;
        }

        const { [field]: removed, ...remainingErrors } = this.fieldErrors;

        this.fieldErrors = remainingErrors;
    },

    validate() {
        const errors = {};
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!this.form.code.trim()) {
            errors.code = 'Kode pelanggan wajib diisi.';
        }

        if (!this.form.name.trim()) {
            errors.name = 'Nama pelanggan wajib diisi.';
        }

        if (!this.form.email.trim()) {
            errors.email = 'Email pelanggan wajib diisi.';
        } else if (!emailPattern.test(this.form.email.trim())) {
            errors.email = 'Format email pelanggan belum valid.';
        }

        if (!this.form.phone.trim()) {
            errors.phone = 'Nomor telepon wajib diisi.';
        }

        if (!this.form.address.trim()) {
            errors.address = 'Alamat penagihan wajib diisi.';
        }

        if (!this.form.city.trim()) {
            errors.city = 'Kota wajib diisi.';
        }

        return errors;
    },

    submit() {
        if (this.saving) {
            return;
        }

        this.fieldErrors = this.validate();
        this.saved = false;

        if (this.validationMessages.length > 0) {
            return;
        }

        this.saving = true;

        window.setTimeout(() => {
            this.saved = true;
            this.saving = false;
        }, 450);
    },

    statusClass(status) {
        return {
            Aktif: 'bg-green-100 text-green-800',
            'Perlu follow-up': 'bg-yellow-100 text-yellow-900',
            Prospek: 'bg-accent-soft text-accent',
            Nonaktif: 'bg-canvas text-muted',
        }[status] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('productIndexTable', (initialProducts = []) => ({
    query: '',
    statusFilter: 'all',
    categoryFilter: 'all',
    products: initialProducts,
    loading: false,
    error: '',

    statusOptions: [
        { key: 'all', label: 'Semua Status' },
        { key: 'Aktif', label: 'Aktif' },
        { key: 'Stok menipis', label: 'Stok menipis' },
        { key: 'Nonaktif', label: 'Nonaktif' },
    ],

    categoryOptions: [
        { key: 'all', label: 'Semua Kategori' },
    ],

    async init() {
        await this.loadProducts();
    },

    async loadProducts() {
        this.loading = true;
        this.error = '';

        try {
            const response = await listProductCatalog();

            this.products = response.data.map((product) => this.normalizeProduct(product));
            this.categoryOptions = [
                { key: 'all', label: 'Semua Kategori' },
                ...[...new Set(this.products.map((product) => product.category).filter(Boolean))]
                    .sort((a, b) => a.localeCompare(b, 'id'))
                    .map((category) => ({ key: category, label: category })),
            ];
        } catch (error) {
            this.error = error?.message ?? 'Data produk belum dapat dimuat.';
        } finally {
            this.loading = false;
        }
    },

    normalizeProduct(product) {
        const stockValue = product.stock === null ? 0 : Number(product.stock) || 0;
        const minimumStock = Number(product.minimum_stock) || 0;
        const status = product.status === 'inactive'
            ? 'Nonaktif'
            : minimumStock > 0 && stockValue <= minimumStock
                ? 'Stok menipis'
                : 'Aktif';

        return {
            id: product.id,
            sku: product.sku ?? product.code,
            name: product.name,
            category: product.category ?? 'Lainnya',
            brand: product.brand ?? '',
            unit: product.unit ?? 'Pcs',
            purchasePrice: formatRupiah(Number(product.purchase_price) || 0),
            purchasePriceValue: Number(product.purchase_price) || 0,
            stock: product.track_stock === false ? 'Tidak dilacak' : `${stockValue} ${product.unit ?? 'Pcs'}`,
            stockValue: product.track_stock === false ? 999999 : stockValue,
            minimumStock,
            sales: product.sales ?? 0,
            status,
            rawStatus: product.status,
            trackStock: product.track_stock,
        };
    },

    get isFiltered() {
        return this.query.trim() !== '' ||
            this.statusFilter !== 'all' ||
            this.categoryFilter !== 'all';
    },

    get filteredProducts() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        return this.products.filter((product) => {
            const matchesStatus = this.statusFilter === 'all' || product.status === this.statusFilter;
            const matchesCategory = this.categoryFilter === 'all' || product.category === this.categoryFilter;
            const matchesKeyword = !keyword ||
                `${product.sku} ${product.name} ${product.category} ${product.status}`
                    .toLocaleLowerCase('id')
                    .includes(keyword);

            return matchesStatus && matchesCategory && matchesKeyword;
        });
    },

    get lowStockProducts() {
        return this.products.filter((product) => this.isLowStock(product));
    },

    get lowStockSummary() {
        const [firstProduct] = this.lowStockProducts;

        if (!firstProduct) {
            return 'Semua produk berada di atas minimum stok.';
        }

        return `${this.lowStockProducts.length} produk berada di bawah minimum stok, termasuk ${firstProduct.name}.`;
    },

    get visibleCatalogValueFormatted() {
        const total = this.filteredProducts.reduce((sum, product) => {
            const stockMultiplier = product.stockValue >= 999 ? 1 : product.stockValue;

            return sum + ((product.purchasePriceValue || 0) * stockMultiplier);
        }, 0);

        return formatRupiah(total);
    },

    get resultSummary() {
        const filters = [];

        if (this.query.trim() !== '') {
            filters.push(`pencarian "${this.query.trim()}"`);
        }

        if (this.statusFilter !== 'all') {
            filters.push(`status ${this.statusFilter}`);
        }

        if (this.categoryFilter !== 'all') {
            filters.push(`kategori ${this.categoryFilter}`);
        }

        if (filters.length === 0) {
            return `${this.filteredProducts.length} produk ditampilkan.`;
        }

        return `${this.filteredProducts.length} produk cocok dengan ${filters.join(', ')}.`;
    },

    setStatusFilter(status) {
        this.statusFilter = status;
    },

    isLowStock(product) {
        return product.status !== 'Nonaktif' &&
            product.minimumStock > 0 &&
            product.stockValue <= product.minimumStock;
    },

    resetFilters() {
        this.query = '';
        this.statusFilter = 'all';
        this.categoryFilter = 'all';
    },

    async deleteProduct(product) {
        if (!window.confirm(`Hapus produk ${product.sku} - ${product.name}?`)) {
            return;
        }

        await deleteProduct(product.id);
        this.products = this.products.filter((item) => item.id !== product.id);
    },

    statusClass(status) {
        return {
            Aktif: 'bg-green-100 text-green-800',
            'Stok menipis': 'bg-yellow-100 text-yellow-900',
            Nonaktif: 'bg-canvas text-muted',
        }[status] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('productForm', (initialForm = {}, isEditMode = false) => ({
    isEdit: isEditMode,
    saving: false,
    saved: false,
    loading: false,
    error: '',
    fieldErrors: {},
    form: {
        id: initialForm.id ?? null,
        sku: initialForm.sku ?? 'PRN-NEW-01',
        name: initialForm.name ?? '',
        category: initialForm.category ?? 'Cetak premium',
        brand: initialForm.brand ?? '',
        unit: initialForm.unit ?? 'Pcs',
        purchasePrice: initialForm.purchasePrice ?? initialForm.purchase_price ?? 0,
        stock: initialForm.stock ?? 10,
        minimumStock: initialForm.minimumStock ?? 5,
        minimumOrderQty: initialForm.minimumOrderQty ?? initialForm.minimum_order_qty ?? 1000,
        packageConversion: initialForm.packageConversion ?? initialForm.package_conversion ?? 500,
        shortDescription: initialForm.shortDescription ?? initialForm.short_description ?? '',
        status: initialForm.status ?? 'Aktif',
        description: initialForm.description ?? '',
        trackStock: initialForm.trackStock ?? true,
    },

    async init() {
        if (!this.isEdit || !this.form.id) {
            this.form.sku = '';

            return;
        }

        this.loading = true;
        this.error = '';

        try {
            const response = await getProduct(this.form.id);

            this.form = this.normalizeProduct(response.data);
        } catch (error) {
            this.error = error?.message ?? 'Detail produk belum dapat dimuat.';
        } finally {
            this.loading = false;
        }
    },

    normalizeProduct(product) {
        return {
            id: product.id,
            sku: product.sku ?? product.code ?? '',
            name: product.name ?? '',
            category: product.category ?? 'Cup PP',
            brand: product.brand ?? '',
            unit: product.unit ?? 'Pcs',
            purchasePrice: Number(product.purchase_price) || 0,
            stock: Number(product.stock) || 0,
            minimumStock: Number(product.minimum_stock) || 0,
            minimumOrderQty: Number(product.minimum_order_qty) || 1000,
            packageConversion: Number(product.package_conversion) || 500,
            shortDescription: product.short_description ?? '',
            status: product.status === 'inactive' ? 'Nonaktif' : 'Aktif',
            description: product.description ?? '',
            trackStock: product.track_stock ?? true,
        };
    },

    get validationMessages() {
        return Object.values(this.fieldErrors);
    },

    get formattedPurchasePrice() {
        return formatRupiah(Number(this.form.purchasePrice) || 0);
    },

    get stockLabel() {
        if (!this.form.trackStock) {
            return 'Tidak dilacak';
        }

        return `${Number(this.form.stock) || 0} ${this.form.unit}`;
    },

    clearFieldError(field) {
        if (!field || !this.fieldErrors[field]) {
            return;
        }

        const { [field]: removed, ...remainingErrors } = this.fieldErrors;

        this.fieldErrors = remainingErrors;
    },

    validate() {
        const errors = {};

        if (!this.form.name.trim()) {
            errors.name = 'Nama produk wajib diisi.';
        }

        if ((Number(this.form.purchasePrice) || 0) < 0) {
            errors.purchasePrice = 'Harga beli tidak boleh bernilai negatif.';
        }

        if (this.form.trackStock) {
            if ((Number(this.form.stock) || 0) < 0) {
                errors.stock = 'Stok tidak boleh bernilai negatif.';
            }

            if ((Number(this.form.minimumStock) || 0) < 0) {
                errors.minimumStock = 'Minimum stok tidak boleh bernilai negatif.';
            }
        }

        if ((Number(this.form.minimumOrderQty) || 0) < 1) {
            errors.minimumOrderQty = 'Minimum order wajib minimal 1 pcs.';
        }

        if ((Number(this.form.packageConversion) || 0) < 1) {
            errors.packageConversion = 'Kelipatan order wajib minimal 1 pcs.';
        }

        return errors;
    },

    payload() {
        return {
            sku: this.form.sku.trim() || null,
            name: this.form.name.trim(),
            category: this.form.category,
            brand: this.form.brand || null,
            unit: 'Pcs',
            purchase_price: Number(this.form.purchasePrice) || 0,
            stock: this.form.trackStock ? Number(this.form.stock) || 0 : null,
            minimum_stock: this.form.trackStock ? Number(this.form.minimumStock) || 0 : 0,
            minimum_order_qty: Number(this.form.minimumOrderQty) || 1000,
            package_conversion: Number(this.form.packageConversion) || 500,
            short_description: this.form.shortDescription || null,
            description: this.form.description || this.form.shortDescription || null,
            track_stock: Boolean(this.form.trackStock),
            status: this.form.status === 'Nonaktif' ? 'inactive' : 'active',
        };
    },

    applyApiErrors(errors = {}) {
        this.fieldErrors = Object.entries(errors).reduce((fields, [field, messages]) => ({
            ...fields,
            [field.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase())]: Array.isArray(messages) ? messages[0] : messages,
        }), {});
    },

    async submit() {
        if (this.saving) {
            return;
        }

        this.fieldErrors = this.validate();
        this.saved = false;

        if (this.validationMessages.length > 0) {
            return;
        }

        this.saving = true;
        this.error = '';

        try {
            const response = this.isEdit
                ? await updateCatalogProduct(this.form.id, this.payload())
                : await createProduct(this.payload());

            this.form = this.normalizeProduct(response.data);
            this.saved = true;
        } catch (error) {
            this.error = error?.message ?? 'Produk belum dapat disimpan.';

            if (error?.errors) {
                this.applyApiErrors(error.errors);
            }
        } finally {
            this.saving = false;
        }
    },

    statusClass(status) {
        return {
            Aktif: 'bg-green-100 text-green-800',
            'Stok menipis': 'bg-yellow-100 text-yellow-900',
            Nonaktif: 'bg-canvas text-muted',
        }[status] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('companyProfileSettings', (initialForm = {}) => ({
    saving: false,
    saved: false,
    savedAt: '',
    fieldErrors: {},
    logoPreview: '',
    logoFileName: '',
    logoFile: null,
    loading: false,
    selectedPalette: 'sage',
    invoiceTemplateOptions: [
        {
            key: 'professional',
            label: 'Professional clean',
            description: 'Layout formal dengan header ringkas untuk invoice korporat.',
        },
        {
            key: 'modern',
            label: 'Modern compact',
            description: 'Layout padat untuk invoice cepat dan hemat ruang.',
        },
        {
            key: 'creative',
            label: 'Creative bold',
            description: 'Layout visual dengan aksen kuat untuk jasa kreatif.',
        },
    ],
    themePalettes: [
        {
            key: 'sage',
            label: 'Sage profesional',
            description: 'Hijau kalem untuk identitas bisnis stabil.',
            primary: '#52772c',
            accent: '#234c8c',
            soft: '#eef5ea',
        },
        {
            key: 'ocean',
            label: 'Ocean blue',
            description: 'Biru bersih untuk layanan modern.',
            primary: '#2563eb',
            accent: '#0f766e',
            soft: '#eff6ff',
        },
        {
            key: 'sunset',
            label: 'Sunset amber',
            description: 'Amber hangat untuk brand kreatif.',
            primary: '#d97706',
            accent: '#be123c',
            soft: '#fff7ed',
        },
        {
            key: 'ink',
            label: 'Ink premium',
            description: 'Gelap elegan untuk invoice korporat.',
            primary: '#1f2937',
            accent: '#7c3aed',
            soft: '#f3f4f6',
        },
    ],
    form: {
        businessName: initialForm.businessName ?? '',
        legalName: initialForm.legalName ?? '',
        businessType: initialForm.businessType ?? 'Perseroan Terbatas',
        registrationNumber: initialForm.registrationNumber ?? '',
        industry: initialForm.industry ?? '',
        businessScale: initialForm.businessScale ?? 'UMKM',
        foundedYear: initialForm.foundedYear ?? '',
        picName: initialForm.picName ?? '',
        picRole: initialForm.picRole ?? '',
        email: initialForm.email ?? '',
        phone: initialForm.phone ?? '',
        website: initialForm.website ?? '',
        taxNumber: initialForm.taxNumber ?? '',
        invoicePrefix: initialForm.invoicePrefix ?? 'INV',
        bankName: initialForm.bankName ?? '',
        bankAccount: initialForm.bankAccount ?? '',
        bankHolder: initialForm.bankHolder ?? '',
        address: initialForm.address ?? '',
        city: initialForm.city ?? '',
        province: initialForm.province ?? '',
        postalCode: initialForm.postalCode ?? '',
        brandColor: initialForm.brandColor ?? '#52772c',
        invoiceTemplate: initialForm.invoiceTemplate ?? 'professional',
        defaultTaxRate: initialForm.defaultTaxRate ?? 11,
        defaultDueDays: initialForm.defaultDueDays ?? 14,
        reminderDaysBeforeDue: initialForm.reminderDaysBeforeDue ?? 3,
        numberingReset: initialForm.numberingReset ?? 'yearly',
        notes: initialForm.notes ?? '',
    },

    init() {
        this.loadInitialData();
    },

    get initials() {
        return this.form.businessName
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word[0]?.toLocaleUpperCase('id') ?? '')
            .join('') || 'IH';
    },

    get fullAddress() {
        return [this.form.address, this.form.city, this.form.province, this.form.postalCode]
            .filter(Boolean)
            .join(', ');
    },

    get paymentLine() {
        return `${this.form.bankName || 'Bank'} ${this.form.bankAccount || '-'} a.n. ${this.form.bankHolder || this.form.legalName || this.form.businessName}`;
    },

    get sampleInvoiceNumber() {
        return `${this.form.invoicePrefix || 'INV'}-2026-0085`;
    },

    get validationMessages() {
        return Object.values(this.fieldErrors);
    },

    get currentPalette() {
        return this.themePalettes.find((palette) => palette.key === this.selectedPalette) ?? this.themePalettes[0];
    },

    get currentInvoiceTemplate() {
        return this.invoiceTemplateOptions.find((template) => template.key === this.form.invoiceTemplate) ?? this.invoiceTemplateOptions[0];
    },

    get themeLivePreviewStyle() {
        return `--theme-primary: ${this.form.brandColor}; --theme-accent: ${this.currentPalette.accent}; --theme-soft: ${this.currentPalette.soft};`;
    },

    selectPalette(palette) {
        this.selectedPalette = palette.key;
        this.form.brandColor = palette.primary;
    },

    clearFieldError(field) {
        if (!field || !this.fieldErrors[field]) {
            return;
        }

        const { [field]: removed, ...remainingErrors } = this.fieldErrors;

        this.fieldErrors = remainingErrors;
    },

    handleLogoUpload(event) {
        const [file] = event.target.files ?? [];

        if (!file) {
            return;
        }

        this.clearFieldError('logo');
        this.logoFile = file;
        this.logoFileName = file.name;

        const reader = new FileReader();

        reader.addEventListener('load', () => {
            this.logoPreview = String(reader.result ?? '');
        });
        reader.readAsDataURL(file);
    },

    clearLogo() {
        this.logoPreview = '';
        this.logoFileName = '';
        this.logoFile = null;
    },

    async loadInitialData() {
        this.loading = true;

        try {
            await Promise.all([
                this.loadCompanyProfile(),
                this.loadThemeDefaults(),
            ]);
        } catch (error) {
            console.warn('Gagal memuat pengaturan profil usaha.', error);
        } finally {
            this.loading = false;
        }
    },

    async loadCompanyProfile() {
        const response = await fetch('/api/company-profile', {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        if (payload?.data) {
            this.applyCompanyProfile(payload.data);
        }
    },

    async loadThemeDefaults() {
        const response = await fetch('/api/settings/theme-defaults', {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json();

        if (payload?.data) {
            this.applyThemeDefaults(payload.data);
        }
    },

    applyCompanyProfile(profile) {
        this.form = {
            ...this.form,
            businessName: profile.business_name ?? this.form.businessName,
            legalName: profile.legal_name ?? '',
            businessType: profile.business_type ?? this.form.businessType,
            registrationNumber: profile.registration_number ?? '',
            industry: profile.industry ?? '',
            businessScale: profile.business_scale ?? this.form.businessScale,
            foundedYear: profile.founded_year ?? '',
            picName: profile.pic_name ?? '',
            picRole: profile.pic_role ?? '',
            email: profile.email ?? this.form.email,
            phone: profile.phone ?? '',
            website: profile.website ?? '',
            taxNumber: profile.tax_number ?? '',
            invoicePrefix: profile.invoice_prefix ?? this.form.invoicePrefix,
            bankName: profile.bank_name ?? '',
            bankAccount: profile.bank_account ?? '',
            bankHolder: profile.bank_holder ?? '',
            address: profile.address ?? this.form.address,
            city: profile.city ?? '',
            province: profile.province ?? '',
            postalCode: profile.postal_code ?? '',
            brandColor: profile.brand_color ?? this.form.brandColor,
            invoiceTemplate: profile.invoice_template ?? this.form.invoiceTemplate,
            defaultTaxRate: Number(profile.default_tax_rate ?? this.form.defaultTaxRate),
            defaultDueDays: Number(profile.default_due_days ?? this.form.defaultDueDays),
            reminderDaysBeforeDue: Number(profile.reminder_days_before_due ?? this.form.reminderDaysBeforeDue),
            numberingReset: profile.numbering_reset ?? this.form.numberingReset,
            notes: profile.notes ?? '',
        };

        if (profile.logo_url) {
            this.logoPreview = profile.logo_url;
            this.logoFileName = profile.logo_path?.split('/').pop() ?? 'Logo tersimpan';
        }

        if (profile.metadata?.palette) {
            this.selectedPalette = profile.metadata.palette;
        }
    },

    applyThemeDefaults(settings) {
        if (Array.isArray(settings.theme?.palettes) && settings.theme.palettes.length > 0) {
            this.themePalettes = settings.theme.palettes.map((palette) => ({
                key: palette.key,
                label: palette.label,
                description: palette.description,
                primary: palette.primary,
                accent: palette.accent,
                soft: palette.soft,
            }));
        }

        this.selectedPalette = settings.theme?.default_palette ?? this.selectedPalette;
        this.form.invoiceTemplate = settings.theme?.invoice_template ?? this.form.invoiceTemplate;
        this.form.invoicePrefix = settings.invoice_defaults?.invoice_prefix ?? this.form.invoicePrefix;
        this.form.defaultTaxRate = Number(settings.invoice_defaults?.default_tax_rate ?? this.form.defaultTaxRate);
        this.form.defaultDueDays = Number(settings.invoice_defaults?.default_due_days ?? this.form.defaultDueDays);
        this.form.reminderDaysBeforeDue = Number(settings.invoice_defaults?.reminder_days_before_due ?? this.form.reminderDaysBeforeDue);
        this.form.numberingReset = settings.invoice_defaults?.numbering_reset ?? this.form.numberingReset;
    },

    validate() {
        const errors = {};
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!this.form.businessName.trim()) {
            errors.businessName = 'Nama usaha wajib diisi.';
        }

        if (!this.form.email.trim()) {
            errors.email = 'Email penagihan wajib diisi.';
        } else if (!emailPattern.test(this.form.email.trim())) {
            errors.email = 'Format email penagihan belum valid.';
        }

        if (!this.form.address.trim()) {
            errors.address = 'Alamat usaha wajib diisi.';
        }

        if (!this.form.invoicePrefix.trim()) {
            errors.invoicePrefix = 'Prefix invoice wajib diisi.';
        }

        if ((Number(this.form.defaultTaxRate) || 0) < 0 || (Number(this.form.defaultTaxRate) || 0) > 100) {
            errors.defaultTaxRate = 'PPN default harus berada di antara 0-100%.';
        }

        if ((Number(this.form.defaultDueDays) || 0) < 0) {
            errors.defaultDueDays = 'Jatuh tempo default tidak boleh negatif.';
        }

        if ((Number(this.form.reminderDaysBeforeDue) || 0) < 0) {
            errors.reminderDaysBeforeDue = 'Pengingat jatuh tempo tidak boleh negatif.';
        }

        return errors;
    },

    async submit() {
        if (this.saving) {
            return;
        }

        this.fieldErrors = this.validate();
        this.saved = false;
        this.savedAt = '';

        if (Object.keys(this.fieldErrors).length > 0) {
            this.$nextTick(() => {
                const [firstField] = Object.keys(this.fieldErrors);

                document.querySelector(`[data-validation-field="${firstField}"]`)?.focus();
            });
            return;
        }

        this.saving = true;

        try {
            await this.saveCompanyProfile();
            await this.saveThemeDefaults();

            if (this.logoFile) {
                await this.uploadCompanyLogo();
            }

            this.saved = true;
            this.savedAt = new Intl.DateTimeFormat('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date());
        } catch (error) {
            if (error?.errors) {
                this.fieldErrors = this.normalizeServerErrors(error.errors);
                this.$nextTick(() => {
                    const [firstField] = Object.keys(this.fieldErrors);

                    document.querySelector(`[data-validation-field="${firstField}"]`)?.focus();
                });
                return;
            }

            this.fieldErrors = {
                general: 'Pengaturan belum tersimpan. Coba ulangi beberapa saat lagi.',
            };
            console.error('Gagal menyimpan profil usaha.', error);
        } finally {
            this.saving = false;
        }
    },

    async saveCompanyProfile() {
        const response = await this.requestJson('/api/company-profile', {
            method: 'PUT',
            body: JSON.stringify(this.companyProfilePayload()),
        });

        if (response?.data) {
            this.applyCompanyProfile(response.data);
        }
    },

    async saveThemeDefaults() {
        const response = await this.requestJson('/api/settings/theme-defaults', {
            method: 'PUT',
            body: JSON.stringify(this.themeDefaultPayload()),
        });

        if (response?.data) {
            this.applyThemeDefaults(response.data);
        }
    },

    async uploadCompanyLogo() {
        const formData = new FormData();
        formData.append('logo', this.logoFile);

        const response = await fetch('/api/company-profile/logo', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
            },
            body: formData,
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw payload;
        }

        if (payload?.data) {
            this.applyCompanyProfile(payload.data);
            this.logoFile = null;
        }
    },

    async requestJson(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers ?? {}),
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw payload;
        }

        return payload;
    },

    companyProfilePayload() {
        return {
            business_name: this.form.businessName,
            legal_name: this.form.legalName,
            business_type: this.form.businessType,
            registration_number: this.form.registrationNumber,
            industry: this.form.industry,
            business_scale: this.form.businessScale,
            founded_year: this.form.foundedYear || null,
            pic_name: this.form.picName,
            pic_role: this.form.picRole,
            email: this.form.email,
            phone: this.form.phone,
            website: this.form.website,
            tax_number: this.form.taxNumber,
            invoice_prefix: this.form.invoicePrefix,
            bank_name: this.form.bankName,
            bank_account: this.form.bankAccount,
            bank_holder: this.form.bankHolder,
            address: this.form.address,
            city: this.form.city,
            province: this.form.province,
            postal_code: this.form.postalCode,
            brand_color: this.form.brandColor,
            invoice_template: this.form.invoiceTemplate,
            default_tax_rate: this.form.defaultTaxRate,
            default_due_days: this.form.defaultDueDays,
            reminder_days_before_due: this.form.reminderDaysBeforeDue,
            numbering_reset: this.form.numberingReset,
            notes: this.form.notes,
            metadata: {
                palette: this.selectedPalette,
            },
        };
    },

    themeDefaultPayload() {
        return {
            default_palette: this.selectedPalette,
            invoice_template: this.form.invoiceTemplate,
            invoice_prefix: this.form.invoicePrefix,
            default_tax_rate: this.form.defaultTaxRate,
            default_due_days: this.form.defaultDueDays,
            reminder_days_before_due: this.form.reminderDaysBeforeDue,
            numbering_reset: this.form.numberingReset,
        };
    },

    normalizeServerErrors(errors) {
        const fieldMap = {
            business_name: 'businessName',
            legal_name: 'legalName',
            business_type: 'businessType',
            registration_number: 'registrationNumber',
            business_scale: 'businessScale',
            founded_year: 'foundedYear',
            pic_name: 'picName',
            pic_role: 'picRole',
            tax_number: 'taxNumber',
            invoice_prefix: 'invoicePrefix',
            bank_name: 'bankName',
            bank_account: 'bankAccount',
            bank_holder: 'bankHolder',
            postal_code: 'postalCode',
            brand_color: 'brandColor',
            invoice_template: 'invoiceTemplate',
            default_tax_rate: 'defaultTaxRate',
            default_due_days: 'defaultDueDays',
            reminder_days_before_due: 'reminderDaysBeforeDue',
            numbering_reset: 'numberingReset',
            default_palette: 'selectedPalette',
        };

        return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [
            fieldMap[field] ?? field,
            Array.isArray(messages) ? messages[0] : String(messages),
        ]));
    },
}));

const salesReportDatasets = {
    monthly: {
        label: '6 bulan terakhir',
        labels: ['Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'],
        revenue: [46000000, 58000000, 52000000, 71000000, 64000000, 86400000],
        target: [40000000, 50000000, 50000000, 65000000, 65000000, 80000000],
    },
    quarterly: {
        label: '4 kuartal terakhir',
        labels: ['Q4 2025', 'Q1 2026', 'Q2 2026', 'Q3 2026'],
        revenue: [142000000, 168000000, 187000000, 221400000],
        target: [130000000, 150000000, 175000000, 200000000],
    },
    yearly: {
        label: '3 tahun terakhir',
        labels: ['2024', '2025', '2026'],
        revenue: [720000000, 940000000, 1180000000],
        target: [650000000, 850000000, 1000000000],
    },
};

Alpine.data('salesReportChart', () => ({
    chart: null,
    selectedPeriod: 'monthly',
    periods: [
        { key: 'monthly', label: 'Bulanan' },
        { key: 'quarterly', label: 'Kuartal' },
        { key: 'yearly', label: 'Tahunan' },
    ],

    get currentDataset() {
        return salesReportDatasets[this.selectedPeriod];
    },

    init() {
        this.$nextTick(() => this.renderChart());
    },

    selectPeriod(period) {
        if (this.selectedPeriod === period || !salesReportDatasets[period]) {
            return;
        }

        this.selectedPeriod = period;

        if (!this.chart) {
            this.renderChart();
            return;
        }

        this.chart.data = this.buildChartData();
        this.chart.options.scales.y.suggestedMax = this.suggestedMax();
        this.chart.update();
    },

    renderChart() {
        const canvas = this.$refs.salesChart;

        if (!canvas) {
            return;
        }

        this.chart = new Chart(canvas, {
            type: 'bar',
            data: this.buildChartData(),
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
                plugins: {
                    legend: {
                        align: 'end',
                        labels: {
                            boxHeight: 10,
                            boxWidth: 10,
                            color: '#344036',
                            font: {
                                family: 'Inter, Segoe UI, sans-serif',
                                size: 12,
                                weight: 600,
                            },
                            usePointStyle: true,
                        },
                    },
                    tooltip: {
                        backgroundColor: '#1d281f',
                        borderColor: 'rgba(255, 255, 255, 0.14)',
                        borderWidth: 1,
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${formatShortRupiah(context.parsed.y)}`,
                        },
                        padding: 12,
                        titleColor: '#ffffff',
                        bodyColor: '#eef5ea',
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: '#647067',
                            font: {
                                family: 'Inter, Segoe UI, sans-serif',
                                size: 12,
                                weight: 600,
                            },
                        },
                    },
                    y: {
                        beginAtZero: true,
                        border: {
                            display: false,
                        },
                        grid: {
                            color: 'rgba(105, 119, 110, 0.18)',
                        },
                        suggestedMax: this.suggestedMax(),
                        ticks: {
                            callback: (value) => formatShortRupiah(Number(value)),
                            color: '#647067',
                            font: {
                                family: 'Inter, Segoe UI, sans-serif',
                                size: 12,
                            },
                            maxTicksLimit: 5,
                        },
                    },
                },
            },
        });
    },

    buildChartData() {
        const dataset = this.currentDataset;

        return {
            labels: dataset.labels,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: dataset.revenue,
                    backgroundColor: '#52772c',
                    borderRadius: 8,
                    maxBarThickness: 42,
                },
                {
                    label: 'Target',
                    data: dataset.target,
                    backgroundColor: '#234c8c',
                    borderRadius: 8,
                    maxBarThickness: 42,
                },
            ],
        };
    },

    suggestedMax() {
        return Math.max(...this.currentDataset.revenue) * 1.2;
    },
}));

Alpine.start();
