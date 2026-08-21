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
                        <span class="truncate font-medium text-ink">Daftar piutang</span>
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
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar piutang</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau invoice outstanding, pembayaran parsial, dan prioritas follow-up pelanggan.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('payments.history.index') }}" class="btn btn-outline">
                                <i class="iconify tabler--clock text-base"></i>
                                Riwayat pembayaran
                            </a>
                            <button type="button" class="btn btn-outline">
                                <i class="iconify tabler--calendar text-base"></i>
                                {{ now(config('app.timezone'))->locale('id')->translatedFormat('F Y') }}
                            </button>
                            <a href="{{ route('notifications.due-invoices.index') }}" class="btn btn-primary">
                                Lihat prioritas reminder
                            </a>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan piutang">
                        @foreach ($summaryCards as $card)
                            @php
                                $toneClass = match ($card['tone']) {
                                    'success' => 'badge-success',
                                    'warning' => 'badge-warning',
                                    'danger' => 'badge-danger',
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
                                    <i class="iconify tabler--search pointer-events-none absolute left-3 top-1/2 text-base -translate-y-1/2 text-muted"></i>
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
                                                <span class="badge" :class="statusClass(receivable.status)" x-text="receivable.status"></span>
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
