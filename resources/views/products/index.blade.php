<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Daftar produk YokPrinting.ID">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Data Produk - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" aria-hidden="true"></div>

            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden" @click="sidebarOpen = true" aria-controls="app-sidebar" :aria-expanded="sidebarOpen" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <svg class="hidden size-4 text-line sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate font-medium text-ink">Data produk</span>
                    </div>
                    <div class="ml-auto hidden text-sm text-muted sm:block">{{ now(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y') }}</div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Data produk</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar produk</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Kelola katalog produk cetak, harga beli, stok, dan performa transaksi. Harga jual diatur langsung di item invoice.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800" @click="$dispatch('open-product-bulk-stock')">
                                Bulk edit stok
                            </button>
                            <a href="{{ route('products.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                Tambah produk
                            </a>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan produk">
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
                        aria-labelledby="products-heading"
                        x-data='productIndexTable(@json($products))'
                        @open-product-bulk-stock.window="openBulkStockEditor()"
                    >
                        <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 id="products-heading" class="font-semibold text-ink">Tabel produk</h2>
                                <p class="mt-1 text-sm text-muted">Cari SKU, nama produk, kategori, atau status katalog.</p>
                                <p class="mt-2 text-xs font-medium text-muted" x-show="loading">Memuat katalog produk dari database…</p>
                                <p class="mt-2 text-xs font-medium text-red-700" x-show="error" x-text="error"></p>
                                <p class="mt-2 text-xs font-medium text-brand-800" x-text="resultSummary"></p>
                            </div>
                            <div class="grid w-full gap-3 sm:grid-cols-[auto_12rem_minmax(14rem,1fr)_auto] xl:w-auto">
                                <div class="inline-flex max-w-full overflow-x-auto rounded-lg bg-canvas p-1 text-xs font-semibold text-muted" aria-label="Filter status produk">
                                    <template x-for="filter in statusOptions" :key="filter.key">
                                        <button type="button" class="rounded-md px-2.5 py-1.5 hover:text-ink" :class="statusFilter === filter.key ? 'bg-white text-ink border border-line' : ''" :aria-pressed="statusFilter === filter.key" @click="setStatusFilter(filter.key)" x-text="filter.label"></button>
                                    </template>
                                </div>
                                <select class="form-control text-xs font-semibold text-muted" x-model="categoryFilter" aria-label="Filter kategori produk">
                                    <template x-for="option in categoryOptions" :key="option.key">
                                        <option :value="option.key" x-text="option.label"></option>
                                    </template>
                                </select>
                                <label class="relative block min-w-0">
                                    <span class="sr-only">Cari produk</span>
                                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke-linecap="round"/>
                                    </svg>
                                    <input type="search" class="form-control pl-9" placeholder="Cari produk, SKU, kategori..." x-model.debounce.150ms="query">
                                </label>
                                <button type="button" class="inline-flex items-center justify-center rounded-lg border border-line bg-canvas px-3 py-2 text-xs font-semibold text-muted hover:bg-white hover:text-ink" x-show="isFiltered" x-cloak @click="resetFilters()">Reset</button>
                            </div>
                        </div>

                        <div class="border-b border-line px-5 py-4 sm:px-6" x-show="lowStockProducts.length > 0">
                            <div class="flex flex-col gap-3 rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-950 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold">Penanda stok menipis aktif</p>
                                    <p class="mt-1 leading-6" x-text="lowStockSummary"></p>
                                </div>
                                <button type="button" class="inline-flex w-fit items-center rounded-lg bg-yellow-100 px-3 py-2 text-xs font-semibold text-yellow-950 hover:bg-yellow-200" @click="setStatusFilter('Stok menipis')">
                                    Lihat stok menipis
                                </button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1120px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3 sm:px-6">Produk</th>
                                        <th class="px-5 py-3">Kategori</th>
                                        <th class="px-5 py-3">Unit</th>
                                        <th class="px-5 py-3 text-right">HPP FIFO</th>
                                        <th class="px-5 py-3 text-right">Stok</th>
                                        <th class="px-5 py-3 text-right">Terjual</th>
                                        <th class="px-5 py-3 text-right">Status</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template x-for="product in filteredProducts" :key="product.sku">
                                        <tr class="hover:bg-brand-50/45" :class="isLowStock(product) ? 'bg-yellow-50/55' : ''">
                                            <td class="px-5 py-4 sm:px-6">
                                                <p class="font-semibold text-ink" x-text="product.name"></p>
                                                <p class="mt-0.5 font-mono text-xs text-muted" x-text="product.sku"></p>
                                            </td>
                                            <td class="px-5 py-4 text-muted" x-text="product.category"></td>
                                            <td class="px-5 py-4 text-muted" x-text="product.unit"></td>
                                            <td class="px-5 py-4 text-right font-semibold text-ink" x-text="product.purchasePrice"></td>
                                            <td class="px-5 py-4 text-right">
                                                <p class="font-medium" :class="isLowStock(product) ? 'text-yellow-900' : 'text-muted'" x-text="product.stock"></p>
                                                <p class="mt-1 text-xs text-muted" x-show="product.minimumStock > 0">Minimum <span x-text="product.minimumStock"></span> <span x-text="product.unit"></span></p>
                                                <span class="mt-2 inline-flex rounded-full bg-yellow-100 px-2 py-0.5 text-[0.7rem] font-semibold text-yellow-900" x-show="isLowStock(product)">
                                                    Di bawah minimum
                                                </span>
                                            </td>
                                            <td class="px-5 py-4 text-right text-muted" x-text="product.sales"></td>
                                            <td class="px-5 py-4 text-right">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(product.status)" x-text="product.status"></span>
                                            </td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <div class="flex justify-end gap-3">
                                                    <a :href="`/products/${product.id}/edit`" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Edit</a>
                                                    <button type="button" class="text-xs font-semibold text-red-700 hover:text-red-900" @click="deleteProduct(product)">Hapus</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredProducts.length === 0">
                                        <td colspan="8" class="px-5 py-10 text-center text-sm text-muted sm:px-6">
                                            <p class="font-medium text-ink">Tidak ada produk yang cocok dengan filter saat ini.</p>
                                            <button type="button" class="mt-3 text-xs font-semibold text-brand-700 hover:text-brand-900" @click="resetFilters()">Reset pencarian & filter</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-line px-5 py-4 text-sm text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <span><strong class="font-semibold text-ink" x-text="filteredProducts.length"></strong> produk tampil dari <strong class="font-semibold text-ink" x-text="products.length"></strong> data.</span>
                            <span>Nilai persediaan tampil: <strong class="font-semibold text-ink" x-text="visibleCatalogValueFormatted"></strong></span>
                        </div>

                        <div
                            x-show="bulkEditorOpen"
                            x-cloak
                            class="fixed inset-0 z-50 grid place-items-center bg-ink/45 p-4"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="bulk-stock-heading"
                            @keydown.escape.window="closeBulkStockEditor()"
                        >
                            <div class="w-full max-w-lg rounded-xl border border-line bg-white shadow-xl">
                                <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                                    <div>
                                        <h3 id="bulk-stock-heading" class="font-semibold text-ink">Bulk edit stok produk</h3>
                                        <p class="mt-1 text-sm text-muted">Terapkan perubahan ke produk yang sedang tampil dari filter saat ini.</p>
                                    </div>
                                    <button type="button" class="rounded-lg p-2 text-muted hover:bg-canvas hover:text-ink" @click="closeBulkStockEditor()" aria-label="Tutup bulk edit stok">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="space-y-4 px-5 py-5">
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Field yang diubah</span>
                                        <select class="form-control mt-1.5" x-model="bulkStockMode">
                                            <option value="stock">Stok fisik</option>
                                            <option value="minimum_stock">Minimum stok</option>
                                        </select>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nilai baru</span>
                                        <input type="number" min="0" class="form-control mt-1.5" x-model.number="bulkStockValue">
                                    </label>
                                    <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
                                        <p class="font-semibold" x-text="`${filteredProducts.length} produk akan diperbarui.`"></p>
                                        <p class="mt-1 text-xs leading-5">Kalau hanya mau sebagian, filter/search tabel dulu baru jalankan bulk edit.</p>
                                    </div>
                                    <p x-show="bulkStockError" x-text="bulkStockError" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"></p>
                                    <p x-show="bulkStockSuccess" x-text="bulkStockSuccess" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"></p>
                                </div>
                                <div class="flex flex-col-reverse gap-2 border-t border-line bg-canvas px-5 py-4 sm:flex-row sm:justify-end">
                                    <button type="button" class="rounded-lg border border-line bg-white px-4 py-2 text-sm font-semibold text-ink hover:bg-brand-50" @click="closeBulkStockEditor()" :disabled="bulkSaving">
                                        Batal
                                    </button>
                                    <button type="button" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-70" @click="applyBulkStock()" :disabled="bulkSaving || filteredProducts.length === 0">
                                        <span x-text="bulkSaving ? 'Menyimpan...' : 'Terapkan perubahan'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
