<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Catat Penerimaan Barang YokPrinting.ID">
        <title>Terima Barang - YokPrinting.ID</title>
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
                        <a href="{{ route('goods-receipts.index') }}" class="hidden text-muted hover:text-ink sm:inline">Penerimaan Barang</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="font-medium text-ink">Terima barang</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-[900px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='goodsReceiptFormPage(@json(["today" => now()->toDateString(), "indexUrl" => route("goods-receipts.index")]))'
                >
                    <div class="mb-6">
                        <span class="badge badge-brand">Pembelian</span>
                        <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Terima Barang</h1>
                        <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Harga diambil otomatis dari PO dan tidak bisa diubah di sini. Draft belum mengubah stok - klik Posting di daftar setelah data sesuai.</p>
                    </div>

                    <div x-show="generalError" x-cloak class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" x-text="generalError" role="alert"></div>
                    <div x-show="loading" class="card p-4 text-sm text-muted">Memuat data PO...</div>

                    <form x-show="!loading && purchaseOrder" class="space-y-6" @submit.prevent="submit()">
                        <section class="card p-5 sm:p-6">
                            <h2 class="font-semibold text-ink">
                                PO <span x-text="purchaseOrder?.po_number"></span>
                                <span class="ml-2 text-sm font-normal text-muted" x-text="purchaseOrder?.supplier?.name"></span>
                            </h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Tanggal terima</span>
                                    <input type="date" class="form-control" x-model="form.receipt_date">
                                </label>
                                <label>
                                    <span class="mb-1.5 block text-xs font-semibold text-muted">Catatan <span class="font-normal text-muted">(opsional)</span></span>
                                    <input type="text" class="form-control" x-model="form.notes" placeholder="Nomor surat jalan, dsb.">
                                </label>
                            </div>
                        </section>

                        <section class="card">
                            <div class="border-b border-line px-5 py-4 sm:px-6">
                                <h2 class="font-semibold text-ink">Jumlah diterima</h2>
                                <p class="mt-1 text-sm text-muted" x-show="errors.items" x-text="errors.items"></p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[640px] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-xs font-semibold text-muted">
                                            <th class="px-5 py-3 sm:px-6">Barang</th>
                                            <th class="px-4 py-3 text-right">Qty PO</th>
                                            <th class="px-4 py-3 text-right">Sudah diterima</th>
                                            <th class="px-4 py-3 text-right">Sisa</th>
                                            <th class="px-4 py-3">Diterima sekarang</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        <template x-for="(item, index) in items" :key="item.purchase_order_item_id">
                                            <tr>
                                                <td class="px-5 py-3 sm:px-6">
                                                    <span class="block font-semibold text-ink" x-text="item.product_name"></span>
                                                    <span class="block text-xs text-muted" x-text="`${item.sku} · ${formatRupiah(item.unit_price)}/pcs`"></span>
                                                </td>
                                                <td class="px-4 py-3 text-right text-muted" x-text="item.ordered"></td>
                                                <td class="px-4 py-3 text-right text-muted" x-text="item.already_received"></td>
                                                <td class="px-4 py-3 text-right font-semibold text-ink" x-text="item.remaining"></td>
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <input type="number" min="0" :max="item.remaining" step="0.0001" class="form-control w-28" x-model="item.quantity_received" @input="clearError(`items.${index}`)">
                                                        <button type="button" class="btn btn-sm btn-outline whitespace-nowrap" @click="fillMax(index)">Isi penuh</button>
                                                    </div>
                                                    <span class="mt-1.5 block text-xs text-red-700" x-show="errors[`items.${index}`]" x-text="errors[`items.${index}`]"></span>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="items.length === 0">
                                            <td colspan="5" class="px-5 py-8 text-center text-muted">Semua item PO ini sudah diterima penuh.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <button
                            type="submit"
                            class="btn btn-primary w-full disabled:cursor-wait sm:w-auto"
                            :disabled="saving || items.length === 0"
                            x-text="saving ? 'Menyimpan...' : 'Simpan sebagai Draft'"
                        ></button>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
