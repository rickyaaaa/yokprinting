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
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Daftar invoice YokPrinting.ID">

        <title>Daftar Invoice - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div
            class="min-h-screen lg:flex"
            x-data="{ sidebarOpen: false }"
            @keydown.escape.window="sidebarOpen = false"
        >
            <div
                class="fixed inset-0 z-30 bg-ink/45 lg:hidden"
                x-cloak
                x-show="sidebarOpen"
                x-transition.opacity
                @click="sidebarOpen = false"
                aria-hidden="true"
            ></div>

            <x-app-sidebar />

            <div class="min-w-0 flex-1" x-data="{ query: '', status: 'all', invoices: @js($invoiceRows) }">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="btn-icon mr-3 border-transparent lg:hidden"
                        @click="sidebarOpen = true"
                        aria-controls="app-sidebar"
                        :aria-expanded="sidebarOpen"
                        aria-label="Buka navigasi"
                    >
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <i class="iconify tabler--chevron-right hidden text-sm text-line sm:block"></i>
                        <span class="truncate font-medium text-ink">Daftar invoice</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <x-notification-bell />
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">{{ now(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y') }}</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="badge badge-brand">Daftar invoice</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar Invoice</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau invoice terbit, status pembayaran, progres pesanan, jatuh tempo, dan akses cepat ke detail pembayaran.</p>
                        </div>
                        <a href="{{ route('invoices.create') }}" class="btn btn-primary">
                            <i class="iconify tabler--plus text-base"></i>
                            Buat invoice
                        </a>
                    </div>

                    <section class="grid gap-4 md:grid-cols-3" aria-label="Ringkasan daftar invoice">
                        @foreach ($summaryCards as $card)
                            <article class="card p-5">
                                <p class="text-sm font-medium text-muted">{{ $card['label'] }}</p>
                                <p class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $card['value'] }}</p>
                                <p class="mt-3 text-xs text-muted">{{ $card['caption'] }}</p>
                            </article>
                        @endforeach
                    </section>

                    <section class="mt-6 card" aria-labelledby="invoice-table-heading">
                        <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 id="invoice-table-heading" class="font-semibold text-ink">Semua invoice</h2>
                                <p class="mt-1 text-sm text-muted">Cari berdasarkan nomor invoice, pelanggan, email, atau status.</p>
                            </div>
                            <form method="get" class="flex flex-wrap items-end gap-2">
                                <label class="text-left text-xs font-semibold text-muted">Dari<input name="date_from" type="date" value="{{ $filters['date_from'] }}" class="form-control mt-1 text-sm"></label>
                                <label class="text-left text-xs font-semibold text-muted">Sampai<input name="date_to" type="date" value="{{ $filters['date_to'] }}" class="form-control mt-1 text-sm"></label>
                                <label class="text-left text-xs font-semibold text-muted">Status<select name="status" class="form-control mt-1 text-sm"><option value="all">Semua</option><option value="unpaid" @selected($filters['status'] === 'unpaid')>Belum bayar</option><option value="partial" @selected($filters['status'] === 'partial')>Parsial</option><option value="paid" @selected($filters['status'] === 'paid')>Lunas</option><option value="cancelled" @selected($filters['status'] === 'cancelled')>Dibatalkan</option></select></label>
                                <label class="text-left text-xs font-semibold text-muted">Urutan<select name="sort" class="form-control mt-1 text-sm"><option value="latest" @selected($filters['sort'] === 'latest')>Terbaru</option><option value="oldest" @selected($filters['sort'] === 'oldest')>Terlama</option></select></label>
                                <button type="submit" class="btn bg-ink text-white hover:bg-slate-800">Terapkan</button>
                                <a href="{{ route('api.invoices.export.orders.pdf', request()->query()) }}" class="btn border-brand-200 bg-brand-50 text-brand-800 hover:bg-brand-100">{{ request()->filled('date_from') || request()->filled('date_to') ? 'Cetak pesanan sesuai tanggal' : 'Cetak pesanan hari ini' }}</a>
                                <a href="{{ route('api.invoices.export.pdf', request()->query()) }}" class="btn btn-outline">Export PDF</a>
                                <a href="{{ route('api.invoices.export.csv', request()->query()) }}" class="btn btn-outline">Export CSV</a>
                            </form>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <label class="sr-only" for="invoice-search">Cari invoice</label>
                                <input id="invoice-search" x-model.debounce.150ms="query" type="search" class="form-control sm:w-72" placeholder="Cari invoice atau pelanggan">
                                <label class="sr-only" for="invoice-status">Filter status</label>
                                <select id="invoice-status" x-model="status" class="form-control sm:w-44">
                                    <option value="all">Semua status pembayaran</option>
                                    <option value="Belum Bayar">Belum Bayar</option>
                                    <option value="Parsial">Parsial</option>
                                    <option value="Lunas">Lunas</option>
                                    <option value="Overdue">Overdue</option>
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-line text-sm">
                                <thead class="bg-surface-low text-left text-xs font-semibold uppercase tracking-[0.08em] text-muted">
                                    <tr>
                                        <th class="px-5 py-3 sm:px-6">Invoice</th>
                                        <th class="px-5 py-3 sm:px-6">Pelanggan</th>
                                        <th class="px-5 py-3 sm:px-6">Tanggal</th>
                                        <th class="px-5 py-3 sm:px-6">Jatuh tempo</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Total</th>
                                        <th class="px-5 py-3 sm:px-6">Status pembayaran</th>
                                        <th class="px-5 py-3 sm:px-6">Status pesanan</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template
                                        x-for="invoice in invoices.filter((row) => {
                                            const keyword = query.trim().toLowerCase();
                                            const matchesStatus = status === 'all' || row.status === status;
                                            const matchesKeyword = ! keyword || `${row.number} ${row.customer} ${row.email} ${row.status} ${row.order_status}`.toLowerCase().includes(keyword);

                                            return matchesStatus && matchesKeyword;
                                        })"
                                        :key="invoice.number"
                                    >
                                        <tr class="hover:bg-surface-low">
                                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-ink sm:px-6" x-text="invoice.number"></td>
                                            <td class="px-5 py-4 sm:px-6">
                                                <p class="font-medium text-ink" x-text="invoice.customer"></p>
                                                <p class="mt-0.5 text-xs text-muted" x-text="invoice.email"></p>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted sm:px-6" x-text="invoice.issue_date"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted sm:px-6" x-text="invoice.due_date"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-ink sm:px-6" x-text="invoice.amount"></td>
                                            <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                                <span
                                                    class="badge"
                                                    :class="{
                                                        'badge-success': invoice.tone === 'success',
                                                        'badge-warning': invoice.tone === 'warning',
                                                        'badge-brand': invoice.tone === 'brand',
                                                        'badge-danger': invoice.tone === 'danger',
                                                        'badge-info': invoice.tone === 'info'
                                                    }"
                                                    x-text="invoice.status"
                                                ></span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                                <span
                                                    class="badge"
                                                    :class="{
                                                        'badge-success': invoice.order_tone === 'success',
                                                        'badge-warning': invoice.order_tone === 'warning',
                                                        'badge-brand': invoice.order_tone === 'brand',
                                                        'badge-info': invoice.order_tone === 'info'
                                                    }"
                                                    x-text="invoice.order_status"
                                                ></span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                                <a :href="`/payments/invoices/${invoice.number}`" class="font-semibold text-brand-700 hover:text-brand-900">Detail</a>
                                                <template x-if="invoice.is_editable && {{ $can('invoice.update') ? 'true' : 'false' }}">
                                                    <a :href="`/invoices/${invoice.number}/edit`" class="ml-3 font-semibold text-ink hover:text-brand-900">Edit</a>
                                                </template>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
