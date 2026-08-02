const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 2,
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

const firstValidationMessages = (errors = {}) => Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
        field,
        Array.isArray(messages) ? messages[0] : String(messages),
    ]),
);

export const registerExpenseComponents = (Alpine) => {
    Alpine.data('expenseIndexPage', (config) => ({
        config,
        expenses: [],
        loading: true,
        error: '',
        filters: {
            search: '',
            date_from: '',
            date_to: '',
            category: '',
        },
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
            total_expense: 0,
            from: null,
            to: null,
        },

        init() {
            this.loadExpenses(1);
        },

        async loadExpenses(page = 1) {
            this.loading = true;
            this.error = '';

            const parameters = new URLSearchParams({
                page: String(page),
                per_page: String(this.meta.per_page),
            });

            Object.entries(this.filters).forEach(([key, value]) => {
                if (String(value).trim() !== '') {
                    parameters.set(key, value);
                }
            });

            try {
                const response = await fetch(`/api/expenses?${parameters.toString()}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                this.expenses = payload.data ?? [];
                this.meta = {
                    ...this.meta,
                    ...(payload.meta ?? {}),
                };
            } catch (error) {
                this.error = error?.message ?? 'Pengeluaran belum berhasil dimuat.';
                this.expenses = [];
            } finally {
                this.loading = false;
            }
        },

        applyFilters() {
            this.loadExpenses(1);
        },

        resetFilters() {
            this.filters = {
                search: '',
                date_from: '',
                date_to: '',
                category: '',
            };
            this.loadExpenses(1);
        },

        goToPage(page) {
            if (page < 1 || page > this.meta.last_page || page === this.meta.current_page) {
                return;
            }

            this.loadExpenses(page);
        },

        async deleteExpense(expense) {
            if (!window.confirm(`Hapus pengeluaran ${expense.category_label} sebesar ${this.formatRupiah(expense.amount)}?`)) {
                return;
            }

            this.error = '';

            try {
                const response = await fetch(`/api/expenses/${expense.id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                const targetPage = this.expenses.length === 1 && this.meta.current_page > 1
                    ? this.meta.current_page - 1
                    : this.meta.current_page;
                await this.loadExpenses(targetPage);
            } catch (error) {
                this.error = error?.message ?? 'Pengeluaran belum berhasil dihapus.';
            }
        },

        editUrl(id) {
            return this.config.editUrlTemplate.replace('__ID__', String(id));
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

        get rangeSummary() {
            if (!this.meta.total) {
                return '0 transaksi';
            }

            return `Menampilkan ${this.meta.from}-${this.meta.to} dari ${this.meta.total} transaksi`;
        },
    }));

    Alpine.data('expenseFormPage', (config) => ({
        config,
        loading: Boolean(config.expenseId),
        saving: false,
        generalError: '',
        errors: {},
        proofFile: null,
        currentProofName: '',
        currentProofUrl: '',
        form: {
            expense_date: config.defaultExpenseDate,
            category: '',
            subcategory: '',
            amount: '',
            description: '',
            recipient: '',
            payment_method: '',
        },

        init() {
            if (this.config.expenseId) {
                this.loadExpense();
            }
        },

        async loadExpense() {
            this.loading = true;
            this.generalError = '';

            try {
                const response = await fetch(`/api/expenses/${this.config.expenseId}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const payload = await parseJsonResponse(response);

                if (!response.ok) {
                    throw payload;
                }

                const expense = payload.data;
                this.form = {
                    expense_date: expense.expense_date ?? this.config.defaultExpenseDate,
                    category: expense.category ?? '',
                    subcategory: expense.subcategory ?? '',
                    amount: expense.amount ?? '',
                    description: expense.description ?? '',
                    recipient: expense.recipient ?? '',
                    payment_method: expense.payment_method ?? '',
                };
                this.currentProofName = expense.proof_original_name ?? '';
                this.currentProofUrl = expense.proof_download_url ?? '';
            } catch (error) {
                this.generalError = error?.message ?? 'Data pengeluaran belum berhasil dimuat.';
            } finally {
                this.loading = false;
            }
        },

        categoryChanged() {
            this.clearError('category');

            if (this.form.category !== this.config.employeeCategory) {
                this.form.subcategory = '';
                this.clearError('subcategory');
            }
        },

        proofChanged(event) {
            [this.proofFile] = event.target.files ?? [];
            this.clearError('proof_payment');
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

            if (!this.form.expense_date) errors.expense_date = 'Tanggal wajib diisi.';
            if (!this.form.category) errors.category = 'Kategori wajib dipilih.';
            if (this.form.category === this.config.employeeCategory && !this.form.subcategory) {
                errors.subcategory = 'Subkategori wajib dipilih untuk Biaya Karyawan.';
            }
            if (!Number.isFinite(Number(this.form.amount)) || Number(this.form.amount) <= 0) {
                errors.amount = 'Nominal harus lebih besar dari 0.';
            }
            if (!this.form.description.trim()) errors.description = 'Keterangan wajib diisi.';
            if (!this.form.recipient.trim()) errors.recipient = 'Penerima wajib diisi.';
            if (!this.form.payment_method.trim()) errors.payment_method = 'Metode pembayaran wajib diisi.';
            if (!this.config.expenseId && !this.proofFile) errors.proof_payment = 'Bukti pembayaran wajib diunggah.';

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
            const payload = new FormData();

            Object.entries(this.form).forEach(([field, value]) => {
                payload.append(field, value ?? '');
            });

            if (this.proofFile) {
                payload.append('proof_payment', this.proofFile);
            }

            if (this.config.expenseId) {
                payload.append('_method', 'PATCH');
            }

            try {
                const endpoint = this.config.expenseId
                    ? `/api/expenses/${this.config.expenseId}`
                    : '/api/expenses';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: payload,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const responsePayload = await parseJsonResponse(response);

                if (!response.ok) {
                    if (response.status === 422) {
                        this.errors = firstValidationMessages(responsePayload.errors);
                    }

                    throw responsePayload;
                }

                window.location.assign(this.config.indexUrl);
            } catch (error) {
                this.generalError = error?.message ?? 'Pengeluaran belum berhasil disimpan.';
            } finally {
                this.saving = false;
            }
        },
    }));
};
