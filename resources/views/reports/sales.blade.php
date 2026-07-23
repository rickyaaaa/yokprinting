@php
    $summaryCards = [
        ['label' => 'Total penjualan', 'value' => 'Rp312.400.000', 'caption' => '+21% dari Juni', 'tone' => 'success'],
        ['label' => 'Invoice terbit', 'value' => '48', 'caption' => '42 sudah dikirim', 'tone' => 'brand'],
        ['label' => 'Rata-rata invoice', 'value' => 'Rp6.508.000', 'caption' => 'Per transaksi', 'tone' => 'brand'],
        ['label' => 'Outstanding', 'value' => 'Rp74.850.000', 'caption' => 'Perlu follow-up', 'tone' => 'warning'],
    ];

    $salesRows = [
        ['customer' => 'PT Sinar Nusantara', 'product' => 'Brand refresh + katalog', 'category' => 'Jasa desain', 'invoice' => 'INV-2026-0084', 'date' => '23 Jul 2026', 'rawDate' => '2026-07-23', 'amount' => 'Rp18.450.000', 'rawAmount' => 18450000, 'margin' => '34%', 'status' => 'Menunggu'],
        ['customer' => 'CV Lautan Rasa', 'product' => 'Packaging dan materi promosi', 'category' => 'Materi promosi', 'invoice' => 'INV-2026-0082', 'date' => '20 Jul 2026', 'rawDate' => '2026-07-20', 'amount' => 'Rp12.750.000', 'rawAmount' => 12750000, 'margin' => '29%', 'status' => 'Lunas'],
        ['customer' => 'PT Cakra Media', 'product' => 'Cetak katalog premium', 'category' => 'Cetak premium', 'invoice' => 'INV-2026-0076', 'date' => '08 Jul 2026', 'rawDate' => '2026-07-08', 'amount' => 'Rp14.800.000', 'rawAmount' => 14800000, 'margin' => '31%', 'status' => 'Parsial'],
        ['customer' => 'PT Bumi Lestari', 'product' => 'Company profile', 'category' => 'Jasa desain', 'invoice' => 'INV-2026-0078', 'date' => '10 Jul 2026', 'rawDate' => '2026-07-10', 'amount' => 'Rp5.600.000', 'rawAmount' => 5600000, 'margin' => '24%', 'status' => 'Overdue'],
        ['customer' => 'UD Sumber Makmur', 'product' => 'Flyer promosi bulanan', 'category' => 'Materi promosi', 'invoice' => 'INV-2026-0072', 'date' => '02 Jul 2026', 'rawDate' => '2026-07-02', 'amount' => 'Rp7.900.000', 'rawAmount' => 7900000, 'margin' => '27%', 'status' => 'Overdue'],
    ];

    $productMix = [
        ['label' => 'Jasa desain', 'value' => '42%', 'class' => 'bg-brand-600'],
        ['label' => 'Cetak premium', 'value' => '36%', 'class' => 'bg-accent'],
        ['label' => 'Materi promosi', 'value' => '22%', 'class' => 'bg-yellow-500'],
    ];
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

            <div class="min-w-0 flex-1" x-data="salesReportTable(@json($salesRows))">
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
                        <span class="truncate font-medium text-ink">Laporan penjualan</span>
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
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Laporan penjualan</span>
                                <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent">Data tiruan</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Ringkasan penjualan</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Analisis performa penjualan, invoice, margin, dan komposisi produk dalam periode berjalan.</p>
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                            <select
                                class="form-control text-sm font-semibold text-ink sm:w-44"
                                :value="periodPreset"
                                @change="selectPeriod($event.target.value)"
                                aria-label="Pilih periode laporan"
                            >
                                <template x-for="option in periodOptions" :key="option.key">
                                    <option :value="option.key" x-text="option.label"></option>
                                </template>
                            </select>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-50"
                                @click="exportExcel()"
                                :disabled="exporting"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 20h16M8 16V8M12 16V4M16 16v-5" stroke-linecap="round"/>
                                </svg>
                                <span x-text="exporting ? 'Mengunduh...' : 'Export Excel'"></span>
                            </button>
                        </div>
                    </div>

                    <div x-show="exportSuccess" x-cloak class="mb-6 flex flex-col gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="size-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M22 4L12 14.01l-3-3" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Laporan penjualan berhasil diunduh sebagai file CSV/Excel.</span>
                        </div>
                        <button type="button" @click="exportSuccess = false" class="text-green-700 hover:text-green-900 font-semibold">Tutup</button>
                    </div>

                    <div x-show="showCustomDate" x-cloak class="mb-6 flex flex-col gap-4 rounded-xl border border-line bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                        <div class="flex flex-col gap-2 text-sm text-muted sm:flex-row sm:items-center">
                            <span>Dari:</span>
                            <input type="date" class="form-control text-xs sm:w-40" x-model="startDate">
                        </div>
                        <div class="flex flex-col gap-2 text-sm text-muted sm:flex-row sm:items-center">
                            <span>Sampai:</span>
                            <input type="date" class="form-control text-xs sm:w-40" x-model="endDate">
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan laporan penjualan">
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

                    <section class="mt-6 rounded-xl bg-white p-4 border border-line sm:p-6" x-data="salesReportChart" aria-labelledby="sales-chart-heading">
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
                    </section>

                    <div class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                        <section class="rounded-xl bg-white border border-line" aria-labelledby="sales-table-heading">
                            <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                                <div>
                                    <h2 id="sales-table-heading" class="font-semibold text-ink">Tabel penjualan</h2>
                                    <p class="mt-1 text-sm text-muted">Filter berdasarkan status, kategori, atau pencarian nama pelanggan & invoice.</p>
                                </div>
                                <div class="grid w-full gap-3 sm:grid-cols-[auto_11rem_minmax(14rem,1fr)_auto] xl:w-auto">
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
                                    <label class="relative block min-w-0">
                                        <span class="sr-only">Cari penjualan</span>
                                        <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/>
                                        </svg>
                                        <input type="search" class="form-control pl-9" placeholder="Cari pelanggan, produk, invoice..." x-model.debounce.150ms="query">
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
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(row.status)" x-text="row.status"></span>
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
                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="product-mix-heading">
                                <h2 id="product-mix-heading" class="font-semibold text-ink">Komposisi produk</h2>
                                <p class="mt-1 text-sm text-muted">Kontribusi penjualan per kategori.</p>
                                <div class="mt-5 space-y-4">
                                    @foreach ($productMix as $segment)
                                        <div>
                                            <div class="flex items-center justify-between gap-3 text-sm">
                                                <span class="font-medium text-ink">{{ $segment['label'] }}</span>
                                                <span class="text-muted">{{ $segment['value'] }}</span>
                                            </div>
                                            <div class="mt-2 h-2 rounded-full bg-canvas">
                                                <div class="h-2 rounded-full {{ $segment['class'] }}" style="width: {{ $segment['value'] }}"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>

                            <section class="rounded-xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-900">
                                <h2 class="font-semibold">Catatan analisis</h2>
                                <p class="mt-3 leading-6">Penjualan bulan ini didorong oleh paket desain dan cetak premium. Invoice overdue tetap perlu diprioritaskan agar cashflow tidak tersendat.</p>
                            </section>
                        </aside>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
