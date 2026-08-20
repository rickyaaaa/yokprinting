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

export const registerGoodsReceiptComponents = (Alpine) => {
    Alpine.data('goodsReceiptIndexPage', () => ({
        receipts: [],
        loading: true,
        error: '',
        actingOn: null,
        statusLabels: {},
        filters: { search: '', status: '' },
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },

        init() {
            this.loadReceipts(1);
        },

        async loadReceipts(page = 1) {
            this.loading = true;
            this.error = '';

            const parameters = new URLSearchParams({ page: String(page), per_page: String(this.meta.per_page) });

            Object.entries(this.filters).forEach(([key, value]) => {
                if (String(value).trim() !== '') {
                    parameters.set(key, value);
                }
            });

            try {
                const response = await fetch(`/api/goods-receipts?${parameters.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.receipts = payload.data ?? [];
                this.meta = { ...this.meta, ...(payload.meta ?? {}) };
                this.statusLabels = payload.reference?.statuses ?? {};
            } catch (error) {
                this.error = error?.message ?? 'Daftar penerimaan barang belum berhasil dimuat.';
                this.receipts = [];
            } finally {
                this.loading = false;
            }
        },

        applyFilters() {
            this.loadReceipts(1);
        },

        resetFilters() {
            this.filters = { search: '', status: '' };
            this.loadReceipts(1);
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) {
                return;
            }

            this.loadReceipts(page);
        },

        async post(receipt) {
            if (this.actingOn) {
                return;
            }

            if (!window.confirm(`Posting ${receipt.receipt_number}? Stok dan layer HPP FIFO akan diperbarui. Pembatalan setelah ini hanya bisa dilakukan selama belum ada transaksi lain menyentuh produk yang sama.`)) {
                return;
            }

            await this.performAction(receipt, `/api/goods-receipts/${receipt.id}/post`, {});
        },

        async cancel(receipt) {
            const confirmMessage = receipt.status === 'posted'
                ? `Batalkan ${receipt.receipt_number} yang sudah diposting? Stok dan layer HPP FIFO produk akan dikembalikan seperti sebelum penerimaan ini, kalau belum ada transaksi lain yang menyusul.`
                : `Batalkan draft ${receipt.receipt_number}?`;

            if (this.actingOn || !window.confirm(confirmMessage)) {
                return;
            }

            const reason = window.prompt('Alasan pembatalan (opsional):', '');

            if (reason === null) {
                return;
            }

            await this.performAction(receipt, `/api/goods-receipts/${receipt.id}/cancel`, {
                reason: reason.trim() === '' ? null : reason.trim(),
            });
        },

        async performAction(receipt, endpoint, body) {
            this.actingOn = receipt.id;
            this.error = '';

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: jsonHeaders(),
                    body: JSON.stringify(body),
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                await this.loadReceipts(this.meta.current_page);
            } catch (error) {
                const validationMessage = Object.values(error?.errors ?? {}).flat()[0];
                this.error = validationMessage ?? error?.message ?? 'Aksi belum berhasil diproses.';
            } finally {
                this.actingOn = null;
            }
        },

        statusClass(status) {
            return {
                draft: 'bg-canvas text-muted',
                posted: 'bg-green-100 text-green-800',
                cancelled: 'bg-red-100 text-red-800',
            }[status] ?? 'bg-canvas text-muted';
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

    Alpine.data('goodsReceiptFormPage', (config) => ({
        config,
        loading: true,
        saving: false,
        generalError: '',
        errors: {},
        purchaseOrder: null,
        form: { receipt_date: config.today, notes: '' },
        items: [],

        async init() {
            const params = new URLSearchParams(window.location.search);
            const purchaseOrderId = params.get('purchase_order_id');

            if (!purchaseOrderId) {
                this.generalError = 'Pilih PO dari halaman daftar Purchase Order terlebih dahulu, lalu klik "Terima Barang".';
                this.loading = false;

                return;
            }

            try {
                const response = await fetch(`/api/purchase-orders/${purchaseOrderId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.purchaseOrder = payload.data;

                if (!['approved', 'partially_received'].includes(this.purchaseOrder.status)) {
                    this.generalError = 'PO ini belum disetujui atau sudah selesai diterima, tidak bisa dibuatkan penerimaan barang baru.';
                }

                this.items = this.purchaseOrder.items
                    .map((item) => ({
                        purchase_order_item_id: item.id,
                        product_name: item.product_name,
                        sku: item.sku,
                        ordered: item.quantity,
                        already_received: item.received_quantity,
                        remaining: Math.max(0, item.quantity - item.received_quantity),
                        unit_price: item.unit_price,
                        quantity_received: '',
                    }))
                    .filter((item) => item.remaining > 0);
            } catch (error) {
                this.generalError = error?.message ?? 'PO belum berhasil dimuat.';
            } finally {
                this.loading = false;
            }
        },

        fillMax(index) {
            this.items[index].quantity_received = this.items[index].remaining;
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
            const hasAny = this.items.some((item) => Number(item.quantity_received) > 0);

            if (!hasAny) {
                errors.items = 'Isi minimal satu jumlah barang yang diterima.';
            }

            this.items.forEach((item, index) => {
                const value = Number(item.quantity_received) || 0;

                if (value < 0) errors[`items.${index}`] = 'Jumlah tidak boleh negatif.';
                if (value > Number(item.remaining)) errors[`items.${index}`] = `Melebihi sisa PO (${item.remaining}).`;
            });

            return errors;
        },

        async submit() {
            if (this.saving) {
                return;
            }

            this.errors = this.validate();
            this.generalError = '';

            if (Object.keys(this.errors).length > 0) {
                return;
            }

            this.saving = true;

            try {
                const response = await fetch(`/api/purchase-orders/${this.purchaseOrder.id}/goods-receipts`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: jsonHeaders(),
                    body: JSON.stringify({
                        receipt_date: this.form.receipt_date,
                        notes: this.form.notes,
                        items: this.items
                            .filter((item) => Number(item.quantity_received) > 0)
                            .map((item) => ({
                                purchase_order_item_id: item.purchase_order_item_id,
                                quantity_received: item.quantity_received,
                            })),
                    }),
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    if (response.status === 422) {
                        this.generalError = Object.values(payload.errors ?? {}).flat()[0] ?? payload.message;
                    }

                    throw payload;
                }

                window.location.assign(this.config.indexUrl);
            } catch (error) {
                this.generalError = this.generalError || error?.message || 'Penerimaan barang belum berhasil disimpan.';
            } finally {
                this.saving = false;
            }
        },

        formatRupiah(value) {
            return rupiahFormatter.format(Number(value ?? 0));
        },
    }));
};
