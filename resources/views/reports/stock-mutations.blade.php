@php
    $reportConfig = [
        'endpoint' => route('api.reports.stock-movements.index'),
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'canExport' => $canExport,
        'products' => $products->map(fn ($product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
        ])->values(),
        'exportEndpoints' => [
            'excel' => route('api.reports.stock-mutations.excel'),
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mutasi per Barang - YokPrinting.ID</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
    <x-app-sidebar />
    <main class="min-w-0 flex-1" x-data='stockMutationReportPage(@json($reportConfig))' x-init="init()">
        <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
            <button type="button" class="btn-icon mr-3 border-transparent lg:hidden" @click="sidebarOpen = true" aria-controls="app-sidebar" :aria-expanded="sidebarOpen" aria-label="Buka navigasi">
                <i class="iconify tabler--align-left text-xl"></i>
            </button>
            <div class="flex min-w-0 items-center gap-2 text-sm">
                <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                <i class="iconify tabler--chevron-right hidden text-sm text-line sm:block"></i>
                <span class="truncate font-medium text-ink">Mutasi per Barang</span>
            </div>
            <div class="ml-auto hidden text-sm text-muted sm:block">{{ now()->translatedFormat('l, d F Y') }}</div>
        </header>

        <div class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <span class="badge badge-brand">Laporan persediaan</span>
                    <h1 class="mt-2 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Mutasi per Barang</h1>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Telusuri saldo berjalan setiap barang, termasuk dokumen, tanggal, dan customer atau supplier yang terkait.</p>
                </div>
                @if ($canExport)
                <div class="flex flex-wrap gap-2" x-show="report.product" x-cloak>
                    <button type="button" class="btn btn-primary" @click="exportFile('excel')" :aria-busy="exporting === 'excel'" aria-label="Export Excel mutasi barang">
                        <i class="iconify tabler--download text-base"></i>
                        <span x-text="exporting === 'excel' ? 'Menyiapkan...' : 'Export Excel'"></span>
                    </button>
                </div>
                @endif
            </div>

            <form class="card mb-6 p-4 sm:p-5" @submit.prevent="load()" aria-label="Filter mutasi per barang">
                <div class="grid gap-4 lg:grid-cols-[13rem_13rem_minmax(0,1fr)_auto] lg:items-end">
                    <label class="text-sm"><span class="mb-1.5 block font-semibold text-muted">Dari</span><input class="form-control" type="date" x-model="filters.start_date"></label>
                    <label class="text-sm"><span class="mb-1.5 block font-semibold text-muted">Sampai</span><input class="form-control" type="date" x-model="filters.end_date"></label>
                    <div class="relative text-sm" @click.outside="productMenuOpen = false">
                        <label for="stock-mutation-product" class="mb-1.5 block font-semibold text-muted">Barang &amp; Jasa <span class="text-danger">*</span></label>
                        <div class="relative">
                            <input id="stock-mutation-product" class="form-control pr-20" type="search" x-model="productSearch" @focus="productMenuOpen = true" @input="productMenuOpen = true" placeholder="Cari/Pilih..." autocomplete="off" role="combobox" :aria-expanded="productMenuOpen" aria-controls="stock-mutation-products">
                            <button x-show="filters.product_id" x-cloak type="button" class="absolute right-9 top-1/2 -translate-y-1/2 p-1 text-muted hover:text-ink" @click="clearProduct()" aria-label="Hapus barang terpilih"><i class="iconify tabler--x"></i></button>
                            <i class="iconify tabler--search pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-lg text-muted"></i>
                        </div>
                        <div id="stock-mutation-products" x-show="productMenuOpen && filteredProducts.length" x-cloak class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-line bg-white p-1 shadow-lg" role="listbox">
                            <template x-for="product in filteredProducts" :key="product.id">
                                <button type="button" class="flex w-full items-start gap-3 rounded-md px-3 py-2 text-left hover:bg-brand-50" @click="chooseProduct(product)">
                                    <span class="min-w-20 font-mono text-xs font-semibold text-brand-800" x-text="product.sku"></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate font-medium text-ink" x-text="product.name"></span><span class="block text-xs text-muted" x-text="product.unit"></span></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <button class="btn btn-primary min-h-11" type="submit" :disabled="loading"><i class="iconify tabler--list-search text-base"></i><span x-text="loading ? 'Memuat...' : 'Tampilkan'"></span></button>
                </div>
            </form>

            <p x-show="error" x-cloak class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></p>
            <div x-show="loading" x-cloak class="card px-5 py-12 text-center text-sm text-muted">Memuat mutasi barang...</div>

            <template x-if="report.product && !loading">
                <div>
                    <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan mutasi barang">
                        <article class="card p-5"><p class="text-sm font-medium text-muted">Saldo awal</p><p class="mt-2 text-2xl font-semibold text-ink" x-text="formatQuantity(report.detail_summary.opening_balance)"></p></article>
                        <article class="card p-5"><p class="text-sm font-medium text-muted">Barang masuk</p><p class="mt-2 text-2xl font-semibold text-emerald-700" x-text="formatQuantity(report.detail_summary.incoming)"></p></article>
                        <article class="card p-5"><p class="text-sm font-medium text-muted">Barang keluar</p><p class="mt-2 text-2xl font-semibold text-rose-700" x-text="formatQuantity(report.detail_summary.outgoing)"></p></article>
                        <article class="card p-5"><p class="text-sm font-medium text-muted">Saldo akhir</p><p class="mt-2 text-2xl font-semibold text-brand-800" x-text="formatQuantity(report.detail_summary.closing_balance)"></p></article>
                    </section>

                    <section
                        class="mb-6 rounded-xl border px-5 py-4 text-sm"
                        :class="report.detail_summary.reconciliation_status === 'needs_reconciliation' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
                        aria-live="polite"
                    >
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold" x-text="reconciliationLabel(report.detail_summary.reconciliation_status)"></p>
                                <p class="mt-1" x-show="report.detail_summary.reconciliation_status !== 'not_applicable'">
                                    Saldo ledger: <strong x-text="formatQuantity(report.detail_summary.ledger_stock)"></strong>
                                    · Stok produk: <strong x-text="formatQuantity(report.detail_summary.product_stock)"></strong>
                                    · Selisih: <strong x-text="formatQuantity(report.detail_summary.difference)"></strong>
                                </p>
                            </div>
                            <span class="text-xs" x-show="report.detail_summary.reconciliation_status === 'needs_reconciliation'">Gunakan Stock Adjustment resmi untuk koreksi; histori ledger tidak dihapus.</span>
                        </div>
                    </section>

                    <section class="card overflow-hidden" aria-labelledby="mutation-table-heading">
                        <div class="flex flex-col gap-2 border-b border-line bg-surface-low px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div><h2 id="mutation-table-heading" class="font-semibold text-ink" x-text="report.product.name"></h2><p class="mt-1 text-sm text-muted"><span class="font-mono" x-text="report.product.sku"></span> · Saldo berjalan per transaksi</p></div>
                            <span class="badge badge-brand" x-text="`${report.mutations.length} baris mutasi`"></span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[920px] text-left text-sm">
                                <thead><tr class="border-b border-line text-xs font-semibold text-muted"><th class="px-5 py-3 sm:px-6">Nomor dokumen</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Deskripsi</th><th class="px-5 py-3">Customer / Supplier</th><th class="px-5 py-3 text-right">Masuk</th><th class="px-5 py-3 text-right">Keluar</th><th class="px-5 py-3 text-right sm:px-6">Saldo</th></tr></thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="row in report.mutations" :key="row.id">
                                        <tr class="hover:bg-brand-50/45" :class="row.type === 'opening_balance' ? 'bg-surface-low' : ''">
                                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs font-semibold text-brand-800 sm:px-6" x-text="row.document_number || '-' "></td>
                                            <td class="whitespace-nowrap px-5 py-3 text-muted" x-text="formatDate(row.date)"></td>
                                            <td class="min-w-64 px-5 py-3 text-ink" x-text="row.description"></td>
                                            <td class="px-5 py-3 text-muted" x-text="row.party || '-' "></td>
                                            <td class="px-5 py-3 text-right font-medium text-emerald-700" x-text="row.incoming ? formatQuantity(row.incoming) : '-' "></td>
                                            <td class="px-5 py-3 text-right font-medium text-rose-700" x-text="row.outgoing ? formatQuantity(row.outgoing) : '-' "></td>
                                            <td class="px-5 py-3 text-right font-semibold text-ink sm:px-6" x-text="formatQuantity(row.balance)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </template>

            <div x-show="!loading && !report.product && !error" class="card px-5 py-16 text-center">
                <i class="iconify tabler--package-search mx-auto text-4xl text-brand-500"></i>
                <h2 class="mt-4 font-semibold text-ink">Pilih barang untuk melihat mutasinya</h2>
                <p class="mx-auto mt-1 max-w-md text-sm leading-6 text-muted">Atur rentang tanggal, pilih barang, lalu tampilkan untuk melihat siapa customer atau supplier-nya pada setiap transaksi.</p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
