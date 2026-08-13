const rupiah = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value) || 0);

const emptySummary = () => ({
    account_name: 'Rekening Utama', bank_name: '-', account_number: '', opening_balance: 0,
    current_balance: 0, income_this_month: 0, expense_this_month: 0, net_cash_flow: 0,
    has_negative_balance: false,
});

export function registerCashBankComponents(Alpine) {
    Alpine.data('cashBankPage', (config = {}) => ({
        config,
        summary: emptySummary(),
        transactions: [],
        meta: { current_page: 1, last_page: 1, total: 0, beginning_balance: 0 },
        filters: { search: '', date_from: '', date_to: '', type: '', category: '', per_page: 15 },
        accountSettingsOpen: false,
        accountForm: { name: '', bank_name: '', account_number: '', opening_balance: 0 },
        formOpen: false,
        editingId: null,
        form: { transaction_date: new Date().toISOString().slice(0, 10), type: 'income', category: 'other_income', amount: '', description: '' },
        errors: {},
        loading: false,
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
            const response = await fetch('/api/cash-bank/summary', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const payload = await response.json().catch(() => ({}));
            if (response.ok) this.summary = payload.data;
        },

        async loadTransactions(page = 1) {
            this.loading = true;
            this.error = '';
            const params = new URLSearchParams({ page, per_page: this.filters.per_page });
            Object.entries(this.filters).forEach(([key, value]) => { if (value && key !== 'per_page') params.set(key, value); });

            try {
                const response = await fetch(`/api/cash-bank/transactions?${params}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message ?? 'Riwayat Kas & Bank gagal dimuat.');
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
                transaction_date: new Date().toISOString().slice(0, 10), type,
                category: type === 'income' ? 'other_income' : 'operational_cost', amount: '', description: '',
            };
            this.errors = {};
            this.notice = '';
            this.formOpen = true;
            this.$nextTick(() => this.$refs.manualForm?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },

        openAccountSettings() {
            this.accountForm = {
                name: this.summary.account_name,
                bank_name: this.summary.bank_name,
                account_number: this.summary.account_number ?? '',
                opening_balance: this.summary.opening_balance,
            };
            this.notice = '';
            this.error = '';
            this.accountSettingsOpen = true;
            this.$nextTick(() => this.$refs.accountForm?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },

        async saveAccount() {
            if (this.savingAccount) return;
            this.savingAccount = true;
            this.error = '';
            this.notice = '';

            try {
                const response = await fetch('/api/cash-bank/account', {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify(this.accountForm),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message ?? 'Rekening belum dapat diperbarui.');
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
                category: transaction.category, amount: transaction.amount, description: transaction.description,
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
                    throw new Error(payload.message ?? 'Transaksi belum dapat disimpan.');
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
            const response = await fetch(`/api/cash-bank/transactions/${transaction.id}`, {
                method: 'DELETE', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                this.error = payload.message ?? 'Transaksi belum dapat dibatalkan.';
                return;
            }
            this.notice = payload.message;
            await Promise.all([this.loadSummary(), this.loadTransactions(this.meta.current_page)]);
        },

        resetFilters() {
            this.filters = { search: '', date_from: '', date_to: '', type: '', category: '', per_page: 15 };
            this.loadTransactions();
        },

        formatRupiah: rupiah,
        formatDate(value) { return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`)); },
    }));
}
