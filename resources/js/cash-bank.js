const rupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value) || 0);

const emptySummary = () => ({
    account_name: 'Rekening Utama', bank_name: '-', account_number: '', opening_balance: 0,
    current_balance: 0, income_this_month: 0, expense_this_month: 0, net_cash_flow: 0,
    has_negative_balance: false,
});

export const businessDate = (timeZone = 'Asia/Jakarta', date = new Date()) => {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(date);
    const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

    return `${values.year}-${values.month}-${values.day}`;
};

export function registerCashBankComponents(Alpine) {
    Alpine.data('cashBankPage', (config = {}) => ({
        config,
        summary: emptySummary(),
        transactions: [],
        meta: { current_page: 1, last_page: 1, total: 0, beginning_balance: 0 },
        filters: { search: '', date_from: '', date_to: '', type: '', category: '', payment_method: '', per_page: 15 },
        accountSettingsOpen: false,
        accountForm: { name: '', bank_name: '', account_number: '', opening_balance: 0 },
        formOpen: false,
        editingId: null,
        form: { transaction_date: businessDate(config.timezone), type: 'income', category: 'other_income', payment_method: 'transfer', amount: '', description: '' },
        errors: {},
        accountErrors: {},
        loading: false,
        summaryLoading: true,
        summaryLoaded: false,
        summaryError: '',
        saving: false,
        savingAccount: false,
        notice: '',
        error: '',

        get categories() {
            return this.form.type === 'income'
                ? { owner_capital: 'Modal Owner', supplier_refund: 'Refund Supplier', other_income: 'Pendapatan Lain', balance_adjustment: 'Koreksi Saldo' }
                : { bank_fee: 'Biaya Admin Bank', owner_withdrawal: 'Penarikan Owner', tax: 'Pajak', operational_cost: 'Biaya Operasional', balance_adjustment: 'Koreksi Saldo' };
        },

        async init() {
            await Promise.all([this.loadSummary(), this.loadTransactions()]);
        },

        async loadSummary() {
            this.summaryLoading = true;
            this.summaryError = '';

            try {
                const response = await fetch('/api/cash-bank/summary', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !payload.data) throw new Error(payload.message ?? 'Ringkasan Kas & Bank gagal dimuat.');
                this.summary = payload.data;
                this.summaryLoaded = true;
            } catch (error) {
                this.summaryLoaded = false;
                this.summaryError = error instanceof Error ? error.message : 'Ringkasan Kas & Bank gagal dimuat.';
            } finally {
                this.summaryLoading = false;
            }
        },

        async loadTransactions(page = 1) {
            this.loading = true;
            this.error = '';
            const params = new URLSearchParams({ page, per_page: this.filters.per_page });
            Object.entries(this.filters).forEach(([key, value]) => { if (value && key !== 'per_page') params.set(key, value); });

            try {
                const response = await fetch(`/api/cash-bank/transactions?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || !Array.isArray(payload.data) || !payload.meta) {
                    throw new Error(payload.message ?? 'Riwayat Kas & Bank gagal dimuat.');
                }
                this.transactions = payload.data;
                this.meta = payload.meta;
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        openCreate(type) {
            this.editingId = null;
            this.form = {
                transaction_date: businessDate(this.config.timezone), type,
                category: type === 'income' ? 'other_income' : 'operational_cost',
                payment_method: 'transfer', amount: '', description: '',
            };
            this.errors = {};
            this.notice = '';
            this.formOpen = true;
            this.$nextTick(() => this.$refs.manualForm?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },

        openAccountSettings() {
            if (!this.summaryLoaded) {
                this.summaryError = 'Muat ulang ringkasan sebelum mengubah rekening utama.';
                return;
            }

            this.accountForm = {
                name: this.summary.account_name,
                bank_name: this.summary.bank_name,
                account_number: this.summary.account_number ?? '',
                opening_balance: this.summary.opening_balance,
            };
            this.notice = '';
            this.error = '';
            this.accountErrors = {};
            this.accountSettingsOpen = true;
            this.$nextTick(() => this.$refs.accountForm?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },

        async saveAccount() {
            if (this.savingAccount) return;
            this.savingAccount = true;
            this.error = '';
            this.notice = '';
            this.accountErrors = {};

            try {
                const response = await fetch('/api/cash-bank/account', {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify(this.accountForm),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.accountErrors = payload.errors ?? {};
                    throw new Error(this.firstValidationError(this.accountErrors) ?? payload.message ?? 'Rekening belum dapat diperbarui.');
                }
                this.notice = payload.message;
                this.accountSettingsOpen = false;
                await Promise.all([this.loadSummary(), this.loadTransactions(this.meta.current_page)]);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.savingAccount = false;
            }
        },

        edit(transaction) {
            if (!transaction.is_manual || transaction.status === 'cancelled') return;
            this.editingId = transaction.id;
            this.form = {
                transaction_date: transaction.transaction_date, type: transaction.type,
                category: transaction.category, payment_method: transaction.payment_method ?? 'transfer',
                amount: transaction.amount, description: transaction.description,
            };
            this.errors = {};
            this.formOpen = true;
            this.$nextTick(() => this.$refs.manualForm?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },

        typeChanged() {
            this.form.category = this.form.type === 'income' ? 'other_income' : 'operational_cost';
        },

        async save() {
            if (this.saving) return;
            this.saving = true;
            this.errors = {};
            this.notice = '';
            this.error = '';
            const url = this.editingId ? `/api/cash-bank/transactions/${this.editingId}` : '/api/cash-bank/transactions';

            try {
                const response = await fetch(url, {
                    method: this.editingId ? 'PATCH' : 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify(this.form),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.errors = payload.errors ?? {};
                    throw new Error(this.firstValidationError(this.errors) ?? payload.message ?? 'Transaksi belum dapat disimpan.');
                }
                this.notice = payload.message;
                this.formOpen = false;
                await Promise.all([this.loadSummary(), this.loadTransactions(this.meta.current_page)]);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        async cancel(transaction) {
            if (!transaction.is_manual || transaction.status === 'cancelled' || !window.confirm(`Batalkan ${transaction.transaction_number}?`)) return;
            this.error = '';

            try {
                const response = await fetch(`/api/cash-bank/transactions/${transaction.id}`, {
                    method: 'DELETE', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message ?? 'Transaksi belum dapat dibatalkan.');
                this.notice = payload.message;
                await Promise.all([this.loadSummary(), this.loadTransactions(this.meta.current_page)]);
            } catch (error) {
                this.error = error instanceof Error ? error.message : 'Transaksi belum dapat dibatalkan.';
            }
        },

        resetFilters() {
            this.filters = { search: '', date_from: '', date_to: '', type: '', category: '', payment_method: '', per_page: 15 };
            this.loadTransactions();
        },

        formatRupiah: rupiah,
        fieldError(source, field) { return Array.isArray(source?.[field]) ? source[field][0] : (source?.[field] ?? ''); },
        firstValidationError(source) {
            const first = Object.values(source ?? {}).flat().find(Boolean);
            return typeof first === 'string' ? first : null;
        },
        formatDate(value) { return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`)); },
    }));
}
