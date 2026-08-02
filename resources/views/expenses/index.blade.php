@php
    $currentUser = auth()->user();
    $role = $currentUser?->role === \App\Models\User::ROLE_OWNER
        ? null
        : $currentUser?->roleDefinition()->with('permissions')->first();
    $rolePermissions = $role && $role->status !== \App\Models\Role::STATUS_DISABLED
        ? $role->permissions->pluck('code')
        : collect();
    $can = fn (string $permission): bool => (bool) ($currentUser?->isActive() && (
        $currentUser->role === \App\Models\User::ROLE_OWNER || $rolePermissions->contains($permission)
    ));
    $pageConfig = [
        'categories' => \App\Models\Expense::categoryOptions(),
        'canCreate' => $can('expense.create'),
        'canUpdate' => $can('expense.update'),
        'canDelete' => $can('expense.delete'),
        'createUrl' => route('expenses.create'),
        'editUrlTemplate' => route('expenses.edit', ['expense' => '__ID__']),
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Kelola transaksi pengeluaran YokPrinting.ID">
        <title>Pengeluaran - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                    </button>
                    <div class="flex items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Pengeluaran</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='expenseIndexPage(@json($pageConfig))'
                    x-init="init()"
                >
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Keuangan</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Pengeluaran</h1>
                            <p class="mt-1 text-sm leading-6 text-muted">Catat dan telusuri seluruh biaya operasional dengan bukti pembayaran dan jejak audit.</p>
                        </div>
                        <a
                            x-show="config.canCreate"
                            href="{{ route('expenses.create') }}"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800"
                        >
                            <span class="text-lg leading-none">+</span>
                            Tambah pengeluaran
                        </a>
                    </div>

                    <section class="mb-6 grid gap-4 sm:grid-cols-2" aria-label="Ringkasan pengeluaran">
                        <article class="rounded-xl border border-line bg-white p-5">
                            <p class="text-sm font-medium text-muted">Total pengeluaran sesuai filter</p>
                            <p class="mt-2 text-2xl font-semibold text-ink" x-text="formatRupiah(meta.total_expense)"></p>
                        </article>
                        <article class="rounded-xl border border-line bg-white p-5">
                            <p class="text-sm font-medium text-muted">Jumlah transaksi sesuai filter</p>
                            <p class="mt-2 text-2xl font-semibold text-ink" x-text="meta.total"></p>
                        </article>
                    </section>

                    <section class="rounded-xl border border-line bg-white">
                        <form class="grid gap-3 border-b border-line p-5 md:grid-cols-2 xl:grid-cols-[minmax(13rem,1fr)_10rem_10rem_13rem_auto_auto]" @submit.prevent="applyFilters()">
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Pencarian</span>
                                <input type="search" class="form-control" x-model="filters.search" placeholder="Keterangan, penerima, metode...">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Dari tanggal</span>
                                <input type="date" class="form-control" x-model="filters.date_from">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Sampai tanggal</span>
                                <input type="date" class="form-control" x-model="filters.date_to">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Kategori</span>
                                <select class="form-control" x-model="filters.category">
                                    <option value="">Semua kategori</option>
                                    <template x-for="(label, value) in config.categories" :key="value">
                                        <option :value="value" x-text="label"></option>
                                    </template>
                                </select>
                            </label>
                            <button type="submit" class="self-end rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800">Terapkan</button>
                            <button type="button" class="self-end rounded-lg border border-line px-4 py-2.5 text-sm font-semibold text-muted hover:bg-canvas" @click="resetFilters()">Reset</button>
                        </form>

                        <div x-show="error" x-cloak class="m-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
                        <div x-show="loading" class="p-10 text-center text-sm text-muted">Memuat pengeluaran...</div>

                        <div x-show="!loading" class="overflow-x-auto">
                            <table class="w-full min-w-[1100px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3">Tanggal</th>
                                        <th class="px-5 py-3">Kategori</th>
                                        <th class="px-5 py-3 text-right">Nominal</th>
                                        <th class="px-5 py-3">Keterangan</th>
                                        <th class="px-5 py-3">Penerima</th>
                                        <th class="px-5 py-3">Pembayaran</th>
                                        <th class="px-5 py-3">Pembuat</th>
                                        <th class="px-5 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="expense in expenses" :key="expense.id">
                                        <tr class="align-top hover:bg-brand-50/40">
                                            <td class="whitespace-nowrap px-5 py-4" x-text="formatDate(expense.expense_date)"></td>
                                            <td class="px-5 py-4">
                                                <span class="block font-semibold text-ink" x-text="expense.category_label"></span>
                                                <span class="mt-0.5 block text-xs text-muted" x-show="expense.subcategory_label" x-text="expense.subcategory_label"></span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-ink" x-text="formatRupiah(expense.amount)"></td>
                                            <td class="max-w-xs px-5 py-4 text-muted" x-text="expense.description"></td>
                                            <td class="px-5 py-4" x-text="expense.recipient"></td>
                                            <td class="px-5 py-4">
                                                <span class="block" x-text="expense.payment_method"></span>
                                                <a :href="expense.proof_download_url" class="mt-1 inline-block text-xs font-semibold text-brand-700 hover:underline">Lihat bukti</a>
                                            </td>
                                            <td class="px-5 py-4" x-text="expense.creator?.name ?? '-' "></td>
                                            <td class="px-5 py-4 text-right">
                                                <div class="flex justify-end gap-2">
                                                    <a x-show="config.canUpdate" :href="editUrl(expense.id)" class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink hover:bg-canvas">Edit</a>
                                                    <button x-show="config.canDelete" type="button" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" @click="deleteExpense(expense)">Hapus</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="expenses.length === 0">
                                        <td colspan="8" class="px-5 py-12 text-center text-muted">Belum ada pengeluaran yang sesuai filter.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <footer class="flex flex-col gap-3 border-t border-line px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-muted" x-text="rangeSummary"></p>
                            <div class="flex items-center gap-2">
                                <button type="button" class="rounded-lg border border-line px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40" :disabled="meta.current_page <= 1 || loading" @click="goToPage(meta.current_page - 1)">Sebelumnya</button>
                                <span class="px-2 text-muted" x-text="`Halaman ${meta.current_page} dari ${meta.last_page}`"></span>
                                <button type="button" class="rounded-lg border border-line px-3 py-2 font-semibold disabled:cursor-not-allowed disabled:opacity-40" :disabled="meta.current_page >= meta.last_page || loading" @click="goToPage(meta.current_page + 1)">Berikutnya</button>
                            </div>
                        </footer>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
