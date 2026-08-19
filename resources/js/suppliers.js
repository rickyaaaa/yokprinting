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

export const registerSupplierComponents = (Alpine) => {
    Alpine.data('supplierIndexPage', () => ({
        suppliers: [],
        loading: true,
        error: '',
        deletingId: null,
        search: '',

        init() {
            this.loadSuppliers();
        },

        async loadSuppliers() {
            this.loading = true;
            this.error = '';

            const parameters = new URLSearchParams({ limit: '200' });

            if (this.search.trim() !== '') {
                parameters.set('search', this.search.trim());
            }

            try {
                const response = await fetch(`/api/suppliers?${parameters.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.suppliers = payload.data ?? [];
            } catch (error) {
                this.error = error?.message ?? 'Daftar supplier belum berhasil dimuat.';
                this.suppliers = [];
            } finally {
                this.loading = false;
            }
        },

        applySearch() {
            this.loadSuppliers();
        },

        async remove(supplier) {
            if (this.deletingId || !window.confirm(`Hapus supplier ${supplier.name}?`)) {
                return;
            }

            this.deletingId = supplier.id;
            this.error = '';

            try {
                const response = await fetch(`/api/suppliers/${supplier.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: jsonHeaders(),
                });

                if (!response.ok) {
                    throw await parseJsonResponse(response);
                }

                await this.loadSuppliers();
            } catch (error) {
                this.error = error?.message ?? 'Supplier belum berhasil dihapus.';
            } finally {
                this.deletingId = null;
            }
        },
    }));

    Alpine.data('supplierFormPage', (config = {}) => ({
        isEdit: Boolean(config.isEdit),
        supplierId: config.supplierId ?? null,
        loading: false,
        saving: false,
        generalError: '',
        errors: {},
        form: {
            code: '',
            name: '',
            contact_person: '',
            phone: '',
            email: '',
            address: '',
        },

        async init() {
            if (!this.isEdit || !this.supplierId) {
                return;
            }

            this.loading = true;

            try {
                const response = await fetch(`/api/suppliers/${this.supplierId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.form = {
                    code: payload.data.code ?? '',
                    name: payload.data.name ?? '',
                    contact_person: payload.data.contact_person ?? '',
                    phone: payload.data.phone ?? '',
                    email: payload.data.email ?? '',
                    address: payload.data.address ?? '',
                };
            } catch (error) {
                this.generalError = 'Detail supplier belum berhasil dimuat.';
            } finally {
                this.loading = false;
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

            if (!this.form.code.trim()) errors.code = 'Kode supplier wajib diisi.';
            if (!this.form.name.trim()) errors.name = 'Nama supplier wajib diisi.';

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

            const payload = {
                code: this.form.code.trim(),
                name: this.form.name.trim(),
                contact_person: this.form.contact_person.trim() || null,
                phone: this.form.phone.trim() || null,
                email: this.form.email.trim() || null,
                address: this.form.address.trim() || null,
            };

            try {
                const response = await fetch(
                    this.isEdit ? `/api/suppliers/${this.supplierId}` : '/api/suppliers',
                    {
                        method: this.isEdit ? 'PUT' : 'POST',
                        credentials: 'same-origin',
                        headers: jsonHeaders(),
                        body: JSON.stringify(payload),
                    },
                );
                const responseBody = await parseJsonResponse(response);

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors = Object.fromEntries(
                            Object.entries(responseBody.errors ?? {}).map(([field, messages]) => [
                                field,
                                Array.isArray(messages) ? messages[0] : String(messages),
                            ]),
                        );
                    }

                    throw responseBody;
                }

                window.location.assign('/suppliers');
            } catch (error) {
                this.generalError = error?.message ?? 'Supplier belum berhasil disimpan.';
            } finally {
                this.saving = false;
            }
        },
    }));
};
