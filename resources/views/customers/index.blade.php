<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Indeks pelanggan YokPrinting.ID">
        <meta name="csrf-token" content="{{ csrf_token() }}">

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
                        <span class="truncate font-medium text-ink">Data pelanggan</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <x-notification-bell />
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">{{ now(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y') }}</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    @if (request('created'))
                        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900" role="status" data-testid="customer-created-notice">
                            <p class="font-semibold">Pelanggan berhasil ditambahkan.</p>
                            <p class="mt-1">{{ request('created') }} sekarang sudah tampil di daftar pelanggan.</p>
                        </div>
                    @endif

                    @if (request('deleted'))
                        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900" role="status" data-testid="customer-deleted-notice">
                            <p class="font-semibold">Pelanggan berhasil dihapus.</p>
                            <p class="mt-1">{{ request('deleted') }} sudah dikeluarkan dari daftar aktif. Invoice dan transaksi historis tetap tersimpan.</p>
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
                            <button type="button" class="btn btn-outline">
                                <i class="iconify tabler--calendar text-base"></i>
                                {{ now(config('app.timezone'))->locale('id')->translatedFormat('F Y') }}
                            </button>
                            <a href="{{ route('customers.create') }}" class="btn btn-primary">
                                <i class="iconify tabler--plus text-base"></i>
                                Tambah pelanggan
                            </a>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan pelanggan">
                        @foreach ($summaryCards as $card)
                            @php
                                $toneClass = match ($card['tone']) {
                                    'success' => 'badge-success',
                                    'warning' => 'badge-warning',
                                    default => 'badge-brand',
                                };
                            @endphp
                            <article class="card p-5">
                                <p class="text-sm font-medium text-muted">{{ $card['label'] }}</p>
                                <p class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $card['value'] }}</p>
                                <span class="badge mt-5 {{ $toneClass }}">{{ $card['caption'] }}</span>
                            </article>
                        @endforeach
                    </section>

                    <section
                        class="mt-6 card"
                        aria-labelledby="customers-heading"
                        x-data='customerIndexTable(@json($customers))'
                        @keydown.escape.window="closeDeleteModal()"
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
                                    <i class="iconify tabler--search pointer-events-none absolute left-3 top-1/2 text-base -translate-y-1/2 text-muted"></i>
                                    <input type="search" class="form-control pl-9" placeholder="Cari pelanggan, email, kota..." x-model.debounce.150ms="query">
                                </label>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-line bg-canvas px-3 py-2 text-xs font-semibold text-muted hover:bg-white hover:text-ink"
                                    x-show="isFiltered"
                                    x-cloak
                                    @click="resetFilters()"
                                >
                                    <i class="iconify tabler--x text-sm"></i>
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
                                                <span class="badge badge-default" x-text="customer.segment"></span>
                                            </td>
                                            <td class="px-5 py-4 text-muted" x-text="customer.city"></td>
                                            <td class="px-5 py-4 text-muted" x-text="customer.lastOrder"></td>
                                            <td class="px-5 py-4 text-right font-semibold text-ink" x-text="customer.totalSales"></td>
                                            <td class="px-5 py-4 text-right text-muted" x-text="customer.outstanding"></td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <span class="badge" :class="statusClass(customer.status)" x-text="customer.status"></span>
                                            </td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <div class="flex justify-end gap-2">
                                                    <a
                                                        class="btn btn-sm btn-outline"
                                                        :href="`/customers/${customer.code}`"
                                                    >
                                                        Detail
                                                    </a>
                                                    <a
                                                        class="btn btn-sm btn-outline"
                                                        :href="`/customers/${customer.code}/edit`"
                                                    >
                                                        Edit
                                                    </a>
                                                    @if ($canDeleteCustomer)
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-danger-outline"
                                                            @click="openDeleteModal(customer)"
                                                            :aria-label="`Hapus pelanggan ${customer.name}`"
                                                            data-testid="delete-customer-button"
                                                        >
                                                            Hapus
                                                        </button>
                                                    @endif
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

                        @if ($canDeleteCustomer)
                            <div
                                x-cloak
                                x-show="deleteModalOpen"
                                x-transition.opacity.duration.150ms
                                class="fixed inset-0 z-40 grid place-items-center bg-ink/55 p-4"
                                @click.self="closeDeleteModal()"
                                role="presentation"
                            >
                                <section
                                    x-show="deleteModalOpen"
                                    x-transition:enter="transition duration-200 ease-out"
                                    x-transition:enter-start="translate-y-2 opacity-0"
                                    x-transition:enter-end="translate-y-0 opacity-100"
                                    x-transition:leave="transition duration-150 ease-in"
                                    x-transition:leave-start="translate-y-0 opacity-100"
                                    x-transition:leave-end="translate-y-2 opacity-0"
                                    class="w-full max-w-md rounded-xl bg-white p-6 shadow-lg"
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="delete-customer-title"
                                    aria-describedby="delete-customer-description"
                                >
                                    <div class="flex items-start gap-4">
                                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-red-100 text-red-700" aria-hidden="true">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <h2 id="delete-customer-title" class="text-lg font-semibold text-ink">Hapus pelanggan?</h2>
                                            <p id="delete-customer-description" class="mt-2 text-sm leading-6 text-muted">
                                                <span class="font-semibold text-ink" x-text="deleteCandidate?.name"></span>
                                                akan dikeluarkan dari daftar aktif dan tidak dapat dipilih untuk invoice baru.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-5 rounded-lg bg-canvas p-4 text-sm leading-6 text-muted">
                                        <template x-if="(deleteCandidate?.invoiceCount ?? 0) > 0">
                                            <p>
                                                Pelanggan ini memiliki <strong class="font-semibold text-ink" x-text="deleteCandidate.invoiceCount"></strong>
                                                invoice. Invoice, pembayaran, dan relasi historis tidak akan dihapus.
                                            </p>
                                        </template>
                                        <template x-if="(deleteCandidate?.invoiceCount ?? 0) === 0">
                                            <p>Pelanggan ini belum memiliki invoice. Penghapusan tetap menggunakan soft delete.</p>
                                        </template>
                                    </div>

                                    <p x-show="deleteError" x-text="deleteError" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-800" role="alert"></p>

                                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-60"
                                            @click="closeDeleteModal()"
                                            :disabled="deleting"
                                        >
                                            Batal
                                        </button>
                                        <button
                                            x-ref="deleteConfirmButton"
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 disabled:cursor-wait disabled:opacity-70"
                                            @click="confirmDelete()"
                                            :disabled="deleting"
                                            data-testid="confirm-delete-customer"
                                        >
                                            <span x-text="deleting ? 'Menghapus…' : 'Ya, hapus pelanggan'"></span>
                                        </button>
                                    </div>
                                </section>
                            </div>
                        @endif
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
