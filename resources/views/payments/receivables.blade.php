@php
    $summaryCards = [
        ['label' => 'Total piutang', 'value' => 'Rp74.850.000', 'caption' => '18 invoice aktif', 'tone' => 'brand'],
        ['label' => 'Belum jatuh tempo', 'value' => 'Rp42.900.000', 'caption' => '10 invoice', 'tone' => 'success'],
        ['label' => 'Jatuh tempo 7 hari', 'value' => 'Rp18.250.000', 'caption' => '5 invoice', 'tone' => 'warning'],
        ['label' => 'Overdue', 'value' => 'Rp13.700.000', 'caption' => '3 invoice', 'tone' => 'danger'],
    ];

    $receivables = [
        ['invoice' => 'INV-2026-0084', 'customer' => 'PT Sinar Nusantara', 'issued' => '23 Jul 2026', 'due' => '30 Jul 2026', 'dueSort' => 20260730, 'total' => 'Rp18.450.000', 'paid' => 'Rp12.000.000', 'outstanding' => 'Rp6.450.000', 'outstandingValue' => 6450000, 'status' => 'Menunggu'],
        ['invoice' => 'INV-2026-0082', 'customer' => 'CV Lautan Rasa', 'issued' => '20 Jul 2026', 'due' => '2 Agu 2026', 'dueSort' => 20260802, 'total' => 'Rp12.750.000', 'paid' => 'Rp0', 'outstanding' => 'Rp12.750.000', 'outstandingValue' => 12750000, 'status' => 'Menunggu'],
        ['invoice' => 'INV-2026-0078', 'customer' => 'PT Bumi Lestari', 'issued' => '10 Jul 2026', 'due' => '20 Jul 2026', 'dueSort' => 20260720, 'total' => 'Rp5.600.000', 'paid' => 'Rp0', 'outstanding' => 'Rp5.600.000', 'outstandingValue' => 5600000, 'status' => 'Overdue'],
        ['invoice' => 'INV-2026-0076', 'customer' => 'PT Cakra Media', 'issued' => '8 Jul 2026', 'due' => '22 Jul 2026', 'dueSort' => 20260722, 'total' => 'Rp14.800.000', 'paid' => 'Rp4.250.000', 'outstanding' => 'Rp10.550.000', 'outstandingValue' => 10550000, 'status' => 'Parsial'],
        ['invoice' => 'INV-2026-0072', 'customer' => 'UD Sumber Makmur', 'issued' => '2 Jul 2026', 'due' => '16 Jul 2026', 'dueSort' => 20260716, 'total' => 'Rp7.900.000', 'paid' => 'Rp0', 'outstanding' => 'Rp7.900.000', 'outstandingValue' => 7900000, 'status' => 'Overdue'],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Daftar piutang YokPrinting.ID">

        <title>Daftar Piutang - YokPrinting.ID</title>

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
                        <span class="truncate font-medium text-ink">Daftar piutang</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" class="relative rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800" aria-label="Lihat notifikasi">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">Kamis, 23 Juli 2026</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Manajemen pembayaran</span>
                                <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent">Data tiruan</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar piutang</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau invoice outstanding, pembayaran parsial, dan prioritas follow-up pelanggan.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('payments.history.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M12 8v5l3 2M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Riwayat pembayaran
                            </a>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 7h16M7 4v6M17 4v6M6 12h12v8H6z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Juli 2026
                            </button>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 5h16v14H4zM4 7l8 6 8-6" stroke-linejoin="round"/>
                                </svg>
                                Kirim pengingat
                            </button>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan piutang">
                        @foreach ($summaryCards as $card)
                            @php
                                $toneClass = match ($card['tone']) {
                                    'success' => 'bg-green-100 text-green-800',
                                    'warning' => 'bg-yellow-100 text-yellow-900',
                                    'danger' => 'bg-red-100 text-red-800',
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
                        aria-labelledby="receivables-heading"
                        x-data='receivablesTable(@json($receivables))'
                    >
                        <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 id="receivables-heading" class="font-semibold text-ink">Tabel piutang</h2>
                                <p class="mt-1 text-sm text-muted">Daftar invoice yang masih memiliki outstanding.</p>
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <div class="inline-flex w-fit rounded-lg bg-canvas p-1 text-xs font-semibold text-muted" aria-label="Filter status piutang">
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
                                <label class="relative block min-w-64">
                                    <span class="sr-only">Cari piutang</span>
                                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/>
                                    </svg>
                                    <input type="search" class="form-control pl-9" placeholder="Cari pelanggan atau invoice" x-model.debounce.150ms="query">
                                </label>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[980px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3 sm:px-6">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('invoice')">
                                                Invoice
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('invoice')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('customer')">
                                                Pelanggan
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('customer')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3">Tanggal</th>
                                        <th class="px-5 py-3">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('dueSort')">
                                                Jatuh tempo
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('dueSort')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3 text-right">Total</th>
                                        <th class="px-5 py-3 text-right">Dibayar</th>
                                        <th class="px-5 py-3 text-right">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('outstandingValue')">
                                                Piutang
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('outstandingValue')"></span>
                                            </button>
                                        </th>
                                        <th class="px-5 py-3 text-right sm:px-6">
                                            <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('status')">
                                                Status
                                                <span class="font-mono text-[0.65rem]" x-text="sortIndicator('status')"></span>
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="receivable in filteredReceivables" :key="receivable.invoice">
                                        <tr class="hover:bg-brand-50/45">
                                            <td class="px-5 py-4 font-mono text-xs font-semibold text-brand-800 sm:px-6">
                                                <a :href="`/payments/invoices/${receivable.invoice}`" class="hover:text-brand-900" x-text="receivable.invoice"></a>
                                            </td>
                                            <td class="px-5 py-4 font-medium text-ink" x-text="receivable.customer"></td>
                                            <td class="px-5 py-4 text-muted" x-text="receivable.issued"></td>
                                            <td class="px-5 py-4 text-muted" x-text="receivable.due"></td>
                                            <td class="px-5 py-4 text-right font-medium text-ink" x-text="receivable.total"></td>
                                            <td class="px-5 py-4 text-right text-muted" x-text="receivable.paid"></td>
                                            <td class="px-5 py-4 text-right font-semibold text-ink" x-text="receivable.outstanding"></td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(receivable.status)" x-text="receivable.status"></span>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredReceivables.length === 0">
                                        <td colspan="8" class="px-5 py-10 text-center text-sm text-muted sm:px-6">
                                            Tidak ada piutang yang cocok dengan filter saat ini.
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
