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

export const registerPurchaseOrderComponents = (Alpine) => {
    Alpine.data('purchaseOrderIndexPage', () => ({
        purchaseOrders: [],
        loading: true,
        error: '',
        actingOn: null,
        statusLabels: {},
        filters: { search: '', status: '', supplier_id: '' },
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },

        init() {
            this.loadPurchaseOrders(1);
        },

        async loadPurchaseOrders(page = 1) {
            this.loading = true;
            this.error = '';

            const parameters = new URLSearchParams({ page: String(page), per_page: String(this.meta.per_page) });

            Object.entries(this.filters).forEach(([key, value]) => {
                if (String(value).trim() !== '') {
                    parameters.set(key, value);
                }
            });

            try {
                const response = await fetch(`/api/purchase-orders?${parameters.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.purchaseOrders = payload.data ?? [];
                this.meta = { ...this.meta, ...(payload.meta ?? {}) };
                this.statusLabels = payload.reference?.statuses ?? {};
            } catch (error) {
                this.error = error?.message ?? 'Daftar PO belum berhasil dimuat.';
                this.purchaseOrders = [];
            } finally {
                this.loading = false;
            }
        },

        applyFilters() {
            this.loadPurchaseOrders(1);
        },

        resetFilters() {
            this.filters = { search: '', status: '', supplier_id: '' };
            this.loadPurchaseOrders(1);
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) {
                return;
            }

            this.loadPurchaseOrders(page);
        },

        async submitForApproval(po) {
            if (this.actingOn || !window.confirm(`Ajukan ${po.po_number} untuk approval?`)) {
                return;
            }

            await this.performAction(po, `/api/purchase-orders/${po.id}/submit`, {});
        },

        async approve(po) {
            if (this.actingOn || !window.confirm(`Setujui ${po.po_number} senilai ${this.formatRupiah(po.grand_total)}?`)) {
                return;
            }

            await this.performAction(po, `/api/purchase-orders/${po.id}/approve`, {});
        },

        async cancel(po) {
            if (this.actingOn) {
                return;
            }

            if (!window.confirm(`Batalkan ${po.po_number}? Tindakan ini tidak bisa dibatalkan lagi.`)) {
                return;
            }

            const reason = window.prompt('Alasan pembatalan (opsional):', '');

            if (reason === null) {
                return;
            }

            await this.performAction(po, `/api/purchase-orders/${po.id}/cancel`, {
                reason: reason.trim() === '' ? null : reason.trim(),
            });
        },

        async performAction(po, endpoint, body) {
            this.actingOn = po.id;
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

                await this.loadPurchaseOrders(this.meta.current_page);
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
                waiting_approval: 'bg-yellow-100 text-yellow-900',
                approved: 'bg-green-100 text-green-800',
                partially_received: 'bg-blue-100 text-blue-800',
                fully_received: 'bg-blue-100 text-blue-800',
                invoiced: 'bg-accent-soft text-accent',
                partially_paid: 'bg-accent-soft text-accent',
                paid: 'bg-green-100 text-green-800',
                closed: 'bg-canvas text-muted',
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

    Alpine.data('purchaseOrderFormPage', (config) => ({
        config,
        loadingOptions: true,
        saving: false,
        generalError: '',
        errors: {},
        suppliers: [],
        products: [],
        form: {
            supplier_id: '',
            order_date: config.today,
            expected_date: '',
            shipping_cost: 0,
            other_cost: 0,
            notes: '',
        },
        items: [{ product_id: '', productSearch: '', productPickerOpen: false, pickerStyle: '', quantity: '', unit_price: '', supplier_price_list_id: null, priceReference: null, priceReferenceLoading: false }],

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

        addItem() {
            this.items.push({ product_id: '', productSearch: '', productPickerOpen: false, pickerStyle: '', quantity: '', unit_price: '', supplier_price_list_id: null, priceReference: null, priceReferenceLoading: false });
        },

        removeItem(index) {
            if (this.items.length === 1) {
                return;
            }

            this.items.splice(index, 1);
        },

        async productChanged(index) {
            const item = this.items[index];
            const product = this.products.find((candidate) => String(candidate.id) === String(item.product_id));
            item.supplier_price_list_id = null;
            item.priceReference = null;

            const supplierPrice = await this.fetchSupplierPriceReference(index);

            if (supplierPrice) {
                item.supplier_price_list_id = supplierPrice.status !== 'expired' ? supplierPrice.id : null;

                if (item.unit_price === '' || item.unit_price === null) {
                    item.unit_price = supplierPrice.price;
                }

                return;
            }

            if (product && (item.unit_price === '' || item.unit_price === null)) {
                // No supplier price quote at all - fall back to real purchase
                // history first; the legacy flat field is only a last-resort
                // fallback for products that have never been purchased yet.
                item.unit_price = product.last_purchase_price || product.average_purchase_cost || product.purchase_price || '';
            }
        },

        filteredProducts(item) {
            const keyword = String(item.productSearch ?? '').trim().toLowerCase();

            if (!keyword) {
                return this.products.slice(0, 30);
            }

            return this.products.filter((product) => `${product.sku} ${product.name} ${product.category ?? ''}`.toLowerCase().includes(keyword)).slice(0, 30);
        },

        /**
         * Open the product picker for a row and position its (teleported)
         * dropdown against the triggering input's current screen position -
         * see the x-teleport note in purchase-orders/create.blade.php.
         */
        openProductPicker(index, event) {
            const item = this.items[index];
            item.productPickerOpen = true;

            const rect = event?.target?.getBoundingClientRect?.();

            if (rect) {
                item.pickerStyle = `top:${rect.bottom + 4}px; left:${rect.left}px; width:${rect.width}px;`;
            }
        },

        selectProduct(index, product) {
            const item = this.items[index];
            item.product_id = String(product.id);
            item.productSearch = `${product.sku} - ${product.name}`;
            item.productPickerOpen = false;
            this.clearError(`items.${index}.product_id`);
            this.productChanged(index);
        },

        /**
         * Re-check every line's supplier price suggestion when the supplier
         * changes, since a quote from a different supplier must never leak
         * into this PO (requirement 17).
         */
        async supplierChanged() {
            await Promise.all(this.items.map((item, index) => {
                item.supplier_price_list_id = null;
                item.priceReference = null;

                return item.product_id ? this.fetchSupplierPriceReference(index) : Promise.resolve(null);
            }));
        },

        async fetchSupplierPriceReference(index) {
            const item = this.items[index];

            if (!this.form.supplier_id || !item.product_id) {
                return null;
            }

            item.priceReferenceLoading = true;

            try {
                const parameters = new URLSearchParams({
                    supplier_id: String(this.form.supplier_id),
                    product_id: String(item.product_id),
                });
                const response = await fetch(`/api/supplier-prices/active?${parameters.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok || !payload.data) {
                    item.priceReference = null;

                    return null;
                }

                item.priceReference = payload.data;

                return payload.data;
            } catch (error) {
                item.priceReference = null;

                return null;
            } finally {
                item.priceReferenceLoading = false;
            }
        },

        itemSubtotal(item) {
            return (Number(item.quantity) || 0) * (Number(item.unit_price) || 0);
        },

        get subtotal() {
            return this.items.reduce((total, item) => total + this.itemSubtotal(item), 0);
        },

        get grandTotal() {
            return this.subtotal + (Number(this.form.shipping_cost) || 0) + (Number(this.form.other_cost) || 0);
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
            if (!this.form.order_date) errors.order_date = 'Tanggal PO wajib diisi.';

            this.items.forEach((item, index) => {
                if (!item.product_id) errors[`items.${index}.product_id`] = 'Barang wajib dipilih.';
                if (!(Number(item.quantity) > 0)) errors[`items.${index}.quantity`] = 'Jumlah harus lebih dari 0.';
                if (Number(item.unit_price) < 0 || item.unit_price === '') errors[`items.${index}.unit_price`] = 'Harga wajib diisi.';
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
                const response = await fetch('/api/purchase-orders', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: jsonHeaders(),
                    body: JSON.stringify({
                        ...this.form,
                        items: this.items.map((item) => ({
                            product_id: item.product_id,
                            quantity: item.quantity,
                            unit_price: item.unit_price,
                            supplier_price_list_id: item.supplier_price_list_id || null,
                        })),
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

                window.location.assign(this.config.indexUrl);
            } catch (error) {
                this.generalError = error?.message ?? 'PO belum berhasil disimpan.';
            } finally {
                this.saving = false;
            }
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

    Alpine.data('purchaseOrderShowPage', (config) => ({
        config,
        po: null,
        loading: true,
        error: '',
        acting: false,
        receipts: [],
        receiptsLoading: true,

        async init() {
            await Promise.all([this.loadPurchaseOrder(), this.loadReceipts()]);
        },

        async loadPurchaseOrder() {
            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(`/api/purchase-orders/${this.config.purchaseOrderId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.po = payload.data;
            } catch (error) {
                this.error = error?.message ?? 'Detail PO belum berhasil dimuat.';
            } finally {
                this.loading = false;
            }
        },

        async loadReceipts() {
            this.receiptsLoading = true;

            try {
                const response = await fetch(`/api/goods-receipts?purchase_order_id=${this.config.purchaseOrderId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                this.receipts = response.ok ? (payload.data ?? []) : [];
            } catch (error) {
                this.receipts = [];
            } finally {
                this.receiptsLoading = false;
            }
        },

        async submitForApproval() {
            if (this.acting || !window.confirm(`Ajukan ${this.po.po_number} untuk approval?`)) {
                return;
            }

            await this.performAction(`/api/purchase-orders/${this.po.id}/submit`, {});
        },

        async approve() {
            if (this.acting || !window.confirm(`Setujui ${this.po.po_number} senilai ${this.formatRupiah(this.po.grand_total)}?`)) {
                return;
            }

            await this.performAction(`/api/purchase-orders/${this.po.id}/approve`, {});
        },

        async cancel() {
            if (this.acting) {
                return;
            }

            if (!window.confirm(`Batalkan ${this.po.po_number}? Tindakan ini tidak bisa dibatalkan lagi.`)) {
                return;
            }

            const reason = window.prompt('Alasan pembatalan (opsional):', '');

            if (reason === null) {
                return;
            }

            await this.performAction(`/api/purchase-orders/${this.po.id}/cancel`, {
                reason: reason.trim() === '' ? null : reason.trim(),
            });
        },

        async performAction(endpoint, body) {
            this.acting = true;
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

                await this.loadPurchaseOrder();
            } catch (error) {
                const validationMessage = Object.values(error?.errors ?? {}).flat()[0];
                this.error = validationMessage ?? error?.message ?? 'Aksi belum berhasil diproses.';
            } finally {
                this.acting = false;
            }
        },

        statusClass(status) {
            return {
                draft: 'bg-canvas text-muted',
                waiting_approval: 'bg-yellow-100 text-yellow-900',
                approved: 'bg-green-100 text-green-800',
                partially_received: 'bg-blue-100 text-blue-800',
                fully_received: 'bg-blue-100 text-blue-800',
                invoiced: 'bg-accent-soft text-accent',
                partially_paid: 'bg-accent-soft text-accent',
                paid: 'bg-green-100 text-green-800',
                closed: 'bg-canvas text-muted',
                cancelled: 'bg-red-100 text-red-800',
            }[status] ?? 'bg-canvas text-muted';
        },

        receiptStatusClass(status) {
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
};
