@php
    $periodPresets = \App\Support\SalesReportPeriodPresets::current();
    $reportToday = \App\Support\SalesReportPeriodPresets::today();
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Laporan penjualan YokPrinting.ID">

        <title>Laporan Penjualan - YokPrinting.ID</title>

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

            <div class="min-w-0 flex-1" x-data='salesReportTable(@json($periodPresets))'>
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
                        <span class="truncate font-medium text-ink">Laporan penjualan</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <x-notification-bell />
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">{{ $reportToday->translatedFormat('l, d F Y') }}</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="badge badge-brand">Laporan penjualan</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Ringkasan penjualan</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Analisis performa penjualan, invoice, margin, dan komposisi produk dalam periode berjalan.</p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                            <select
                                class="form-control text-sm font-semibold text-ink sm:w-44"
                                x-model="periodPreset"
                                @change="selectPeriod($event.target.value)"
                                aria-label="Pilih periode laporan"
                            >
                                <option value="weekly">Mingguan</option>
                                <option value="monthly">Bulanan</option>
                                <option value="yearly">Tahunan</option>
                                <option value="custom">Rentang kustom...</option>
                            </select>
                            <button
                                type="button"
                                class="btn btn-primary disabled:opacity-50"
                                @click="exportExcel()"
                                :disabled="exporting"
                            >
                                <i class="iconify tabler--download text-base"></i>
                                <span x-text="exporting ? 'Mengunduh...' : 'Export Excel'">Export Excel</span>
                            </button>
                        </div>
                    </div>

                    <div x-show="exportSuccess" x-cloak class="mb-6 flex flex-col gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <i class="iconify tabler--circle-check text-lg text-green-600"></i>
                            <span>Laporan penjualan berhasil diunduh sebagai file CSV/Excel.</span>
                        </div>
                        <button type="button" @click="exportSuccess = false" class="text-green-700 hover:text-green-900 font-semibold">Tutup</button>
                    </div>

                    <div x-show="showCustomDate" x-cloak class="mb-6 flex flex-col gap-4 rounded-xl border border-line bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                        <div class="flex flex-col gap-2 text-sm text-muted sm:flex-row sm:items-center">
                            <span>Dari:</span>
                            <input type="date" class="form-control text-xs sm:w-40" x-model="startDate" @change="loadReport()">
                        </div>
                        <div class="flex flex-col gap-2 text-sm text-muted sm:flex-row sm:items-center">
                            <span>Sampai:</span>
                            <input type="date" class="form-control text-xs sm:w-40" x-model="endDate" @change="loadReport()">
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan laporan penjualan">
                        <template x-for="card in summaryCards" :key="card.key">
                            <article class="card p-5">
                                <p class="text-sm font-medium text-muted" x-text="card.label"></p>
                                <p class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink" x-text="card.value_formatted"></p>
                                <span class="badge mt-5" :class="summaryToneClass(card.tone)" x-text="card.caption"></span>
                            </article>
                        </template>
                    </section>

                    <p x-show="loadError" x-cloak class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="loadError"></p>

                    <section class="mt-6 card p-4 sm:p-6" x-data="salesReportChart" aria-labelledby="sales-chart-heading">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 id="sales-chart-heading" class="font-semibold text-ink">Grafik tren pendapatan & target</h2>
                                <p class="mt-1 text-sm text-muted">Perbandingan realisasi pendapatan dengan target penjualan.</p>
                            </div>
                            <div class="inline-flex max-w-full overflow-x-auto rounded-lg bg-canvas p-1 text-xs font-semibold text-muted">
                                <template x-for="period in periods" :key="period.key">
                                    <button
                                        type="button"
                                        class="rounded-md px-3 py-1.5 hover:text-ink"
                                        :class="selectedPeriod === period.key ? 'bg-white text-ink border border-line' : ''"
                                        @click="selectPeriod(period.key)"
                                        x-text="period.label"
                                    ></button>
                                </template>
                            </div>
                        </div>
                        <div class="mt-5 h-64 w-full sm:h-72 lg:h-80">
                            <canvas x-ref="salesChart" aria-label="Grafik tren pendapatan dan target penjualan"></canvas>
                        </div>
                        <p x-show="loadError" x-cloak class="mt-3 text-sm text-red-700" x-text="loadError"></p>
                    </section>

                    <div class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                        <section class="card" aria-labelledby="sales-table-heading">
                            <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                                <div>
                                    <h2 id="sales-table-heading" class="font-semibold text-ink">Tabel penjualan</h2>
                                    <p class="mt-1 text-sm text-muted">Filter berdasarkan status, kategori, atau pencarian nama pelanggan & invoice.</p>
                                </div>
                                <div class="grid w-full gap-3 sm:grid-cols-[minmax(min-content,auto)_11rem_auto_minmax(14rem,1fr)_auto] xl:w-auto">
                                    <div class="inline-flex max-w-full overflow-x-auto rounded-lg bg-canvas p-1 text-xs font-semibold text-muted" aria-label="Filter status penjualan">
                                        <template x-for="option in statusOptions" :key="option.key">
                                            <button
                                                type="button"
                                                class="rounded-md px-2.5 py-1.5 hover:text-ink"
                                                :class="statusFilter === option.key ? 'bg-white text-ink border border-line' : ''"
                                                :aria-pressed="statusFilter === option.key"
                                                @click="setStatusFilter(option.key)"
                                                x-text="option.label"
                                            ></button>
                                        </template>
                                    </div>
                                    <select
                                        class="form-control text-xs font-semibold text-muted"
                                        x-model="categoryFilter"
                                        aria-label="Filter kategori produk"
                                    >
                                        <template x-for="option in categoryOptions" :key="option.key">
                                            <option :value="option.key" x-text="option.label"></option>
                                        </template>
                                    </select>
                                    <select
                                        class="form-control text-xs font-semibold text-muted"
                                        x-model="sortDirection"
                                        aria-label="Urutan tanggal penjualan"
                                    >
                                        <template x-for="option in sortOptions" :key="option.key">
                                            <option :value="option.key" x-text="option.label"></option>
                                        </template>
                                    </select>
                                    <label class="relative block min-w-0">
                                        <span class="sr-only">Cari penjualan</span>
                                        <i class="iconify tabler--search pointer-events-none absolute left-3 top-1/2 text-base -translate-y-1/2 text-muted"></i>
                                        <input type="search" class="form-control pl-9" placeholder="Cari pelanggan, produk, invoice..." x-model.debounce.150ms="query">
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
                                <table class="w-full min-w-[900px] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-xs font-semibold text-muted">
                                            <th class="px-5 py-3 sm:px-6">Pelanggan</th>
                                            <th class="px-5 py-3">Produk</th>
                                            <th class="px-5 py-3">Invoice</th>
                                            <th class="px-5 py-3">Tanggal</th>
                                            <th class="px-5 py-3 text-right">Penjualan</th>
                                            <th class="px-5 py-3 text-right">Margin</th>
                                            <th class="px-5 py-3 text-right sm:px-6">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        <template x-for="row in filteredSales" :key="row.invoice">
                                            <tr class="hover:bg-brand-50/45">
                                                <td class="px-5 py-4 font-medium text-ink sm:px-6" x-text="row.customer"></td>
                                                <td class="px-5 py-4 text-muted">
                                                    <span x-text="row.product"></span>
                                                    <span class="block text-xs text-muted/70" x-text="row.category"></span>
                                                </td>
                                                <td class="px-5 py-4 font-mono text-xs font-semibold text-brand-800" x-text="row.invoice"></td>
                                                <td class="px-5 py-4 text-muted" x-text="row.date"></td>
                                                <td class="px-5 py-4 text-right font-semibold text-ink" x-text="row.amount"></td>
                                                <td class="px-5 py-4 text-right text-muted" x-text="row.margin"></td>
                                                <td class="px-5 py-4 text-right sm:px-6">
                                                    <span class="badge" :class="statusClass(row.status)" x-text="row.status"></span>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="filteredSales.length === 0">
                                            <td colspan="7" class="px-5 py-10 text-center text-sm text-muted sm:px-6">
                                                <p class="font-medium text-ink">Tidak ada data penjualan yang cocok dengan filter saat ini.</p>
                                                <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-brand-700 hover:text-brand-900" @click="resetFilters()">
                                                    Reset pencarian & filter
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <aside class="space-y-6">
                            <section class="card p-5 sm:p-6" aria-labelledby="product-mix-heading">
                                <h2 id="product-mix-heading" class="font-semibold text-ink">Komposisi produk</h2>
                                <p class="mt-1 text-sm text-muted">Kontribusi penjualan per kategori.</p>
                                <div class="mt-5 space-y-4">
                                    <template x-for="segment in productMix" :key="segment.label">
                                        <div>
                                            <div class="flex items-center justify-between gap-3 text-sm">
                                                <span class="font-medium text-ink" x-text="segment.label"></span>
                                                <span class="text-muted" x-text="segment.value"></span>
                                            </div>
                                            <div class="mt-2 h-2 rounded-full bg-canvas">
                                                <div class="h-2 rounded-full" :class="segment.class" :style="`width: ${segment.value}`"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </section>

                            <section class="rounded-xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-900">
                                <h2 class="font-semibold">Catatan analisis</h2>
                                <p class="mt-3 leading-6">Gunakan filter periode, status, dan kategori untuk membaca pola penjualan dari invoice tersimpan.</p>
                            </section>
                        </aside>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
