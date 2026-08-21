<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Riwayat pembayaran YokPrinting.ID">

        <title>Riwayat Pembayaran - YokPrinting.ID</title>

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
                        <a href="{{ route('payments.receivables.index') }}" class="hidden text-muted hover:text-ink sm:inline">Pembayaran</a>
                        <i class="iconify tabler--chevron-right hidden text-sm text-line sm:block"></i>
                        <span class="truncate font-medium text-ink">Riwayat pembayaran</span>
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
                                <span class="badge badge-brand">Manajemen pembayaran</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Riwayat pembayaran</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Lihat transaksi pembayaran masuk, status verifikasi, dan referensi invoice terkait.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('payments.receivables.index') }}" class="btn btn-outline">
                                <i class="iconify tabler--chevron-left text-base"></i>
                                Daftar piutang
                            </a>
                            <button type="button" class="btn btn-primary">
                                <i class="iconify tabler--download text-base"></i>
                                Export
                            </button>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan riwayat pembayaran">
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
                        aria-labelledby="payment-history-page-heading"
                        x-data='paymentHistoryTable(@json($payments))'
                    >
                        <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 id="payment-history-page-heading" class="font-semibold text-ink">Tabel riwayat pembayaran</h2>
                                <p class="mt-1 text-sm text-muted">Transaksi pembayaran terbaru.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="inline-flex w-fit rounded-lg bg-canvas p-1 text-xs font-semibold text-muted" aria-label="Filter status riwayat pembayaran">
                                    <template x-for="filter in filters" :key="filter.key">
                                        <button
                                            type="button"
                                            class="rounded-md px-3 py-1.5 hover:text-ink"
                                            :class="statusFilter === filter.key ? 'bg-white text-ink border border-line' : ''"
                                            :aria-pressed="statusFilter === filter.key"
                                            @click="setStatusFilter(filter.key)"
                                            x-text="filter.label"
                                        ></button>
                                    </template>
                                </div>
                                <div class="relative">
                                    <select
                                        class="form-control text-xs font-semibold text-muted"
                                        x-model="methodFilter"
                                        aria-label="Filter metode pembayaran"
                                    >
                                        <template x-for="option in methodOptions" :key="option.key">
                                            <option :value="option.key" x-text="option.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <label class="relative block min-w-64">
                                    <span class="sr-only">Cari riwayat pembayaran</span>
                                    <i class="iconify tabler--search pointer-events-none absolute left-3 top-1/2 text-base -translate-y-1/2 text-muted"></i>
                                    <input type="search" class="form-control pl-9" placeholder="Cari pelanggan, invoice, referensi" x-model.debounce.150ms="query">
                                </label>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-canvas px-3 py-2 text-xs font-semibold text-muted hover:bg-white hover:text-ink"
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
                            <table class="w-full min-w-[980px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3 sm:px-6">Waktu</th>
                                        <th class="px-5 py-3">Invoice</th>
                                        <th class="px-5 py-3">Pelanggan</th>
                                        <th class="px-5 py-3">Metode</th>
                                        <th class="px-5 py-3">Referensi</th>
                                        <th class="px-5 py-3 text-right">Nominal</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="payment in filteredPayments" :key="`${payment.invoice}-${payment.reference}`">
                                        <tr class="hover:bg-brand-50/45">
                                            <td class="px-5 py-4 sm:px-6">
                                                <p class="font-medium text-ink" x-text="payment.date"></p>
                                                <p class="mt-0.5 text-xs text-muted" x-text="payment.time"></p>
                                            </td>
                                            <td class="px-5 py-4 font-mono text-xs font-semibold text-brand-800">
                                                <a :href="`/payments/invoices/${payment.invoice}`" class="hover:text-brand-900" x-text="payment.invoice"></a>
                                            </td>
                                            <td class="px-5 py-4 font-medium text-ink" x-text="payment.customer"></td>
                                            <td class="px-5 py-4 text-muted" x-text="payment.method"></td>
                                            <td class="px-5 py-4 font-mono text-xs text-muted" x-text="payment.reference"></td>
                                            <td class="px-5 py-4 text-right font-semibold text-ink" x-text="payment.amount"></td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <span class="badge" :class="statusClass(payment.status)" x-text="payment.status"></span>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredPayments.length === 0">
                                        <td colspan="7" class="px-5 py-10 text-center text-sm text-muted sm:px-6">
                                            <p class="font-medium text-ink">Tidak ada pembayaran yang cocok dengan filter saat ini.</p>
                                            <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 hover:text-brand-900" @click="resetFilters()">
                                                Reset pencarian & filter
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
