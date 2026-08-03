@php
    $formConfig = [
        'expenseId' => $expenseId,
        'defaultExpenseDate' => $defaultExpenseDate,
        'categories' => $categoryOptions,
        'employeeCategory' => \App\Models\Expense::CATEGORY_EMPLOYEE,
        'employeeSubcategories' => $employeeSubcategoryOptions,
        'indexUrl' => route('expenses.index'),
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ $expenseId ? 'Edit' : 'Tambah' }} pengeluaran YokPrinting.ID">
        <title>{{ $expenseId ? 'Edit' : 'Tambah' }} Pengeluaran - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                    </button>
                    <a href="{{ route('expenses.index') }}" class="text-sm font-medium text-muted hover:text-ink">Pengeluaran</a>
                    <span class="mx-2 text-line">/</span>
                    <span class="text-sm font-medium text-ink">{{ $expenseId ? 'Edit' : 'Tambah' }}</span>
                </header>

                <main
                    class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='expenseFormPage(@json($formConfig))'
                    x-init="init()"
                >
                    <div class="mb-6">
                        <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink" x-text="config.expenseId ? 'Edit pengeluaran' : 'Tambah pengeluaran'"></h1>
                        <p class="mt-1 text-sm text-muted">Gunakan keterangan untuk rincian Biaya Produksi, Belanjaan, dan Biaya Tempat.</p>
                    </div>

                    <form class="rounded-xl border border-line bg-white p-5 sm:p-7" @submit.prevent="submit()">
                        <div x-show="generalError" x-cloak class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="generalError"></div>
                        <div x-show="loading" class="py-10 text-center text-sm text-muted">Memuat data pengeluaran...</div>

                        <div x-show="!loading" class="grid gap-5 sm:grid-cols-2">
                            <label>
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Tanggal <span class="text-red-600">*</span></span>
                                <input type="date" class="form-control" x-model="form.expense_date" @input="clearError('expense_date')" required>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.expense_date" x-text="errors.expense_date"></span>
                            </label>

                            <label>
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Kategori <span class="text-red-600">*</span></span>
                                <select class="form-control" x-model="form.category" @change="categoryChanged()" required>
                                    <option value="">Pilih kategori</option>
                                    <template x-for="(label, value) in config.categories" :key="value">
                                        <option :value="value" x-text="label"></option>
                                    </template>
                                </select>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.category" x-text="errors.category"></span>
                            </label>

                            <label x-show="form.category === config.employeeCategory" x-cloak>
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Subkategori <span class="text-red-600">*</span></span>
                                <select class="form-control" x-model="form.subcategory" @change="clearError('subcategory')">
                                    <option value="">Pilih subkategori</option>
                                    <template x-for="(label, value) in config.employeeSubcategories" :key="value">
                                        <option :value="value" x-text="label"></option>
                                    </template>
                                </select>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.subcategory" x-text="errors.subcategory"></span>
                            </label>

                            <label>
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Nominal <span class="text-red-600">*</span></span>
                                <input type="number" min="0.01" step="0.01" class="form-control" x-model="form.amount" @input="clearError('amount')" placeholder="0" required>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.amount" x-text="errors.amount"></span>
                            </label>

                            <label>
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Penerima <span class="text-red-600">*</span></span>
                                <input type="text" maxlength="255" class="form-control" x-model="form.recipient" @input="clearError('recipient')" required>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.recipient" x-text="errors.recipient"></span>
                            </label>

                            <label>
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Metode pembayaran <span class="text-red-600">*</span></span>
                                <input type="text" maxlength="100" class="form-control" x-model="form.payment_method" @input="clearError('payment_method')" placeholder="Contoh: transfer bank atau tunai" required>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.payment_method" x-text="errors.payment_method"></span>
                            </label>

                            <label class="sm:col-span-2">
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Keterangan <span class="text-red-600">*</span></span>
                                <textarea rows="4" maxlength="2000" class="form-control" x-model="form.description" @input="clearError('description')" placeholder="Tuliskan detail transaksi" required></textarea>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.description" x-text="errors.description"></span>
                            </label>

                            <label class="sm:col-span-2">
                                <span class="mb-1.5 block text-sm font-semibold text-ink">Bukti pembayaran <span class="text-red-600">*</span></span>
                                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control" @change="proofChanged($event)" :required="!config.expenseId && !currentProofName">
                                <span class="mt-1 block text-xs text-muted">JPG, PNG, WebP, atau PDF. Maksimum 5 MB.</span>
                                <a x-show="currentProofUrl" :href="currentProofUrl" class="mt-2 inline-block text-xs font-semibold text-brand-700 hover:underline" x-text="`Bukti saat ini: ${currentProofName}`"></a>
                                <span class="mt-1 block text-xs text-red-700" x-show="errors.proof_payment" x-text="errors.proof_payment"></span>
                            </label>
                        </div>

                        <div class="mt-7 flex flex-col-reverse gap-3 border-t border-line pt-5 sm:flex-row sm:justify-end">
                            <a href="{{ route('expenses.index') }}" class="rounded-lg border border-line px-4 py-2.5 text-center text-sm font-semibold text-muted hover:bg-canvas">Batal</a>
                            <button type="submit" class="rounded-lg bg-brand-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving || loading" x-text="saving ? 'Menyimpan...' : 'Simpan pengeluaran'"></button>
                        </div>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
