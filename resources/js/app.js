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
import { createCustomer, deleteCustomer, listCustomers, updateCustomer } from './services/customer-api';
import { sendInvoiceWhatsApp } from './services/invoice-delivery-api';
import { persistInvoiceDraft, saveInvoiceDraft } from './services/invoice-api';
import { downloadInvoicePdf, downloadInvoicePreviewPdf } from './services/invoice-pdf-api';
import {
    bulkUpdateProductStock,
    createProduct,
    deleteProduct,
    getProduct,
    listProductCatalog,
    listProducts,
    updateProduct as updateCatalogProduct,
} from './services/product-api';
import {
    isProductLowStock,
    minimumStockForForm,
    minimumStockForPayload,
    normalizeMinimumStock,
} from './support/minimum-stock';
import { registerExpenseComponents } from './expenses';
import { registerProfitLossComponents } from './profit-loss';
import { registerCashBankComponents } from './cash-bank';
import { registerPurchaseOrderComponents } from './purchase-orders';
import { registerGoodsReceiptComponents } from './goods-receipts';

window.Alpine = Alpine;

registerExpenseComponents(Alpine);
registerProfitLossComponents(Alpine);
registerCashBankComponents(Alpine);
registerPurchaseOrderComponents(Alpine);
registerGoodsReceiptComponents(Alpine);

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

const emptyDashboardRevenueDataset = () => ({
    label: '',
    headline: 'Rp0',
    caption: 'Belum ada data invoice pada periode ini.',
    labels: [],
    issued: [],
    paid: [],
});
const invoicePreviewStorageKey = 'yokprinting.invoice.previewDraft';
const invoiceDraftStorageKey = 'yokprinting.invoice.editorDraft';
const persistedInvoiceDraftStorageKey = 'yokprinting.invoice.persistedDraft';
const shouldRestoreInvoiceEditorDraft = () => {
    if (!window.sessionStorage.getItem(invoiceDraftStorageKey)) {
        return false;
    }

    if (window.location.hash === '#restore-draft') {
        return true;
    }

    try {
        return new URL(document.referrer).pathname.endsWith('/invoices/preview');
    } catch {
        return false;
    }
};

const formatNumber = (value) => new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
}).format(Math.round(Number(value) || 0));

const clampNumber = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, Number(value) || 0));

const formatLongDate = (value) => {
    if (!value) {
        return '-';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
};

const buildInvoiceDraftPayload = (form) => {
    const explicitProductFields = [...form.querySelectorAll('[data-product-id-field]')];
    const productFields = explicitProductFields.length > 0
        ? explicitProductFields
        : [...form.querySelectorAll('select[name^="items"][name$="[product_id]"]')];

    return {
        customer_id: Number(form.querySelector('[name="customer_id"]')?.value) || null,
        customer_name: form.querySelector('[name="customer_name"]')?.value ?? '',
        customer_email: form.querySelector('[name="customer_email"]')?.value ?? '',
        customer_phone: form.querySelector('[name="customer_phone"]')?.value ?? '',
        customer_address: form.querySelector('[name="customer_address"]')?.value ?? '',
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
            jenis_cetak: form.querySelector(`[name="items[${index}][jenis_cetak]"]`)?.value ?? '',
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
        dp_required_percent: Number(form.querySelector('[name="dp_required_percent"]')?.value) || 50,
    };
};

const buildInvoicePreviewSnapshot = (payload) => {
    const subtotal = payload.items.reduce((total, item) => (
        total + (Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.price) || 0))
    ), 0);
    const discountType = payload.discount?.type ?? 'percentage';
    const discountValue = Number(payload.discount?.value) || 0;
    const discountAmount = discountType === 'fixed'
        ? Math.min(subtotal, Math.max(0, discountValue))
        : subtotal * clampNumber(discountValue, 0, 100) / 100;
    const taxableBase = Math.max(0, subtotal - discountAmount);
    const taxEnabled = Boolean(payload.tax?.enabled);
    const taxRate = clampNumber(payload.tax?.rate, 0, 100);
    const taxAmount = taxEnabled ? taxableBase * taxRate / 100 : 0;
    const shippingCost = Math.max(0, Number(payload.shipping_cost) || 0);
    const totalAmount = taxableBase + taxAmount + (payload.is_free_shipping ? 0 : shippingCost);
    const dpPercent = clampNumber(payload.dp_required_percent, 0, 100);

    return {
        invoice_number: payload.invoice_number || 'Belum disimpan',
        issue_date: payload.issue_date,
        issue_date_label: formatLongDate(payload.issue_date),
        currency: 'IDR',
        customer: {
            name: payload.customer_name || 'Pelanggan',
            email: payload.customer_email || '',
            phone: payload.customer_phone || '',
            address: payload.customer_address || '',
        },
        items: payload.items.map((item, index) => {
            const quantity = Math.max(0, Number(item.quantity) || 0);
            const price = Math.max(0, Number(item.price) || 0);
            const unit = 'Pcs';
            const orderIncrement = Number(item.order_increment) || null;
            const sku = item.sku || '';
            const note = [
                sku ? `SKU: ${sku}` : '',
                orderIncrement ? `Kelipatan jumlah ${formatNumber(orderIncrement)} ${unit}` : '',
            ].filter(Boolean).join(' · ');

            return {
                key: `${item.product_id || 'item'}-${index}`,
                name: item.description || item.product_name || `Item ${index + 1}`,
                note,
                quantity,
                unit,
                quantity_label: `${formatNumber(quantity)} ${unit}`,
                unit_price: price,
                line_total: quantity * price,
            };
        }),
        subtotal,
        discount_type: discountType,
        discount_value: discountValue,
        discount_amount: discountAmount,
        tax_enabled: taxEnabled,
        tax_rate: taxRate,
        tax_amount: taxAmount,
        shipping_cost: shippingCost,
        is_free_shipping: Boolean(payload.is_free_shipping),
        total_amount: totalAmount,
        dp_required_percent: dpPercent,
        dp_amount: totalAmount * dpPercent / 100,
        notes: payload.notes || 'Produksi berjalan setelah DP minimal diterima dan mockup/desain sudah di-ACC. Pelunasan dilakukan sebelum barang dikirim atau diambil.',
        terms: payload.terms || 'Minimal DP sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.',
    };
};

const validateInvoiceDraft = (payload) => {
    const errors = {};

    if (!payload.customer_id) {
        errors.customer_id = 'Pilih pelanggan untuk invoice ini.';
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

    init() {
        this.restoreSavedEditorDraft();
    },

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
            this.errorMessage = 'Perbaiki field bertanda merah sebelum menyimpan invoice.';
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
            window.sessionStorage.setItem(invoiceDraftStorageKey, JSON.stringify(payload));

            if (response.persisted) {
                window.sessionStorage.setItem(persistedInvoiceDraftStorageKey, JSON.stringify({
                    id: response.data.id,
                    invoice_number: response.data.invoice_number,
                    payload,
                }));
            }
        } catch (error) {
            this.errorTitle = 'Invoice gagal disimpan';
            this.errorMessage = error?.message ?? 'Invoice belum dapat disimpan. Coba lagi.';
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

    restoreSavedEditorDraft() {
        if (!shouldRestoreInvoiceEditorDraft()) {
            return;
        }

        const rawDraft = window.sessionStorage.getItem(invoiceDraftStorageKey);

        if (!rawDraft) {
            return;
        }

        let payload;

        try {
            payload = JSON.parse(rawDraft);
        } catch {
            window.sessionStorage.removeItem(invoiceDraftStorageKey);
            return;
        }

        this.$nextTick(() => {
            const form = this.$root;
            const fields = {
                invoice_number: payload.invoice_number,
                issue_date: payload.issue_date,
                due_date: payload.due_date,
                production_status: payload.production_status,
                dp_required_percent: payload.dp_required_percent,
                notes: payload.notes,
                terms: payload.terms,
                design_notes: payload.design_notes,
            };

            Object.entries(fields).forEach(([name, value]) => {
                if (value === undefined || value === null) {
                    return;
                }

                const field = form.querySelector(`[name="${name}"]`);

                if (field) {
                    field.value = value;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            window.dispatchEvent(new CustomEvent('invoice-draft-restore', {
                detail: payload,
            }));

        });
    },

    previewDraft(url, event = null) {
        const form = event?.currentTarget?.matches?.('form')
            ? event.currentTarget
            : event?.target?.closest?.('form');

        if (!form) {
            window.location.assign(url);
            return;
        }

        const payload = buildInvoiceDraftPayload(form);
        const snapshot = buildInvoicePreviewSnapshot(payload);
        const rawPersistedDraft = window.sessionStorage.getItem(persistedInvoiceDraftStorageKey);

        if (rawPersistedDraft) {
            try {
                const persistedDraft = JSON.parse(rawPersistedDraft);

                if (JSON.stringify(persistedDraft.payload) !== JSON.stringify(payload)) {
                    window.sessionStorage.removeItem(persistedInvoiceDraftStorageKey);
                }
            } catch {
                window.sessionStorage.removeItem(persistedInvoiceDraftStorageKey);
            }
        }

        window.sessionStorage.setItem(invoiceDraftStorageKey, JSON.stringify(payload));
        window.sessionStorage.setItem(invoicePreviewStorageKey, JSON.stringify(snapshot));
        window.location.assign(url);
    },
}));

Alpine.data('dashboardRevenueChart', () => ({
    chart: null,
    selectedPeriod: 'monthly',
    dataset: emptyDashboardRevenueDataset(),
    periods: [
        { key: 'monthly', label: 'Bulanan' },
        { key: 'quarterly', label: 'Kuartal' },
        { key: 'yearly', label: 'Tahunan' },
    ],

    get currentDataset() {
        return this.dataset;
    },

    async init() {
        await this.loadPeriod();
        this.$nextTick(() => this.renderChart());
    },

    async selectPeriod(period) {
        if (this.selectedPeriod === period) {
            return;
        }

        this.selectedPeriod = period;
        await this.loadPeriod();

        if (!this.chart) {
            this.renderChart();
            return;
        }

        this.chart.data = this.buildChartData();
        this.chart.options.scales.y.suggestedMax = this.suggestedMax();
        this.chart.update();
    },

    async loadPeriod() {
        const response = await fetch(`/api/dashboard/revenue-chart?period=${encodeURIComponent(this.selectedPeriod)}`, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));

        this.dataset = response.ok ? payload.data : emptyDashboardRevenueDataset();
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
        return Math.max(1, ...this.currentDataset.issued) * 1.2;
    },
}));

Alpine.data('dashboardRecentActivities', () => ({
    activeFilter: 'all',
    filters: [
        { key: 'all', label: 'Semua' },
        { key: 'invoice', label: 'Invoice' },
        { key: 'payment', label: 'Pembayaran' },
    ],
    activities: [],

    async init() {
        const response = await fetch('/api/dashboard/activities', {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({}));

        this.activities = response.ok
            ? payload.data.map((activity) => ({ ...activity, occurredAt: activity.occurred_at }))
            : [];
    },

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

Alpine.data('productionStatusForm', () => ({
    currentStatus: '',
    selectedStatus: '',
    endpoint: '',
    purpose: 'invoice',
    steps: [],
    saving: false,
    message: '',
    messageType: 'success',

    init() {
        this.currentStatus = this.$el.dataset.currentStatus;
        this.selectedStatus = this.currentStatus;
        this.endpoint = this.$el.dataset.endpoint;
        this.steps = JSON.parse(this.$el.dataset.productionSteps ?? '[]');
    },

    get currentIndex() {
        return this.steps.findIndex((step) => step.key === this.currentStatus);
    },

    get currentLabel() {
        return this.steps.find((step) => step.key === this.currentStatus)?.label ?? 'Status tidak dikenal';
    },

    stepIndex(status) {
        return this.steps.findIndex((step) => step.key === status);
    },

    stepCardClass(status) {
        const index = this.stepIndex(status);

        if (status === this.currentStatus) {
            return 'border-brand-300 bg-brand-50 text-brand-900';
        }

        return index < this.currentIndex
            ? 'border-green-200 bg-green-50 text-green-900'
            : 'border-line bg-canvas text-muted';
    },

    stepNumberClass(status) {
        const index = this.stepIndex(status);

        if (status === this.currentStatus) {
            return 'bg-brand-700 text-white';
        }

        return index < this.currentIndex
            ? 'bg-green-700 text-white'
            : 'bg-white text-muted';
    },

    stepNumber(status, number) {
        return this.stepIndex(status) < this.currentIndex ? '✓' : number;
    },

    clearNotice() {
        this.message = '';
    },

    async submit() {
        if (this.saving || this.selectedStatus === this.currentStatus) {
            return;
        }

        this.saving = true;
        this.message = '';

        try {
            const response = await fetch(this.endpoint, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ production_status: this.selectedStatus }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? payload.message ?? 'Status produksi gagal diperbarui.');
            }

            this.currentStatus = payload.data.production_status;
            this.selectedStatus = payload.data.production_status;
            this.message = payload.message;
            this.messageType = 'success';
            window.dispatchEvent(new CustomEvent('invoice-production-status-updated', {
                detail: payload.data.production_status,
            }));
        } catch (error) {
            this.message = error instanceof Error ? error.message : 'Status produksi gagal diperbarui.';
            this.messageType = 'error';
        } finally {
            this.saving = false;
        }
    },
}));

Alpine.data('cancelOrderAction', () => ({
    endpoint: '',
    cancelling: false,
    message: '',

    init() {
        this.endpoint = this.$el.dataset.endpoint;
    },

    async cancel() {
        if (this.cancelling || !this.endpoint) {
            return;
        }

        if (!window.confirm('Yakin batalkan order ini? Order yang sudah dibatalkan tidak bisa dikembalikan lagi.')) {
            return;
        }

        const reason = window.prompt('Alasan pembatalan (opsional):', '');

        if (reason === null) {
            return;
        }

        this.cancelling = true;
        this.message = '';

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ reason: reason.trim() === '' ? null : reason.trim() }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? payload.message ?? 'Order gagal dibatalkan.');
            }

            window.location.reload();
        } catch (error) {
            this.message = error instanceof Error ? error.message : 'Order gagal dibatalkan.';
            this.cancelling = false;
        }
    },
}));

Alpine.data('invoiceWhatsAppDelivery', () => ({
    endpoint: '',
    sending: false,
    sent: false,
    message: '',
    messageType: 'success',

    init() {
        this.endpoint = this.$el.dataset.endpoint;
        this.sent = this.$el.dataset.sent === 'true';
        this.purpose = this.$el.dataset.purpose ?? 'invoice';
    },

    async send() {
        if (this.sending || !this.endpoint) {
            return;
        }

        const popup = window.open('', '_blank');

        if (popup) {
            popup.opener = null;
        }

        this.sending = true;
        this.message = '';

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ purpose: this.purpose }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message ?? 'Invoice belum dapat dikirim via WhatsApp.');
            }

            this.sent = payload.data?.status === 'sent';
            this.message = 'WhatsApp dibuka dan invoice ditandai terkirim.';
            this.messageType = 'success';

            if (popup) {
                popup.location.assign(payload.data.whatsapp_url);
            } else {
                window.location.assign(payload.data.whatsapp_url);
            }
        } catch (error) {
            popup?.close();
            this.message = error instanceof Error ? error.message : 'Invoice belum dapat dikirim via WhatsApp.';
            this.messageType = 'error';
        } finally {
            this.sending = false;
        }
    },
}));

Alpine.data('recordPaymentForm', (config = {}) => ({
    endpoint: config.endpoint ?? '',
    remainingAmount: Number(config.remainingAmount ?? 0),
    saving: false,
    savedPayment: null,
    fieldErrors: {},
    form: {
        amount: '',
        paidAt: new Date().toISOString().slice(0, 10),
        method: 'transfer_bca',
        reference: '',
        notes: '',
    },

    get validationMessages() {
        return Object.values(this.fieldErrors);
    },

    get formattedAmount() {
        return formatRupiah(Number(this.form.amount) || 0);
    },

    get isPaid() {
        return this.remainingAmount <= 0;
    },

    useRemainingAmount() {
        if (this.isPaid) {
            return;
        }

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

        if (this.isPaid) {
            errors.amount = 'Invoice sudah lunas. Pembayaran tambahan tidak dapat dicatat.';
        } else if (amount <= 0) {
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

        return errors;
    },

    async submit() {
        if (this.saving) {
            return;
        }

        this.fieldErrors = this.validate();
        this.savedPayment = null;

        if (this.validationMessages.length > 0) {
            return;
        }

        this.saving = true;

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    amount: Number(this.form.amount),
                    payment_date: this.form.paidAt,
                    method: this.form.method,
                    reference: this.form.reference,
                    notes: this.form.notes,
                    status: 'verified',
                }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.fieldErrors = {
                    amount: payload.errors?.amount?.[0],
                    paidAt: payload.errors?.payment_date?.[0],
                    method: payload.errors?.method?.[0],
                    reference: payload.errors?.reference?.[0],
                };
                throw new Error(payload.message ?? 'Pembayaran belum dapat disimpan.');
            }

            this.savedPayment = {
                amount: this.formattedAmount,
                method: payload.data.method_label,
                reference: this.form.reference,
            };
            this.remainingAmount = Math.max(0, this.remainingAmount - Number(this.form.amount));
            this.form.amount = '';

            if (this.isPaid) {
                window.dispatchEvent(new CustomEvent('invoice-paid'));
            }
        } catch (error) {
            if (this.validationMessages.length === 0) {
                this.fieldErrors = {
                    form: error?.message ?? 'Pembayaran belum dapat disimpan.',
                };
            }
        } finally {
            this.saving = false;
        }
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

Alpine.data('dueInvoiceFollowUpTable', (dueInvoices = []) => ({
    query: '',
    statusFilter: 'all',
    sortKey: 'dueSort',
    sortDirection: 'asc',
    dueInvoices,
    markingInvoice: null,
    message: '',
    messageTone: 'success',

    get filteredInvoices() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        return this.dueInvoices
            .filter((invoice) => {
                const matchesStatus = this.statusFilter === 'all' || invoice.status === this.statusFilter;
                const matchesKeyword = !keyword ||
                    `${invoice.invoice} ${invoice.customer}`
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
            'Due soon': 'bg-yellow-100 text-yellow-900',
            Scheduled: 'bg-accent-soft text-accent',
        }[status] ?? 'bg-canvas text-muted';
    },

    async markFollowUp(invoice) {
        if (this.markingInvoice) {
            return;
        }

        const note = window.prompt(`Catatan follow-up untuk ${invoice.invoice} (opsional):`, '');

        if (note === null) {
            return;
        }

        this.markingInvoice = invoice.invoice;
        this.message = '';

        try {
            const response = await fetch(`/api/invoices/${encodeURIComponent(invoice.invoice)}/follow-up`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ note: note.trim() === '' ? null : note.trim() }),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(validationMessage ?? payload.message ?? 'Follow-up gagal dicatat.');
            }

            const target = this.dueInvoices.find((row) => row.invoice === invoice.invoice);

            if (target) {
                const when = new Date(payload.data.last_follow_up_at).toLocaleString('id-ID', {
                    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                });

                target.lastFollowUpLabel = `${when} oleh ${payload.data.last_follow_up_by ?? 'Anda'}`;
            }

            this.messageTone = 'success';
            this.message = payload.message;
        } catch (error) {
            this.messageTone = 'error';
            this.message = error.message;
        } finally {
            this.markingInvoice = null;
        }
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
    searchTimer: null,
    requestSequence: 0,

    async init() {
        await this.loadCustomers();
        this.$watch('query', (query) => {
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => this.loadCustomers(query), 250);
        });
    },

    get filteredCustomers() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        if (!keyword) {
            return this.customers;
        }

        return this.customers.filter((customer) =>
            `${customer.code ?? ''} ${customer.name} ${customer.email} ${customer.phone}`
                .toLocaleLowerCase('id')
                .includes(keyword),
        );
    },

    async loadCustomers(search = this.query) {
        const requestSequence = ++this.requestSequence;

        this.loading = true;
        this.errorMessage = '';

        try {
            const response = await listCustomers({ search });

            if (requestSequence !== this.requestSequence) {
                return;
            }

            this.customers = response.data;
            this.restoreSelectedCustomer();
        } catch (error) {
            if (requestSequence !== this.requestSequence) {
                return;
            }

            this.errorMessage = error?.message ?? 'Data pelanggan belum dapat dimuat.';
        } finally {
            if (requestSequence === this.requestSequence) {
                this.loading = false;
            }
        }
    },

    restoreSelectedCustomer(payload = null) {
        if (!payload && !shouldRestoreInvoiceEditorDraft()) {
            return;
        }

        let draft = payload;

        if (!draft) {
            try {
                draft = JSON.parse(window.sessionStorage.getItem(invoiceDraftStorageKey) || 'null');
            } catch {
                window.sessionStorage.removeItem(invoiceDraftStorageKey);
                draft = null;
            }
        }

        const customerId = Number(draft?.customer_id) || null;

        if (!customerId) {
            return;
        }

        const selected = this.customers.find((customer) => Number(customer.id) === customerId);

        if (selected) {
            this.selected = selected;
        }
    },

    async show() {
        if (this.loading || this.errorMessage) {
            return;
        }

        await this.loadCustomers(this.query);
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
            this.restoreItemsFromSavedDraft();
            this.seedItems();
        } catch (error) {
            this.products = [];
            this.items = [];
            this.productError = error?.message ?? 'Data produk belum dapat dimuat.';
        } finally {
            this.loadingProducts = false;
        }
    },

    restoreItemsFromSavedDraft() {
        if (!shouldRestoreInvoiceEditorDraft()) {
            return;
        }

        const rawDraft = window.sessionStorage.getItem(invoiceDraftStorageKey);

        if (!rawDraft) {
            return;
        }

        try {
            this.restoreItems(JSON.parse(rawDraft)?.items ?? []);
        } catch {
            window.sessionStorage.removeItem(invoiceDraftStorageKey);
        }
    },

    seedItems() {
        if (this.items.length > 0 || this.products.length === 0) {
            return;
        }

        const defaults = this.products.slice(0, 1);

        this.items = defaults.map((product) => this.createItem(product));
    },

    restoreItems(savedItems = []) {
        if (this.products.length === 0 || savedItems.length === 0) {
            return;
        }

        this.items = savedItems.map((savedItem) => {
            const product = this.products.find((item) => (
                Number(item.id) === Number(savedItem.product_id) ||
                item.sku === savedItem.sku ||
                item.code === savedItem.sku
            ));
            const restored = this.createItem(product);

            restored.productId = product?.id ?? Number(savedItem.product_id) ?? null;
            restored.productName = savedItem.product_name || product?.name || '';
            restored.sku = savedItem.sku || product?.sku || product?.code || '';
            restored.productSearch = product ? this.productLabel(product) : [restored.sku, restored.productName].filter(Boolean).join(' — ');
            restored.cupSize = savedItem.cup_size || product?.cup_size || restored.cupSize;
            restored.cupModel = savedItem.cup_model || product?.cup_model || restored.cupModel;
            restored.grammage = savedItem.grammage || product?.grammage || restored.grammage;
            restored.screenPrintingColor = savedItem.screen_printing_color || product?.screen_printing_color || restored.screenPrintingColor;
            restored.jenisCetak = savedItem.jenis_cetak || (product?.sides ? `${product.sides} warna` : restored.jenisCetak);
            restored.moqQuantity = Number(savedItem.moq_quantity || product?.minimum_order_qty || product?.moq_quantity) || 500;
            restored.orderIncrement = Number(savedItem.order_increment || product?.package_conversion || product?.order_increment) || 500;
            restored.packagingUnit = savedItem.packaging_unit || product?.unit || product?.packaging_unit || 'Pcs';
            restored.quantity = Number(savedItem.quantity) || restored.orderIncrement;
            restored.price = Number(savedItem.price) || 0;

            return restored;
        });
    },

    createItem(product = null) {
        return {
            key: this.nextKey++,
            productId: product?.id ?? null,
            productName: product?.name ?? '',
            sku: product?.sku ?? product?.code ?? '',
            productSearch: product ? this.productLabel(product) : '',
            pickerOpen: false,
            cupSize: '12 Oz',
            cupModel: product?.cup_model ?? 'Oval',
            grammage: product?.grammage ?? '8gr',
            screenPrintingColor: product?.screen_printing_color ?? 'Hitam',
            jenisCetak: product?.sides ? `${product.sides} warna` : '1 warna',
            moqQuantity: Number(product?.package_conversion || product?.order_increment || product?.minimum_order_qty || product?.moq_quantity) || 500,
            orderIncrement: Number(product?.package_conversion || product?.order_increment || product?.minimum_order_qty || product?.moq_quantity) || 500,
            packagingUnit: product?.unit ?? product?.packaging_unit ?? 'Pcs',
            quantity: Number(product?.package_conversion || product?.order_increment || product?.minimum_order_qty || product?.moq_quantity) || 500,
            price: product?.price ?? 0,
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
        ].filter(Boolean).join(' · ');
    },

    productSearchText(product) {
        return [
            product.sku,
            product.code,
            product.name,
            product.brand,
            product.category,
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
            const currentPrice = Number(item.price) || 0;

            item.productName = product.name;
            item.sku = product.sku ?? '';
            item.productSearch = this.productLabel(product);
            item.cupSize = '12 Oz';
            item.cupModel = product.cup_model ?? item.cupModel;
            item.grammage = product.grammage ?? item.grammage;
            item.screenPrintingColor = product.screen_printing_color ?? item.screenPrintingColor;
            item.jenisCetak = product.sides ? `${product.sides} warna` : item.jenisCetak;
            item.orderIncrement = Number(product.package_conversion || product.order_increment || product.minimum_order_qty || product.moq_quantity) || item.orderIncrement;
            item.moqQuantity = item.orderIncrement;
            item.packagingUnit = product.unit ?? product.packaging_unit ?? item.packagingUnit;
            item.quantity = Math.max(Number(item.quantity) || 0, item.orderIncrement || 1);
            item.price = currentPrice > 0 ? currentPrice : (product.price ?? 0);
            this.normalizeQuantity(item);
        }
    },

    normalizeQuantity(item) {
        const increment = Math.max(1, Number(item.orderIncrement) || Number(item.moqQuantity) || 500);
        const minimum = increment;
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
    preview: {
        invoice_number: 'Belum disimpan',
        issue_date_label: '-',
        currency: 'IDR',
        customer: {
            name: 'Pelanggan belum dipilih',
            email: '',
            phone: '',
            address: '',
        },
        items: [],
        subtotal: 0,
        discount_type: 'percentage',
        discount_value: 0,
        discount_amount: 0,
        tax_enabled: false,
        tax_rate: 11,
        tax_amount: 0,
        shipping_cost: 0,
        is_free_shipping: false,
        total_amount: 0,
        dp_required_percent: 0,
        dp_amount: 0,
        notes: '',
        terms: '',
    },
    savingDraft: false,
    draftSaved: false,
    sendingWhatsApp: false,
    downloadingPdf: false,
    pdfDownloaded: false,
    invoiceStatus: 'Draft',
    persistedInvoiceId: null,
    notice: null,

    init() {
        const rawPreview = window.sessionStorage.getItem(invoicePreviewStorageKey);

        if (rawPreview) {
            try {
                this.preview = {
                    ...this.preview,
                    ...JSON.parse(rawPreview),
                };
            } catch {
                window.sessionStorage.removeItem(invoicePreviewStorageKey);
            }
        }

        const rawDraft = window.sessionStorage.getItem(invoiceDraftStorageKey);
        const rawPersistedDraft = window.sessionStorage.getItem(persistedInvoiceDraftStorageKey);

        if (rawDraft && rawPersistedDraft) {
            try {
                const draft = JSON.parse(rawDraft);
                const persistedDraft = JSON.parse(rawPersistedDraft);

                const persistedId = Number(persistedDraft.id);

                if (
                    Number.isInteger(persistedId)
                    && persistedId > 0
                    && JSON.stringify(persistedDraft.payload) === JSON.stringify(draft)
                ) {
                    this.persistedInvoiceId = persistedId;
                    this.preview.invoice_number = persistedDraft.invoice_number;
                    this.draftSaved = true;
                } else {
                    window.sessionStorage.removeItem(persistedInvoiceDraftStorageKey);
                }
            } catch {
                window.sessionStorage.removeItem(persistedInvoiceDraftStorageKey);
            }
        }
    },

    get invoiceId() {
        return this.persistedInvoiceId;
    },

    get invoiceNumber() {
        return this.preview.invoice_number;
    },

    get canSendWhatsApp() {
        return this.persistedInvoiceId !== null;
    },

    get customerContactLine() {
        return [
            this.preview.customer?.email,
            this.preview.customer?.phone,
        ].filter(Boolean).join(' · ');
    },

    get discountLabel() {
        return this.preview.discount_type === 'percentage'
            ? `Diskon (${formatNumber(this.preview.discount_value)}%)`
            : 'Diskon';
    },

    get taxLabel() {
        return this.preview.tax_enabled
            ? `PPN (${formatNumber(this.preview.tax_rate)}%)`
            : 'PPN';
    },

    formatCurrency(value) {
        return formatRupiah(Math.round(Number(value) || 0));
    },

    async saveDraft() {
        if (this.savingDraft) {
            return;
        }

        if (this.canSendWhatsApp) {
            this.notice = {
                type: 'success',
                title: 'Draft sudah tersimpan',
                description: `Invoice ${this.invoiceNumber} siap dikirim dari data database.`,
            };
            return;
        }

        this.savingDraft = true;
        this.draftSaved = false;

        try {
            const rawDraft = window.sessionStorage.getItem(invoiceDraftStorageKey);

            if (!rawDraft) {
                throw new Error('Data editor tidak ditemukan. Kembali ke editor lalu buka pratinjau lagi.');
            }

            const payload = JSON.parse(rawDraft);
            const response = await persistInvoiceDraft(payload);

            this.persistedInvoiceId = response.data.id;
            this.preview.invoice_number = response.data.invoice_number;
            this.invoiceStatus = response.data.sent_at ? 'Terkirim' : 'Tersimpan';
            window.sessionStorage.setItem(persistedInvoiceDraftStorageKey, JSON.stringify({
                id: response.data.id,
                invoice_number: response.data.invoice_number,
                payload,
            }));
            this.draftSaved = true;
            this.notice = {
                type: 'success',
                title: 'Invoice berhasil disimpan',
                description: `Invoice ${response.data.invoice_number} tersimpan dan siap dikirim.`,
            };
        } catch (error) {
            window.sessionStorage.removeItem(persistedInvoiceDraftStorageKey);
            this.notice = {
                type: 'error',
                title: 'Invoice gagal disimpan',
                description: error?.message ?? 'Invoice belum dapat disimpan. Coba lagi.',
            };
        } finally {
            this.savingDraft = false;
        }
    },

    async sendWhatsApp() {
        if (this.sendingWhatsApp) {
            return;
        }

        if (!this.canSendWhatsApp) {
            this.notice = {
                type: 'error',
                title: 'Simpan invoice terlebih dahulu',
                description: 'WhatsApp hanya dapat dikirim dari invoice yang sudah tersimpan di database.',
            };
            return;
        }

        if (this.invoiceStatus === 'Terkirim') {
            this.notice = {
                type: 'success',
                title: 'Invoice sudah berstatus terkirim',
                description: 'Invoice tersimpan ini sudah dikirim via WhatsApp.',
            };
            return;
        }

        const popup = window.open('', '_blank');

        if (popup) {
            popup.opener = null;
        }

        this.sendingWhatsApp = true;
        this.notice = null;

        try {
            const response = await sendInvoiceWhatsApp({ invoiceId: this.invoiceId });

            this.invoiceStatus = response.data.status === 'sent' ? 'Terkirim' : 'Draft';
            this.notice = {
                type: 'success',
                title: 'Invoice berhasil dikirim',
                description: 'WhatsApp dibuka dan invoice ditandai terkirim.',
            };
            if (popup) {
                popup.location.assign(response.data.whatsapp_url);
            } else {
                window.location.assign(response.data.whatsapp_url);
            }
        } catch (error) {
            popup?.close();
            this.notice = {
                type: 'error',
                title: 'Invoice gagal dikirim',
                description: error?.message ?? 'Periksa nomor WhatsApp pelanggan lalu coba lagi.',
            };
        } finally {
            this.sendingWhatsApp = false;
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
            const response = this.canSendWhatsApp
                ? await downloadInvoicePdf(this.invoiceId)
                : await downloadInvoicePreviewPdf(this.preview);
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

Alpine.data('salesReportTable', (periodPresets = {}) => ({
    query: '',
    statusFilter: 'all',
    categoryFilter: 'all',
    periodPreset: 'monthly',
    startDate: periodPresets.monthly?.date_from ?? '',
    endDate: periodPresets.monthly?.date_to ?? '',
    periodPresets,
    showCustomDate: false,
    loading: false,
    loadError: '',
    exporting: false,
    exportSuccess: false,
    summaryCards: [],

    statusOptions: [
        { key: 'all', label: 'Semua Status' },
        { key: 'Lunas', label: 'Lunas' },
        { key: 'Menunggu', label: 'Menunggu' },
        { key: 'Parsial', label: 'Parsial' },
        { key: 'Overdue', label: 'Overdue' },
    ],

    periodOptions: [
        { key: 'weekly', label: 'Mingguan' },
        { key: 'monthly', label: 'Bulanan' },
        { key: 'yearly', label: 'Tahunan' },
        { key: 'custom', label: 'Rentang Kustom...' },
    ],

    sales: [],

    init() {
        this.loadReport();
    },

    get categoryOptions() {
        const categories = [...new Set(this.sales.flatMap((row) => row.categories ?? [row.category]))]
            .filter(Boolean)
            .sort((left, right) => left.localeCompare(right, 'id'));

        return [
            { key: 'all', label: 'Semua Kategori' },
            ...categories.map((category) => ({ key: category, label: category })),
        ];
    },

    get isFiltered() {
        return this.query.trim() !== '' || this.statusFilter !== 'all' || this.categoryFilter !== 'all' || this.periodPreset !== 'monthly';
    },

    async selectPeriod(preset) {
        this.periodPreset = preset;

        if (preset === 'custom') {
            this.showCustomDate = true;
            return;
        }

        this.showCustomDate = false;

        const period = this.periodPresets[preset];

        if (period) {
            this.startDate = period.date_from;
            this.endDate = period.date_to;
            await this.loadReport();
        }
    },

    reportParams() {
        return new URLSearchParams({
            date_from: this.startDate,
            date_to: this.endDate,
        });
    },

    async loadReport() {
        if (!this.startDate || !this.endDate) return;

        this.loading = true;
        this.loadError = '';

        try {
            const params = this.reportParams().toString();
            const [invoiceResponse, summaryResponse] = await Promise.all([
                fetch(`/api/reports/sales/invoices?${params}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                }),
                fetch(`/api/reports/sales/summary?${params}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                }),
            ]);

            if (!invoiceResponse.ok || !summaryResponse.ok) {
                throw new Error('Data laporan belum berhasil dimuat.');
            }

            const [invoicePayload, summaryPayload] = await Promise.all([
                invoiceResponse.json(),
                summaryResponse.json(),
            ]);

            this.sales = (invoicePayload.data ?? []).map((row) => ({
                customer: row.customer?.name ?? '-',
                product: row.product,
                category: row.category,
                categories: row.categories ?? [],
                invoice: row.invoice_number,
                date: row.issue_date_formatted,
                rawDate: row.issue_date,
                amount: row.total_amount_formatted,
                rawAmount: Number(row.total_amount),
                margin: row.margin_label,
                status: row.status_label,
            }));
            this.summaryCards = summaryPayload.data?.cards ?? [];
        } catch (error) {
            this.sales = [];
            this.summaryCards = [];
            this.loadError = error?.message ?? 'Data laporan belum berhasil dimuat.';
        } finally {
            this.loading = false;
        }
    },

    get filteredSales() {
        const keyword = this.query.trim().toLocaleLowerCase('id');

        return this.sales.filter((row) => {
            const matchesStatus = this.statusFilter === 'all' || row.status === this.statusFilter;
            const matchesCategory = this.categoryFilter === 'all' ||
                (row.categories ?? [row.category]).includes(this.categoryFilter);
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

    get productMix() {
        const totals = this.filteredSales.reduce((result, row) => {
            result[row.category] = (result[row.category] ?? 0) + (row.rawAmount || 0);
            return result;
        }, {});
        const grandTotal = Object.values(totals).reduce((sum, amount) => sum + amount, 0);
        const tones = ['bg-brand-600', 'bg-accent', 'bg-yellow-500', 'bg-green-600'];

        return Object.entries(totals)
            .sort(([, left], [, right]) => right - left)
            .map(([label, amount], index) => ({
                label,
                value: grandTotal > 0 ? `${Math.round((amount / grandTotal) * 100)}%` : '0%',
                class: tones[index % tones.length],
            }));
    },

    setStatusFilter(key) {
        this.statusFilter = key;
    },

    resetFilters() {
        this.query = '';
        this.statusFilter = 'all';
        this.categoryFilter = 'all';
        this.selectPeriod('monthly');
    },

    async exportExcel() {
        if (this.exporting) return;

        this.exporting = true;
        this.exportSuccess = false;

        const statusMap = {
            Lunas: 'paid',
            Parsial: 'partial',
            Menunggu: 'unpaid',
            Overdue: 'overdue',
        };
        const params = new URLSearchParams({
            ...Object.fromEntries(this.reportParams()),
            status: statusMap[this.statusFilter] ?? 'all',
            category: this.categoryFilter === 'all' ? '' : this.categoryFilter,
            q: this.query.trim(),
        });

        try {
            const response = await fetch(`/api/reports/sales/export?${params.toString()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'text/csv' },
            });

            if (!response.ok) {
                throw new Error('Export laporan belum berhasil.');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const disposition = response.headers.get('Content-Disposition') ?? '';
            const filename = disposition.match(/filename="([^"]+)"/)?.[1]
                ?? `laporan-penjualan-${this.periodPreset}-${this.endDate}.csv`;
            const link = document.createElement('a');

            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 1000);

            this.exportSuccess = true;
            window.setTimeout(() => {
                this.exportSuccess = false;
            }, 3000);
        } catch (error) {
            window.alert(error?.message ?? 'Export laporan belum berhasil.');
        } finally {
            this.exporting = false;
        }
    },

    statusClass(status) {
        return {
            Lunas: 'bg-green-100 text-green-800',
            Overdue: 'bg-red-100 text-red-800',
            Parsial: 'bg-brand-100 text-brand-800',
            Menunggu: 'bg-yellow-100 text-yellow-900',
        }[status] ?? 'bg-canvas text-muted';
    },

    summaryToneClass(tone) {
        return {
            success: 'bg-green-100 text-green-800',
            warning: 'bg-yellow-100 text-yellow-900',
        }[tone] ?? 'bg-brand-100 text-brand-800';
    },
}));

Alpine.data('customerIndexTable', (initialCustomers = []) => ({
    query: '',
    statusFilter: 'all',
    segmentFilter: 'all',
    sortKey: 'lastOrderSort',
    sortDirection: 'desc',
    customers: initialCustomers,
    deleteCandidate: null,
    deleteModalOpen: false,
    deleting: false,
    deleteError: '',

    statusOptions: [
        { key: 'all', label: 'Semua Status' },
        { key: 'Aktif', label: 'Aktif' },
        { key: 'Perlu follow-up', label: 'Follow-up' },
        { key: 'Prospek', label: 'Prospek' },
    ],

    get segmentOptions() {
        const segments = [...new Set(
            this.customers
                .map((customer) => customer.segment)
                .filter((segment) => segment && segment !== '-'),
        )].sort((first, second) => first.localeCompare(second, 'id'));

        return [
            { key: 'all', label: 'Semua Segmen' },
            ...segments.map((segment) => ({ key: segment, label: segment })),
        ];
    },

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

    openDeleteModal(customer) {
        this.deleteCandidate = customer;
        this.deleteError = '';
        this.deleteModalOpen = true;
        this.$nextTick(() => this.$refs.deleteConfirmButton?.focus());
    },

    closeDeleteModal() {
        if (this.deleting) {
            return;
        }

        this.deleteModalOpen = false;
        this.deleteCandidate = null;
        this.deleteError = '';
    },

    async confirmDelete() {
        if (!this.deleteCandidate || this.deleting) {
            return;
        }

        this.deleting = true;
        this.deleteError = '';

        try {
            const deletedCode = this.deleteCandidate.code;

            await deleteCustomer(this.deleteCandidate.id);
            this.customers = this.customers.filter((customer) => customer.id !== this.deleteCandidate.id);
            window.location.assign(`/customers?deleted=${encodeURIComponent(deletedCode)}`);
        } catch (error) {
            this.deleteError = error?.message ?? 'Pelanggan belum dapat dihapus.';
        } finally {
            this.deleting = false;
        }
    },

    statusClass(status) {
        return {
            Aktif: 'bg-green-100 text-green-800',
            'Perlu follow-up': 'bg-yellow-100 text-yellow-900',
            Prospek: 'bg-accent-soft text-accent',
        }[status] ?? 'bg-canvas text-muted';
    },
}));

Alpine.data('customerForm', (initialForm = {}, isEditMode = false, customerId = null) => ({
    isEdit: isEditMode,
    customerId,
    saving: false,
    saved: false,
    fieldErrors: {},
    form: {
        code: initialForm.code ?? '',
        name: initialForm.name ?? '',
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

        if (!this.form.name.trim()) {
            errors.name = 'Nama pelanggan wajib diisi.';
        }

        if (this.form.email.trim() && !emailPattern.test(this.form.email.trim())) {
            errors.email = 'Format email pelanggan belum valid.';
        }

        if (!this.form.phone.trim()) {
            errors.phone = 'Nomor telepon wajib diisi.';
        }

        if (!this.form.address.trim()) {
            errors.address = 'Alamat penagihan wajib diisi.';
        }

        return errors;
    },

    payload() {
        return {
            name: this.form.name.trim(),
            email: this.form.email.trim() || null,
            phone: this.form.phone.trim() || null,
            tax_number: this.form.taxNumber.trim() || null,
            address: this.form.address.trim(),
            city: this.form.city.trim() || null,
            province: this.form.province.trim() || null,
            postal_code: this.form.postalCode.trim() || null,
            status: {
                Aktif: 'active',
                Nonaktif: 'inactive',
                'Perlu follow-up': 'inactive_1m',
                Prospek: 'active',
            }[this.form.status] ?? 'active',
            notes: this.form.notes.trim() || null,
        };
    },

    applyApiErrors(errors = {}) {
        const fieldMap = {
            tax_number: 'taxNumber',
            postal_code: 'postalCode',
        };

        this.fieldErrors = Object.entries(errors).reduce((fields, [field, messages]) => ({
            ...fields,
            [fieldMap[field] ?? field]: Array.isArray(messages) ? messages[0] : messages,
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

        try {
            if (this.isEdit) {
                const response = await updateCustomer(this.customerId, this.payload());

                this.form.code = response.data.code;
                this.saved = true;
                return;
            }

            const response = await createCustomer(this.payload());

            this.form.code = response.data.code;
            this.saved = true;
            window.location.assign(response.redirect_url ?? '/customers');
        } catch (error) {
            if (error?.errors && Object.keys(error.errors).length > 0) {
                this.applyApiErrors(error.errors);
            } else {
                this.fieldErrors = {
                    form: error?.message ?? 'Pelanggan belum dapat disimpan.',
                };
            }
        } finally {
            this.saving = false;
        }
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
    bulkEditorOpen: false,
    bulkStockMode: 'stock',
    bulkStockValue: 500,
    bulkSaving: false,
    bulkStockError: '',
    bulkStockSuccess: '',

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
        const minimumStock = normalizeMinimumStock(product.minimum_stock);
        const trackStock = product.track_stock ?? true;
        const status = product.status === 'inactive'
            ? 'Nonaktif'
            : isProductLowStock({
                status: product.status,
                trackStock,
                stock: stockValue,
                minimumStock,
            })
                ? 'Stok menipis'
                : 'Aktif';

        return {
            id: product.id,
            sku: product.sku ?? product.code,
            name: product.name,
            category: product.category ?? 'Lainnya',
            brand: product.brand ?? '',
            unit: product.unit ?? 'Pcs',
            // Prefer the purchasing-module cost basis over the legacy flat
            // field, which nothing writes to anymore (see productForm).
            purchasePrice: formatRupiah(Number(product.average_purchase_cost ?? product.last_purchase_price ?? product.purchase_price) || 0),
            purchasePriceValue: Number(product.average_purchase_cost ?? product.last_purchase_price ?? product.purchase_price) || 0,
            stock: product.track_stock === false ? 'Tidak dilacak' : `${stockValue} ${product.unit ?? 'Pcs'}`,
            stockValue: product.track_stock === false ? 999999 : stockValue,
            currentStock: stockValue,
            minimumStock,
            sales: product.sales ?? 0,
            status,
            rawStatus: product.status,
            trackStock,
            updatedAt: product.updated_at,
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
        return isProductLowStock({
            status: product.rawStatus,
            trackStock: product.trackStock,
            stock: product.stockValue,
            minimumStock: product.minimumStock,
        });
    },

    applyBulkUpdates(updates = []) {
        const updatesById = new Map(updates.map((update) => [Number(update.id), update]));

        this.products = this.products.map((product) => {
            const update = updatesById.get(Number(product.id));

            if (!update) {
                return product;
            }

            const stockValue = update.stock === null ? 0 : Number(update.stock) || 0;
            const minimumStock = normalizeMinimumStock(update.minimum_stock);
            const status = product.rawStatus === 'inactive'
                ? 'Nonaktif'
                : isProductLowStock({
                    status: product.rawStatus,
                    trackStock: product.trackStock,
                    stock: stockValue,
                    minimumStock,
                })
                    ? 'Stok menipis'
                    : 'Aktif';

            return {
                ...product,
                stock: product.trackStock === false ? 'Tidak dilacak' : `${stockValue} ${product.unit}`,
                stockValue: product.trackStock === false ? 999999 : stockValue,
                currentStock: stockValue,
                minimumStock,
                status,
                updatedAt: update.updated_at,
            };
        });
    },

    resetFilters() {
        this.query = '';
        this.statusFilter = 'all';
        this.categoryFilter = 'all';
    },

    openBulkStockEditor() {
        this.bulkEditorOpen = true;
        this.bulkStockMode = 'stock';
        this.bulkStockValue = 500;
        this.bulkStockError = '';
        this.bulkStockSuccess = '';
    },

    closeBulkStockEditor() {
        if (this.bulkSaving) {
            return;
        }

        this.bulkEditorOpen = false;
        this.bulkStockError = '';
    },

    async applyBulkStock() {
        const value = Number(this.bulkStockValue);

        this.bulkStockError = '';
        this.bulkStockSuccess = '';

        if (!Number.isFinite(value) || value < 0) {
            this.bulkStockError = 'Nilai stok harus angka 0 atau lebih.';
            return;
        }

        const productsToUpdate = [...this.filteredProducts];

        if (productsToUpdate.length === 0) {
            this.bulkStockError = 'Tidak ada produk yang cocok dengan filter saat ini.';
            return;
        }

        this.bulkSaving = true;

        try {
            const response = await bulkUpdateProductStock(productsToUpdate.map((product) => ({
                id: product.id,
                field: this.bulkStockMode,
                value,
                expected_value: this.bulkStockMode === 'minimum_stock'
                    ? product.minimumStock
                    : product.currentStock,
                expected_updated_at: product.updatedAt,
            })));

            this.applyBulkUpdates(response.data);
            this.bulkStockSuccess = `${response.meta.updated_count} produk berhasil diperbarui.`;

            window.setTimeout(() => {
                this.bulkEditorOpen = false;
                this.bulkStockSuccess = '';
            }, 900);
        } catch (error) {
            const [errorField, messages] = Object.entries(error?.errors ?? {})[0] ?? [];
            const itemMessage = Array.isArray(messages) ? messages[0] : messages;

            this.bulkStockError = itemMessage
                ? `${errorField}: ${itemMessage}`
                : error?.message ?? 'Bulk edit stok belum berhasil disimpan.';
        } finally {
            this.bulkSaving = false;
        }
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
    // Read-only, system-managed cost reference - never part of the submitted
    // payload. Only PostGoodsReceipt (Goods Receipt posting) ever writes these.
    lastPurchasePrice: initialForm.lastPurchasePrice ?? initialForm.last_purchase_price ?? null,
    averagePurchaseCost: initialForm.averagePurchaseCost ?? initialForm.average_purchase_cost ?? null,
    form: {
        id: initialForm.id ?? null,
        sku: initialForm.sku ?? 'PRN-NEW-01',
        name: initialForm.name ?? '',
        category: initialForm.category ?? 'Cetak premium',
        brand: initialForm.brand ?? '',
        unit: initialForm.unit ?? 'Pcs',
        stock: initialForm.stock ?? 10,
        minimumStock: minimumStockForForm(initialForm),
        minimumOrderQty: initialForm.minimumOrderQty ?? initialForm.minimum_order_qty ?? 500,
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
            this.lastPurchasePrice = response.data.last_purchase_price ?? null;
            this.averagePurchaseCost = response.data.average_purchase_cost ?? null;
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
            stock: Number(product.stock) || 0,
            minimumStock: minimumStockForForm(product),
            minimumOrderQty: Number(product.minimum_order_qty) || 500,
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

    get formattedLastPurchasePrice() {
        return this.lastPurchasePrice === null ? 'Belum ada data' : formatRupiah(Number(this.lastPurchasePrice));
    },

    get formattedAveragePurchaseCost() {
        return this.averagePurchaseCost === null ? 'Belum ada data' : formatRupiah(Number(this.averagePurchaseCost));
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
            stock: this.form.trackStock ? Number(this.form.stock) || 0 : null,
            minimum_stock: minimumStockForPayload(this.form.minimumStock, this.form.trackStock),
            minimum_order_qty: Number(this.form.minimumOrderQty) || 500,
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
        return `${this.form.invoicePrefix || 'INV'}-${new Date().getFullYear()}-0001`;
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

Alpine.data('salesReportChart', () => ({
    chart: null,
    selectedPeriod: 'monthly',
    dataset: {
        labels: [],
        revenue: [],
        target: [],
    },
    loading: false,
    loadError: '',
    periods: [
        { key: 'weekly', label: 'Mingguan' },
        { key: 'monthly', label: 'Bulanan' },
        { key: 'yearly', label: 'Tahunan' },
    ],

    init() {
        this.loadChart();
    },

    async selectPeriod(period) {
        if (this.selectedPeriod === period || !this.periods.some((option) => option.key === period)) {
            return;
        }

        this.selectedPeriod = period;
        await this.loadChart();
    },

    async loadChart() {
        this.loading = true;
        this.loadError = '';

        try {
            const response = await fetch(`/api/reports/sales/revenue-chart?period=${encodeURIComponent(this.selectedPeriod)}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Grafik laporan belum berhasil dimuat.');
            }

            const payload = await response.json();
            this.dataset = {
                labels: payload.data?.labels ?? [],
                revenue: payload.data?.revenue ?? [],
                target: payload.data?.target ?? [],
            };

            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }

            this.$nextTick(() => this.renderChart());
        } catch (error) {
            this.loadError = error?.message ?? 'Grafik laporan belum berhasil dimuat.';
        } finally {
            this.loading = false;
        }
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
        return {
            labels: this.dataset.labels,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: this.dataset.revenue,
                    backgroundColor: '#52772c',
                    borderRadius: 8,
                    maxBarThickness: 42,
                },
                {
                    label: 'Target',
                    data: this.dataset.target,
                    backgroundColor: '#234c8c',
                    borderRadius: 8,
                    maxBarThickness: 42,
                },
            ],
        };
    },

    suggestedMax() {
        return Math.max(1, ...this.dataset.revenue) * 1.2;
    },
}));

Alpine.start();
