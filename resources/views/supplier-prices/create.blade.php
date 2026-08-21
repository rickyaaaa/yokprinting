<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Tambah harga supplier baru YokPrinting.ID">
        <title>Tambah Harga Supplier - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="btn-icon mr-3 border-transparent lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex items-center gap-2 text-sm">
                        <a href="{{ route('supplier-prices.index') }}" class="hidden text-muted hover:text-ink sm:inline">Harga Supplier</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Tambah baru</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[820px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='supplierPriceFormPage(@json(["today" => now()->toDateString(), "indexUrl" => route("supplier-prices.index"), "supplierId" => request()->query("supplier_id", ""), "productId" => request()->query("product_id", "")]))'
                    x-init="init()"
                >
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="badge badge-brand">Pembelian</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Tambah Harga Supplier</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Setiap harga baru dari supplier dicatat sebagai entri terpisah - histori lama tetap tersimpan dan tidak berubah. Ini bukan harga PO, hanya referensi tawaran supplier.</p>
                        </div>
                        <a href="{{ route('supplier-prices.index') }}" class="btn btn-outline w-fit">
                            <i class="iconify tabler--chevron-left text-base"></i>
                            Kembali ke daftar
                        </a>
                    </div>

                    <div x-show="generalError" x-cloak class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" x-text="generalError" role="alert"></div>
                    <div x-show="loadingOptions" class="mb-4 card p-4 text-sm text-muted">Memuat data supplier dan produk...</div>

                    <form class="space-y-6" @submit.prevent="submit()" x-show="!loadingOptions">
                        <section class="card p-5 sm:p-6">
                            <h2 class="font-semibold text-ink">Detail harga</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Supplier</span>
                                    <select class="form-control" x-model="form.supplier_id" :aria-invalid="Boolean(errors.supplier_id)" @change="clearError('supplier_id')">
                                        <option value="">Pilih supplier</option>
                                        <template x-for="supplier in suppliers" :key="supplier.id">
                                            <option :value="supplier.id" x-text="`${supplier.code} - ${supplier.name}`"></option>
                                        </template>
                                    </select>
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.supplier_id" x-text="errors.supplier_id"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Produk</span>
                                    <select class="form-control" x-model="form.product_id" :aria-invalid="Boolean(errors.product_id)" @change="clearError('product_id')">
                                        <option value="">Pilih produk</option>
                                        <template x-for="product in products" :key="product.id">
                                            <option :value="product.id" x-text="`${product.sku} - ${product.name}`"></option>
                                        </template>
                                    </select>
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.product_id" x-text="errors.product_id"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Harga</span>
                                    <input type="number" min="0.01" step="0.01" class="form-control" x-model="form.price" @input="clearError('price')" placeholder="Contoh: 296">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.price" x-text="errors.price"></span>
                                </label>
                                <div></div>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Berlaku Mulai</span>
                                    <input type="date" class="form-control" x-model="form.valid_from" @input="clearError('valid_from')">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.valid_from" x-text="errors.valid_from"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Berlaku Sampai <span class="font-normal text-muted">(opsional - kosongkan jika belum ada batas)</span></span>
                                    <input type="date" class="form-control" x-model="form.valid_until" @input="clearError('valid_until')">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.valid_until" x-text="errors.valid_until"></span>
                                </label>
                                <label class="sm:col-span-2">
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Catatan <span class="font-normal text-muted">(opsional)</span></span>
                                    <textarea class="form-control min-h-20" x-model="form.notes" placeholder="Contoh: Harga berlaku 3 hari / Habiskan stok / Promo supplier"></textarea>
                                </label>
                            </div>
                        </section>

                        <div class="card p-5 sm:p-6">
                            <button
                                type="submit"
                                class="btn btn-primary w-full disabled:cursor-wait"
                                :disabled="saving"
                                x-text="saving ? 'Menyimpan...' : 'Simpan harga supplier'"
                            ></button>
                        </div>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
