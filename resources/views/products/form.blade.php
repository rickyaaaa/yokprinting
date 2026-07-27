@php
    $productCode = $productCode ?? null;
    $isEdit = $productCode !== null;
    $product = [
        'id' => $productCode,
        'sku' => '',
        'name' => '',
        'category' => 'Cup PP',
        'brand' => '',
        'unit' => 'Pcs',
        'purchasePrice' => 0,
        'stock' => 0,
        'minimumStock' => 0,
        'minimumOrderQty' => 1000,
        'packageConversion' => 500,
        'shortDescription' => '1 Dus = 1.000 Pcs; kelipatan order 500 Pcs',
        'status' => 'Aktif',
        'description' => '',
        'trackStock' => true,
    ];
    $title = $isEdit ? 'Edit produk' : 'Tambah produk baru';
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Form tambah produk YokPrinting.ID">

        <title>{{ $title }} - YokPrinting.ID</title>

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
                    <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden" @click="sidebarOpen = true" aria-controls="app-sidebar" :aria-expanded="sidebarOpen" aria-label="Buka navigasi">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('products.index') }}" class="hidden text-muted hover:text-ink sm:inline">Data produk</a>
                        <svg class="hidden size-4 text-line sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate font-medium text-ink">{{ $isEdit ? 'Edit produk' : 'Tambah produk' }}</span>
                    </div>
                    <div class="ml-auto hidden text-sm text-muted sm:block">Jumat, 24 Juli 2026</div>
                </header>

                <main class="mx-auto w-full max-w-[1180px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Data produk</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">{{ $title }}</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Tambahkan katalog produk cetak, harga beli, supplier, dan ambang stok minimum. Harga jual tetap diisi per item invoice.</p>
                        </div>
                        <a href="{{ route('products.index') }}" class="inline-flex w-fit items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Kembali ke daftar
                        </a>
                    </div>

                    <form
                        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
                        x-data='productForm(@json($product), @json($isEdit))'
                        @submit.prevent="submit()"
                        novalidate
                    >
                        <section class="space-y-6">
                            <div
                                x-show="saved"
                                x-cloak
                                data-testid="product-saved-notice"
                                class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-900"
                            >
                                <p class="font-semibold" x-text="isEdit ? 'Perubahan produk tersimpan.' : 'Produk baru tersimpan.'"></p>
                                <p class="mt-1">Data katalog sudah masuk ke database dan bisa dipilih saat membuat invoice.</p>
                            </div>

                            <div
                                x-show="loading"
                                x-cloak
                                class="rounded-xl border border-line bg-white p-4 text-sm text-muted"
                            >
                                Memuat detail produk…
                            </div>

                            <div
                                x-show="error"
                                x-cloak
                                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800"
                                x-text="error"
                            >
                            </div>

                            <div
                                x-show="validationMessages.length > 0"
                                x-cloak
                                data-testid="product-validation-summary"
                                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                            >
                                <p class="font-semibold">Form produk perlu diperiksa</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    <template x-for="message in validationMessages" :key="message">
                                        <li x-text="message"></li>
                                    </template>
                                </ul>
                            </div>

                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="product-info-heading">
                                <h2 id="product-info-heading" class="font-semibold text-ink">Informasi produk</h2>
                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Kode barang</span>
                                        <input class="form-control mt-1.5" x-model="form.sku" data-validation-field="sku" placeholder="Kosongkan untuk auto H-XXX" :aria-invalid="Boolean(fieldErrors.sku)" @input="clearFieldError('sku')">
                                        <span class="mt-1 block text-xs text-muted">Opsional. Sistem akan generate H-001, H-002, dst jika kosong.</span>
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.sku" x-text="fieldErrors.sku"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Nama produk</span>
                                        <input class="form-control mt-1.5" x-model="form.name" data-validation-field="name" placeholder="Contoh: Cetak brosur premium" :aria-invalid="Boolean(fieldErrors.name)" @input="clearFieldError('name')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.name" x-text="fieldErrors.name"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Kategori</span>
                                        <input class="form-control mt-1.5" x-model="form.category" placeholder="Contoh: Cup PP, Cup PET, Tutup/Lid">
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Satuan</span>
                                        <input class="form-control mt-1.5 bg-canvas font-semibold text-muted" x-model="form.unit" readonly>
                                        <span class="mt-1 block text-xs text-muted">Satuan master produk dikunci ke Pcs.</span>
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Merk barang</span>
                                        <input class="form-control mt-1.5" x-model="form.brand" placeholder="Contoh: SJP, PLP, MCup, ASB">
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Deskripsi singkat</span>
                                        <input class="form-control mt-1.5" x-model="form.shortDescription" placeholder="Contoh: 1 Dus = 1.000 Pcs; kelipatan order 500 Pcs">
                                    </label>
                                    <label class="block md:col-span-2">
                                        <span class="text-sm font-medium text-ink">Deskripsi lengkap</span>
                                        <textarea class="form-control mt-1.5 min-h-24" x-model="form.description" placeholder="Catatan penggunaan produk saat dipilih di invoice"></textarea>
                                    </label>
                                </div>
                            </section>

                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="product-pricing-heading">
                                <h2 id="product-pricing-heading" class="font-semibold text-ink">Harga beli & stok</h2>
                                <div class="mt-5 grid gap-4 md:grid-cols-2">
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Harga beli</span>
                                        <input type="number" min="0" step="1000" class="form-control mt-1.5" x-model.number="form.purchasePrice" data-validation-field="purchasePrice" :aria-invalid="Boolean(fieldErrors.purchasePrice)" @input="clearFieldError('purchasePrice')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.purchasePrice" x-text="fieldErrors.purchasePrice"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Status katalog</span>
                                        <select class="form-control mt-1.5" x-model="form.status">
                                            <option>Aktif</option>
                                            <option>Stok menipis</option>
                                            <option>Nonaktif</option>
                                        </select>
                                    </label>
                                    <label class="block rounded-xl border border-line bg-canvas p-4 md:col-span-2">
                                        <span class="flex items-start gap-3">
                                            <input type="checkbox" class="mt-1 size-4 rounded border-line text-brand-700 focus:ring-brand-600" x-model="form.trackStock">
                                            <span>
                                                <span class="block text-sm font-semibold text-ink">Lacak stok produk</span>
                                                <span class="mt-1 block text-sm leading-6 text-muted">Matikan untuk jasa desain atau layanan yang tidak memakai inventori fisik.</span>
                                            </span>
                                        </span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Stok tersedia</span>
                                        <input type="number" min="0" class="form-control mt-1.5" x-model.number="form.stock" data-validation-field="stock" :disabled="!form.trackStock" :aria-invalid="Boolean(fieldErrors.stock)" @input="clearFieldError('stock')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.stock" x-text="fieldErrors.stock"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Minimum stok</span>
                                        <input type="number" min="0" class="form-control mt-1.5" x-model.number="form.minimumStock" data-validation-field="minimumStock" :disabled="!form.trackStock" :aria-invalid="Boolean(fieldErrors.minimumStock)" @input="clearFieldError('minimumStock')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.minimumStock" x-text="fieldErrors.minimumStock"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Minimum order</span>
                                        <input type="number" min="1" step="500" class="form-control mt-1.5" x-model.number="form.minimumOrderQty" data-validation-field="minimumOrderQty" :aria-invalid="Boolean(fieldErrors.minimumOrderQty)" @input="clearFieldError('minimumOrderQty')">
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.minimumOrderQty" x-text="fieldErrors.minimumOrderQty"></span>
                                    </label>
                                    <label class="block">
                                        <span class="text-sm font-medium text-ink">Kelipatan order</span>
                                        <input type="number" min="1" step="500" class="form-control mt-1.5" x-model.number="form.packageConversion" data-validation-field="packageConversion" :aria-invalid="Boolean(fieldErrors.packageConversion)" @input="clearFieldError('packageConversion')">
                                        <span class="mt-1 block text-xs text-muted">Default YokPrinting: 500 Pcs.</span>
                                        <span class="mt-1 block text-xs text-red-700" x-show="fieldErrors.packageConversion" x-text="fieldErrors.packageConversion"></span>
                                    </label>
                                </div>
                            </section>
                        </section>

                        <aside class="space-y-6">
                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="product-preview-heading">
                                <h2 id="product-preview-heading" class="font-semibold text-ink">Preview katalog</h2>
                                <div class="mt-5 rounded-xl border border-line bg-canvas p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-ink" x-text="form.name || 'Nama produk'"></p>
                                            <p class="mt-0.5 font-mono text-xs text-muted" x-text="form.sku || 'SKU'"></p>
                                        </div>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(form.status)" x-text="form.status"></span>
                                    </div>
                                    <dl class="mt-5 space-y-3 text-sm">
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Kategori</dt>
                                            <dd class="font-medium text-ink" x-text="form.category"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Harga beli</dt>
                                            <dd class="font-semibold text-ink" x-text="formattedPurchasePrice"></dd>
                                        </div>
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-muted">Stok</dt>
                                            <dd class="font-medium text-ink" x-text="stockLabel"></dd>
                                        </div>
                                    </dl>
                                </div>
                            </section>

                            <section class="rounded-xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-900">
                                <h2 class="font-semibold">Catatan workflow</h2>
                                <p class="mt-3 leading-6">Harga jual tidak disimpan di master produk. Admin mengisi harga jual langsung saat membuat invoice.</p>
                            </section>

                            <div class="rounded-xl bg-white p-5 border border-line">
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 disabled:opacity-60" :disabled="saving || loading">
                                    <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M12 3a9 9 0 1 1-9 9" stroke-linecap="round"/>
                                    </svg>
                                    <span x-text="saving ? 'Menyimpan...' : '{{ $isEdit ? 'Simpan perubahan' : 'Simpan produk' }}'"></span>
                                </button>
                                <a href="{{ route('products.index') }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                    Batal
                                </a>
                            </div>
                        </aside>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
