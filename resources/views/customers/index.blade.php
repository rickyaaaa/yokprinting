<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Indeks pelanggan YokPrinting.ID">

        <title>Data Pelanggan - YokPrinting.ID</title>

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

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden"
                        @click="sidebarOpen = true"
                        aria-controls="app-sidebar"
                        :aria-expanded="sidebarOpen"
                        aria-label="Buka navigasi"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <svg class="hidden size-4 text-line sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate font-medium text-ink">Data pelanggan</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" class="relative rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800" aria-label="Lihat notifikasi">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">Jumat, 24 Juli 2026</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    @if (request('created'))
                        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900" role="status" data-testid="customer-created-notice">
                            <p class="font-semibold">Pelanggan berhasil ditambahkan.</p>
                            <p class="mt-1">{{ request('created') }} sekarang sudah tampil di daftar pelanggan.</p>
                        </div>
                    @endif

                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Data pelanggan</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Indeks pelanggan</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Kelola relasi pelanggan, pantau nilai transaksi, dan prioritaskan follow-up dari satu daftar kerja.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 7h16M7 4v6M17 4v6M6 12h12v8H6z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Juli 2026
                            </button>
                            <a href="{{ route('customers.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                </svg>
                                Tambah pelanggan
                            </a>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan pelanggan">
                        @foreach ($summaryCards as $card)
                            @php
                                $toneClass = match ($card['tone']) {
                                    'success' => 'bg-green-100 text-green-800',
                                    'warning' => 'bg-yellow-100 text-yellow-900',
                                    default => 'bg-brand-100 text-brand-800',
                                };
                            @endphp
                            <article class="rounded-xl bg-white p-5 border border-line">
                                <p class="text-sm font-medium text-muted">{{ $card['label'] }}</p>
                                <p class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $card['value'] }}</p>
                                <span class="mt-5 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $toneClass }}">{{ $card['caption'] }}</span>
                            </article>
                        @endforeach
                    </section>

                    <section
                        class="mt-6 rounded-xl bg-white border border-line"
                        aria-labelledby="customers-heading"
                        x-data='customerIndexTable(@json($customers))'
                    >
                        <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 id="customers-heading" class="font-semibold text-ink">Tabel pelanggan</h2>
                                <p class="mt-1 text-sm text-muted">Cari berdasarkan nama, email, kota, atau kode pelanggan.</p>
                                <p class="mt-2 text-xs font-medium text-brand-800" x-text="resultSummary"></p>
                            </div>
                            <div class="grid w-full gap-3 sm:grid-cols-[auto_11rem_minmax(14rem,1fr)_auto] xl:w-auto">
                                <div class="inline-flex max-w-full overflow-x-auto rounded-lg bg-canvas p-1 text-xs font-semibold text-muted" aria-label="Filter status pelanggan">
                                    <template x-for="filter in statusOptions" :key="filter.key">
                                        <button
                                            type="button"
                                            class="rounded-md px-2.5 py-1.5 hover:text-ink"
                                            :class="statusFilter === filter.key ? 'bg-white text-ink border border-line' : ''"
                                            :aria-pressed="statusFilter === filter.key"
                                            @click="setStatusFilter(filter.key)"
                                            x-text="filter.label"
                                        ></button>
                                    </template>
                                </div>
                                <select class="form-control text-xs font-semibold text-muted" x-model="segmentFilter" aria-label="Filter segmen pelanggan">
                                    <template x-for="option in segmentOptions" :key="option.key">
                                        <option :value="option.key" x-text="option.label"></option>
                                    </template>
                                </select>
                                <label class="relative block min-w-0">
                                    <span class="sr-only">Cari pelanggan</span>
                                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/>
                                    </svg>
                                    <input type="search" class="form-control pl-9" placeholder="Cari pelanggan, email, kota..." x-model.debounce.150ms="query">
                                </label>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-line bg-canvas px-3 py-2 text-xs font-semibold text-muted hover:bg-white hover:text-ink"
                                    x-show="isFiltered"
                                    x-cloak
                                    @click="resetFilters()"
                                >
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round"/>
                                    </svg>
                                    Reset
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1120px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3 sm:px-6">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('name')">
                                                Pelanggan
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('name')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3">Kontak</th>
                                        <th class="px-5 py-3">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('segment')">
                                                Segmen
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('segment')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3">Kota</th>
                                        <th class="px-5 py-3">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('lastOrderSort')">
                                                Transaksi terakhir
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('lastOrderSort')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3 text-right">
                                            <button type="button" class="ml-auto inline-flex items-center gap-1 hover:text-ink" @click="sortBy('totalSalesValue')">
                                                Total transaksi
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('totalSalesValue')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3 text-right">Outstanding</th>
                                        <th class="px-5 py-3 text-right">Status</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="customer in filteredCustomers" :key="customer.code">
                                        <tr class="hover:bg-brand-50/45">
                                            <td class="px-5 py-4 sm:px-6">
                                                <div class="flex items-center gap-3">
                                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800" x-text="customer.initials"></span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate font-semibold text-ink" x-text="customer.name"></span>
                                                        <span class="mt-0.5 block font-mono text-xs text-muted" x-text="customer.code"></span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-5 py-4 text-muted">
                                                <span class="block text-ink" x-text="customer.email"></span>
                                                <span class="mt-0.5 block text-xs" x-text="customer.phone"></span>
                                            </td>
                                            <td class="px-5 py-4">
                                                <span class="inline-flex rounded-full bg-canvas px-2.5 py-1 text-xs font-semibold text-muted" x-text="customer.segment"></span>
                                            </td>
                                            <td class="px-5 py-4 text-muted" x-text="customer.city"></td>
                                            <td class="px-5 py-4 text-muted" x-text="customer.lastOrder"></td>
                                            <td class="px-5 py-4 text-right font-semibold text-ink" x-text="customer.totalSales"></td>
                                            <td class="px-5 py-4 text-right text-muted" x-text="customer.outstanding"></td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(customer.status)" x-text="customer.status"></span>
                                            </td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <div class="flex justify-end gap-2">
                                                    <a
                                                        class="inline-flex items-center justify-center rounded-lg border border-line bg-white px-3 py-1.5 text-xs font-semibold text-ink hover:bg-brand-50 hover:text-brand-800"
                                                        :href="`/customers/${customer.code}`"
                                                    >
                                                        Detail
                                                    </a>
                                                    <a
                                                        class="inline-flex items-center justify-center rounded-lg border border-line bg-white px-3 py-1.5 text-xs font-semibold text-ink hover:bg-brand-50 hover:text-brand-800"
                                                        :href="`/customers/${customer.code}/edit`"
                                                    >
                                                        Edit
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredCustomers.length === 0">
                                        <td colspan="9" class="px-5 py-10 text-center text-sm text-muted sm:px-6">
                                            <p class="font-medium text-ink">Tidak ada pelanggan yang cocok dengan filter saat ini.</p>
                                            <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 hover:text-brand-900" @click="resetFilters()">
                                                Reset pencarian & filter
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-line px-5 py-4 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <span><strong class="font-semibold text-ink" x-text="filteredCustomers.length"></strong> pelanggan tampil dari {{ count($customers) }} data.</span>
                            <span>Total transaksi tampil: <strong class="font-semibold text-ink" x-text="visibleSalesFormatted"></strong></span>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
