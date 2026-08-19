@php
    $user = auth()->user();
    $config = [
        'canCreate' => $user?->hasPermission('cash_bank.create') ?? false,
        'canUpdate' => $user?->hasPermission('cash_bank.update') ?? false,
        'canDelete' => $user?->hasPermission('cash_bank.delete') ?? false,
        'canExport' => $user?->hasPermission('report.export') ?? false,
        'timezone' => config('app.timezone'),
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Ledger dan mutasi rekening utama usaha">
        <title>Kas & Bank - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="mr-3 min-h-11 min-w-11 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                    </button>
                    <div class="flex items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Kas & Bank</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8" x-data='cashBankPage(@json($config))' x-init="init()">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Keuangan</span>
                            <h1 class="mt-3 text-2xl font-semibold text-ink sm:text-[1.75rem]">Kas & Bank</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau saldo dan seluruh uang masuk atau keluar dari rekening utama usaha.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button x-show="config.canExport" type="button" class="min-h-11 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-canvas disabled:cursor-wait disabled:opacity-60" :disabled="exporting" @click="exportCsv()">
                                <span x-text="exporting ? 'Mengekspor...' : 'Export CSV'"></span>
                            </button>
                            <button x-show="config.canUpdate" type="button" class="min-h-11 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-50" :disabled="summaryLoading || !summaryLoaded" :title="summaryLoaded ? 'Ubah rekening utama' : 'Ringkasan rekening belum tersedia'" @click="openAccountSettings()">Atur Rekening</button>
                            <button x-show="config.canCreate" type="button" class="min-h-11 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-sm font-semibold text-green-800 hover:bg-green-100" @click="openCreate('income')">+ Tambah Uang Masuk</button>
                            <button x-show="config.canCreate" type="button" class="min-h-11 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800" @click="openCreate('expense')">+ Tambah Uang Keluar</button>
                        </div>
                    </div>

                    <div x-cloak x-show="summaryError" class="mt-6 flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 sm:flex-row sm:items-center sm:justify-between" role="alert" aria-live="assertive">
                        <span x-text="summaryError"></span>
                        <button type="button" class="min-h-11 shrink-0 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-800 hover:bg-red-100 disabled:cursor-wait disabled:opacity-60" :disabled="summaryLoading" @click="loadSummary()" x-text="summaryLoading ? 'Memuat...' : 'Coba lagi'"></button>
                    </div>

                    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan Kas dan Bank" :aria-busy="summaryLoading">
                        <article class="rounded-xl border border-line bg-white p-5">
                            <p class="text-sm font-medium text-muted">Saldo Bank</p>
                            <div x-show="summaryLoading" class="mt-3 h-7 w-36 animate-pulse rounded bg-surface-high" aria-hidden="true"></div>
                            <p x-show="!summaryLoading" class="mt-2 min-h-8 text-2xl font-semibold" :class="summary.has_negative_balance ? 'text-red-700' : 'text-ink'" x-text="summaryLoaded ? formatRupiah(summary.current_balance) : '—'"></p>
                            <p class="mt-3 min-h-4 text-xs text-muted" x-text="summaryLoaded ? `${summary.bank_name} · ${summary.account_name}` : 'Data rekening belum tersedia'"></p>
                        </article>
                        <article class="rounded-xl border border-line bg-white p-5">
                            <p class="text-sm font-medium text-muted">Pemasukan Bulan Ini</p>
                            <div x-show="summaryLoading" class="mt-3 h-7 w-36 animate-pulse rounded bg-surface-high" aria-hidden="true"></div>
                            <p x-show="!summaryLoading" class="mt-2 min-h-8 text-2xl font-semibold text-green-700" x-text="summaryLoaded ? formatRupiah(summary.income_this_month) : '—'"></p>
                            <p class="mt-3 text-xs text-muted">Uang masuk berstatus tercatat</p>
                        </article>
                        <article class="rounded-xl border border-line bg-white p-5">
                            <p class="text-sm font-medium text-muted">Pengeluaran Bulan Ini</p>
                            <div x-show="summaryLoading" class="mt-3 h-7 w-36 animate-pulse rounded bg-surface-high" aria-hidden="true"></div>
                            <p x-show="!summaryLoading" class="mt-2 min-h-8 text-2xl font-semibold text-red-700" x-text="summaryLoaded ? formatRupiah(summary.expense_this_month) : '—'"></p>
                            <p class="mt-3 text-xs text-muted">Uang keluar berstatus tercatat</p>
                        </article>
                        <article class="rounded-xl border border-line bg-white p-5">
                            <p class="text-sm font-medium text-muted">Arus Kas Bersih</p>
                            <div x-show="summaryLoading" class="mt-3 h-7 w-36 animate-pulse rounded bg-surface-high" aria-hidden="true"></div>
                            <p x-show="!summaryLoading" class="mt-2 min-h-8 text-2xl font-semibold" :class="summary.net_cash_flow < 0 ? 'text-red-700' : 'text-ink'" x-text="summaryLoaded ? formatRupiah(summary.net_cash_flow) : '—'"></p>
                            <p class="mt-3 text-xs text-muted">Pemasukan dikurangi pengeluaran</p>
                        </article>
                    </section>

                    <div x-cloak x-show="summaryLoaded && summary.has_negative_balance" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" role="status">
                        Saldo rekening sedang negatif. Transaksi tetap dicatat agar histori keuangan tetap utuh.
                    </div>

                    <section x-ref="accountForm" x-cloak x-show="accountSettingsOpen" class="mt-6 rounded-xl border border-line bg-white" aria-labelledby="bank-account-heading">
                        <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                            <div>
                                <h2 id="bank-account-heading" class="font-semibold text-ink">Pengaturan rekening utama</h2>
                                <p class="mt-1 text-sm text-muted">Perubahan saldo awal memengaruhi seluruh saldo berjalan.</p>
                            </div>
                            <button type="button" class="min-h-11 min-w-11 rounded-lg px-3 py-2 text-sm font-semibold text-muted hover:bg-canvas" @click="accountSettingsOpen = false">Tutup</button>
                        </div>
                        <form class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 sm:p-6" @submit.prevent="saveAccount()">
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Nama rekening</span><input id="account-name" type="text" maxlength="255" class="form-control" x-model="accountForm.name" :aria-invalid="Boolean(fieldError(accountErrors, 'name'))" aria-describedby="account-name-error" required><span id="account-name-error" x-show="fieldError(accountErrors, 'name')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(accountErrors, 'name')" role="alert"></span></label>
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Nama bank</span><input id="account-bank-name" type="text" maxlength="255" class="form-control" x-model="accountForm.bank_name" :aria-invalid="Boolean(fieldError(accountErrors, 'bank_name'))" aria-describedby="account-bank-name-error" required><span id="account-bank-name-error" x-show="fieldError(accountErrors, 'bank_name')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(accountErrors, 'bank_name')" role="alert"></span></label>
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Nomor rekening</span><input id="account-number" type="text" maxlength="100" class="form-control" x-model="accountForm.account_number" :aria-invalid="Boolean(fieldError(accountErrors, 'account_number'))" aria-describedby="account-number-error"><span id="account-number-error" x-show="fieldError(accountErrors, 'account_number')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(accountErrors, 'account_number')" role="alert"></span></label>
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Saldo awal</span><input id="account-opening-balance" type="number" step="0.01" class="form-control" x-model="accountForm.opening_balance" :aria-invalid="Boolean(fieldError(accountErrors, 'opening_balance'))" aria-describedby="account-opening-balance-error" required><span id="account-opening-balance-error" x-show="fieldError(accountErrors, 'opening_balance')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(accountErrors, 'opening_balance')" role="alert"></span></label>
                            <button type="submit" class="min-h-11 self-end rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-60 sm:col-span-2 lg:col-span-1" :disabled="savingAccount" x-text="savingAccount ? 'Menyimpan...' : 'Simpan Rekening'"></button>
                        </form>
                    </section>

                    <section x-ref="manualForm" x-cloak x-show="formOpen" class="mt-6 rounded-xl border border-line bg-white" aria-labelledby="manual-transaction-heading">
                        <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                            <div>
                                <h2 id="manual-transaction-heading" class="font-semibold text-ink" x-text="editingId ? 'Edit transaksi manual' : (form.type === 'income' ? 'Tambah Uang Masuk' : 'Tambah Uang Keluar')"></h2>
                                <p class="mt-1 text-sm text-muted">Gunakan form ini hanya untuk transaksi yang tidak berasal dari invoice atau pengeluaran.</p>
                            </div>
                            <button type="button" class="min-h-11 min-w-11 rounded-lg px-3 py-2 text-sm font-semibold text-muted hover:bg-canvas" @click="formOpen = false">Tutup</button>
                        </div>
                        <form class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 sm:p-6" @submit.prevent="save()">
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Tanggal</span>
                                <input id="transaction-date" type="date" class="form-control" x-model="form.transaction_date" :aria-invalid="Boolean(fieldError(errors, 'transaction_date'))" aria-describedby="transaction-date-error" required>
                                <span id="transaction-date-error" x-show="fieldError(errors, 'transaction_date')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(errors, 'transaction_date')" role="alert"></span>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Jenis transaksi</span>
                                <select id="transaction-type" class="form-control" x-model="form.type" @change="typeChanged()" :aria-invalid="Boolean(fieldError(errors, 'type'))" aria-describedby="transaction-type-error" required>
                                    <option value="income">Uang Masuk</option>
                                    <option value="expense">Uang Keluar</option>
                                </select>
                                <span id="transaction-type-error" x-show="fieldError(errors, 'type')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(errors, 'type')" role="alert"></span>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Kategori</span>
                                <select id="transaction-category" class="form-control" x-model="form.category" :aria-invalid="Boolean(fieldError(errors, 'category'))" aria-describedby="transaction-category-error" required>
                                    <template x-for="(label, value) in categories" :key="value"><option :value="value" x-text="label"></option></template>
                                </select>
                                <span id="transaction-category-error" x-show="fieldError(errors, 'category')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(errors, 'category')" role="alert"></span>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Metode</span>
                                <select id="transaction-payment-method" class="form-control" x-model="form.payment_method" :aria-invalid="Boolean(fieldError(errors, 'payment_method'))" aria-describedby="transaction-payment-method-error" required>
                                    <option value="transfer">Transfer</option>
                                    <option value="cash">Tunai</option>
                                </select>
                                <span id="transaction-payment-method-error" x-show="fieldError(errors, 'payment_method')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(errors, 'payment_method')" role="alert"></span>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Nominal</span>
                                <input id="transaction-amount" type="number" min="0.01" step="0.01" class="form-control" x-model="form.amount" placeholder="0" :aria-invalid="Boolean(fieldError(errors, 'amount'))" aria-describedby="transaction-amount-error" required>
                                <span id="transaction-amount-error" x-show="fieldError(errors, 'amount')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(errors, 'amount')" role="alert"></span>
                            </label>
                            <label class="sm:col-span-2 lg:col-span-1 xl:col-span-2">
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Keterangan</span>
                                <input id="transaction-description" type="text" maxlength="2000" class="form-control" x-model="form.description" placeholder="Jelaskan transaksi" :aria-invalid="Boolean(fieldError(errors, 'description'))" aria-describedby="transaction-description-error" required>
                                <span id="transaction-description-error" x-show="fieldError(errors, 'description')" class="mt-1.5 block text-xs text-red-700" x-text="fieldError(errors, 'description')" role="alert"></span>
                            </label>
                            <button type="submit" class="min-h-11 self-end rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-60" :disabled="saving" x-text="saving ? 'Menyimpan...' : 'Simpan'"></button>
                        </form>
                    </section>

                    <div x-cloak x-show="notice" class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900" x-text="notice" role="status" aria-live="polite"></div>
                    <div x-cloak x-show="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900" x-text="error" role="alert" aria-live="assertive"></div>

                    <section class="mt-6 rounded-xl border border-line bg-white" aria-labelledby="cash-bank-history-heading">
                        <div class="border-b border-line px-5 py-4 sm:px-6">
                            <h2 id="cash-bank-history-heading" class="font-semibold text-ink">Histori transaksi</h2>
                            <p class="mt-1 text-sm text-muted">Saldo berjalan dihitung dari saldo awal dan seluruh transaksi tercatat sebelumnya.</p>
                        </div>
                        <form class="grid gap-3 border-b border-line p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" @submit.prevent="loadTransactions()">
                            <label class="sm:col-span-2 lg:col-span-1"><span class="mb-1.5 block text-xs font-semibold text-muted">Pencarian</span><input type="search" class="form-control" x-model="filters.search" placeholder="Nomor, kategori, keterangan"></label>
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Dari tanggal</span><input type="date" class="form-control" x-model="filters.date_from"></label>
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Sampai tanggal</span><input type="date" class="form-control" x-model="filters.date_to"></label>
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Jenis</span><select class="form-control" x-model="filters.type"><option value="">Semua transaksi</option><option value="income">Uang Masuk</option><option value="expense">Uang Keluar</option></select></label>
                            <label><span class="mb-1.5 block text-xs font-semibold text-muted">Metode</span><select class="form-control" x-model="filters.payment_method"><option value="">Semua metode</option><option value="transfer">Transfer</option><option value="cash">Tunai</option></select></label>
                            <div class="flex items-end gap-3 sm:col-span-2 lg:col-span-1">
                                <button type="submit" class="min-h-11 flex-1 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-60" :disabled="loading" x-text="loading ? 'Memuat...' : 'Terapkan'"></button>
                                <button type="button" class="min-h-11 flex-1 rounded-lg border border-line px-4 py-2.5 text-sm font-semibold text-muted hover:bg-canvas disabled:cursor-wait disabled:opacity-60" :disabled="loading" @click="resetFilters()">Reset</button>
                            </div>
                        </form>

                        <div x-show="loading" class="min-h-64 p-10 text-center text-sm text-muted" role="status" aria-live="polite">
                            <span class="inline-flex items-center gap-2"><span class="size-4 animate-spin rounded-full border-2 border-line border-t-brand-700" aria-hidden="true"></span>Memuat mutasi rekening...</span>
                        </div>
                        <div x-show="!loading" class="overflow-x-auto" :aria-busy="loading">
                            <table class="w-full min-w-[1100px] text-left text-sm">
                                <thead><tr class="border-b border-line text-xs font-semibold text-muted">
                                    <th class="px-5 py-3 sm:px-6">Tanggal / Nomor</th><th class="px-5 py-3">Keterangan</th><th class="px-5 py-3">Sumber</th><th class="px-5 py-3">Metode</th>
                                    <th class="px-5 py-3 text-right">Masuk</th><th class="px-5 py-3 text-right">Keluar</th><th class="px-5 py-3 text-right">Saldo</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right sm:px-6">Aksi</th>
                                </tr></thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="transaction in transactions" :key="transaction.id">
                                        <tr class="align-top" :class="transaction.status === 'cancelled' ? 'bg-canvas opacity-70' : 'hover:bg-brand-50/40'">
                                            <td class="whitespace-nowrap px-5 py-4 sm:px-6"><span class="block font-medium text-ink" x-text="formatDate(transaction.transaction_date)"></span><span class="mt-1 block font-mono text-xs text-muted" x-text="transaction.transaction_number"></span></td>
                                            <td class="max-w-sm px-5 py-4"><span class="block font-medium text-ink" x-text="transaction.category_label"></span><span class="mt-1 block text-xs leading-5 text-muted" x-text="transaction.description"></span></td>
                                            <td class="px-5 py-4 text-muted" x-text="transaction.source_label"></td>
                                            <td class="px-5 py-4">
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="transaction.payment_method === 'cash' ? 'bg-yellow-100 text-yellow-900' : 'bg-brand-100 text-brand-800'" x-text="transaction.payment_method_label"></span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-green-700" x-text="transaction.income ? formatRupiah(transaction.income) : '-' "></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-red-700" x-text="transaction.expense ? formatRupiah(transaction.expense) : '-' "></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-ink" x-text="formatRupiah(transaction.running_balance)"></td>
                                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="transaction.status === 'posted' ? 'bg-green-100 text-green-800' : 'bg-surface-high text-muted'" x-text="transaction.status_label"></span></td>
                                            <td class="px-5 py-4 text-right sm:px-6"><div class="flex justify-end gap-2" x-show="transaction.is_manual && transaction.status === 'posted'">
                                                <button x-show="config.canUpdate" type="button" class="min-h-11 rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink hover:bg-canvas" @click="edit(transaction)">Edit</button>
                                                <button x-show="config.canDelete" type="button" class="min-h-11 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50" @click="cancel(transaction)">Batalkan</button>
                                            </div></td>
                                        </tr>
                                    </template>
                                    <tr x-show="transactions.length === 0"><td colspan="9" class="px-5 py-12 text-center text-muted">Belum ada transaksi yang sesuai filter.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <footer class="flex flex-col gap-3 border-t border-line px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-muted">Saldo awal periode: <strong class="text-ink" x-text="formatRupiah(meta.beginning_balance)"></strong></p>
                            <div class="flex items-center gap-2"><button type="button" class="min-h-11 rounded-lg border border-line px-3 py-2 font-semibold disabled:opacity-40" :disabled="loading || meta.current_page <= 1" @click="loadTransactions(meta.current_page - 1)">Sebelumnya</button><span class="text-muted" x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span><button type="button" class="min-h-11 rounded-lg border border-line px-3 py-2 font-semibold disabled:opacity-40" :disabled="loading || meta.current_page >= meta.last_page" @click="loadTransactions(meta.current_page + 1)">Berikutnya</button></div>
                        </footer>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
