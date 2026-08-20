<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Buat Purchase Order baru YokPrinting.ID">
        <title>Buat Purchase Order - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/></svg>
                    </button>
                    <div class="flex items-center gap-2 text-sm">
                        <a href="{{ route('purchase-orders.index') }}" class="hidden text-muted hover:text-ink sm:inline">Purchase Order</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Buat baru</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[1180px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='purchaseOrderFormPage(@json(["today" => now()->toDateString(), "indexUrl" => route("purchase-orders.index")]))'
                    x-init="init()"
                >
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Pembelian</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Buat Purchase Order</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">PO tersimpan sebagai draft dulu - harga per barang terkunci begitu disimpan dan tidak berubah lagi walau harga master barang berubah.</p>
                        </div>
                        <a href="{{ route('purchase-orders.index') }}" class="inline-flex w-fit items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Kembali ke daftar
                        </a>
                    </div>

                    <div x-show="generalError" x-cloak class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" x-text="generalError" role="alert"></div>
                    <div x-show="loadingOptions" class="mb-4 rounded-xl border border-line bg-white p-4 text-sm text-muted">Memuat data supplier dan barang...</div>

                    <form class="space-y-6" @submit.prevent="submit()" x-show="!loadingOptions">
                        <section class="rounded-xl border border-line bg-white p-5 sm:p-6">
                            <h2 class="font-semibold text-ink">Informasi PO</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Supplier</span>
                                    <select class="form-control" x-model="form.supplier_id" :aria-invalid="Boolean(errors.supplier_id)" @change="clearError('supplier_id'); supplierChanged()">
                                        <option value="">Pilih supplier</option>
                                        <template x-for="supplier in suppliers" :key="supplier.id">
                                            <option :value="supplier.id" x-text="`${supplier.code} - ${supplier.name}`"></option>
                                        </template>
                                    </select>
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.supplier_id" x-text="errors.supplier_id"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Tanggal PO</span>
                                    <input type="date" class="form-control" x-model="form.order_date" @input="clearError('order_date')">
                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors.order_date" x-text="errors.order_date"></span>
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Perkiraan tiba <span class="font-normal text-muted">(opsional)</span></span>
                                    <input type="date" class="form-control" x-model="form.expected_date">
                                </label>
                            </div>
                        </section>

                        <section class="rounded-xl border border-line bg-white">
                            <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                                <h2 class="font-semibold text-ink">Daftar barang</h2>
                                <button type="button" class="rounded-lg border border-line px-3 py-2 text-xs font-semibold text-ink hover:bg-canvas" @click="addItem()">+ Tambah baris</button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[720px] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-xs font-semibold text-muted">
                                            <th class="px-5 py-3 sm:px-6">Barang</th>
                                            <th class="px-4 py-3">Qty</th>
                                            <th class="px-4 py-3">Harga satuan</th>
                                            <th class="px-4 py-3 text-right">Subtotal</th>
                                            <th class="px-4 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        <template x-for="(item, index) in items" :key="index">
                                            <tr>
                                                <td class="relative px-5 py-3 sm:px-6" @click.outside="item.productPickerOpen = false">
                                                    <input class="form-control" type="search" placeholder="Ketik nama atau SKU barang..." x-model="item.productSearch" @focus="item.productPickerOpen = true" @input="item.productPickerOpen = true; item.product_id = ''">
                                                    <div x-show="item.productPickerOpen" x-cloak class="absolute left-5 right-5 top-[calc(100%-0.25rem)] z-20 max-h-64 overflow-y-auto rounded-lg border border-line bg-white p-1 shadow-lg sm:left-6 sm:right-6">
                                                        <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-xs text-muted hover:bg-brand-50" x-show="filteredProducts(item).length === 0">Produk tidak ditemukan.</button>
                                                        <template x-for="product in filteredProducts(item)" :key="product.id">
                                                            <button type="button" class="block w-full rounded-md px-3 py-2 text-left hover:bg-brand-50" @click="selectProduct(index, product)">
                                                                <span class="block truncate text-sm font-semibold text-ink" x-text="`${product.sku} - ${product.name}`"></span>
                                                                <span class="block truncate text-xs text-muted" x-text="product.category || 'Tanpa kategori'"></span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                    <input type="hidden" :name="`items.${index}.product_id`" :value="item.product_id">
                                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors[`items.${index}.product_id`]" x-text="errors[`items.${index}.product_id`]"></span>

                                                    <div x-show="item.priceReferenceLoading" class="mt-2 text-xs text-muted">Memeriksa harga supplier...</div>

                                                    <template x-if="!item.priceReferenceLoading && item.priceReference && item.priceReference.status === 'active'">
                                                        <div class="mt-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-900">
                                                            <p class="font-semibold">Harga Supplier Aktif: <span x-text="formatRupiah(item.priceReference.price)"></span></p>
                                                            <p class="mt-0.5 text-green-800">Berlaku: <span x-text="formatDate(item.priceReference.valid_from)"></span> - <span x-text="item.priceReference.valid_until ? formatDate(item.priceReference.valid_until) : 'seterusnya'"></span></p>
                                                        </div>
                                                    </template>

                                                    <template x-if="!item.priceReferenceLoading && item.priceReference && item.priceReference.status === 'upcoming'">
                                                        <div class="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900">
                                                            <p class="font-semibold">Harga Supplier Akan Berlaku: <span x-text="formatRupiah(item.priceReference.price)"></span></p>
                                                            <p class="mt-0.5 text-blue-800">Mulai: <span x-text="formatDate(item.priceReference.valid_from)"></span></p>
                                                        </div>
                                                    </template>

                                                    <template x-if="!item.priceReferenceLoading && item.priceReference && item.priceReference.status === 'expired'">
                                                        <div class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-900">
                                                            <p class="font-semibold">Harga Terakhir Supplier: <span x-text="formatRupiah(item.priceReference.price)"></span></p>
                                                            <p class="mt-0.5 text-red-800">Expired <span x-text="formatDate(item.priceReference.valid_until)"></span></p>
                                                            <p class="mt-1 font-medium text-red-900">Harga supplier ini sudah tidak aktif. Konfirmasi kembali dengan supplier.</p>
                                                        </div>
                                                    </template>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number" min="0.0001" step="0.0001" class="form-control w-28" x-model="item.quantity" @input="clearError(`items.${index}.quantity`)">
                                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors[`items.${index}.quantity`]" x-text="errors[`items.${index}.quantity`]"></span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="number" min="0" step="0.01" class="form-control w-32" x-model="item.unit_price" @input="clearError(`items.${index}.unit_price`)">
                                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors[`items.${index}.unit_price`]" x-text="errors[`items.${index}.unit_price`]"></span>
                                                </td>
                                                <td class="px-4 py-3 text-right font-semibold text-ink" x-text="formatRupiah(itemSubtotal(item))"></td>
                                                <td class="px-4 py-3 text-right">
                                                    <button type="button" class="rounded-lg border border-line px-2.5 py-1.5 text-xs font-semibold text-muted hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-40" :disabled="items.length === 1" @click="removeItem(index)">Hapus</button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                            <label class="block rounded-xl border border-line bg-white p-5 sm:p-6">
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Catatan <span class="font-normal text-muted">(opsional)</span></span>
                                <textarea class="form-control min-h-24" x-model="form.notes" placeholder="Catatan untuk supplier atau tim internal"></textarea>
                            </label>

                            <div class="rounded-xl border border-line bg-white p-5 sm:p-6">
                                <div class="space-y-3 text-sm">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-muted">Subtotal barang</span>
                                        <span class="font-semibold text-ink" x-text="formatRupiah(subtotal)"></span>
                                    </div>
                                    <label class="flex items-center justify-between gap-4">
                                        <span class="text-muted">Ongkos kirim</span>
                                        <input type="number" min="0" step="0.01" class="form-control w-32 text-right" x-model="form.shipping_cost">
                                    </label>
                                    <label class="flex items-center justify-between gap-4">
                                        <span class="text-muted">Biaya lain</span>
                                        <input type="number" min="0" step="0.01" class="form-control w-32 text-right" x-model="form.other_cost">
                                    </label>
                                    <div class="flex items-center justify-between gap-4 border-t border-line pt-3">
                                        <span class="font-semibold text-ink">Total PO</span>
                                        <span class="text-lg font-bold tracking-[-0.025em] text-brand-800" x-text="formatRupiah(grandTotal)"></span>
                                    </div>
                                </div>
                                <button
                                    type="submit"
                                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-60"
                                    :disabled="saving"
                                    x-text="saving ? 'Menyimpan...' : 'Simpan sebagai Draft'"
                                ></button>
                            </div>
                        </section>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
