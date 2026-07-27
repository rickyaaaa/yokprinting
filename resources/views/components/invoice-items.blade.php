<section
    x-data="invoiceItems"
    x-effect="$dispatch('invoice-subtotal-changed', { subtotal })"
    class="overflow-hidden rounded-xl bg-white border border-line"
    aria-labelledby="invoice-items-heading"
>
    <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
        <div>
            <h2 id="invoice-items-heading" class="font-semibold text-ink">Item tagihan</h2>
            <p class="mt-1 text-sm text-muted">Tambahkan produk atau jasa yang ditagihkan.</p>
        </div>
        <span
            class="shrink-0 rounded-full bg-canvas px-2.5 py-1 text-xs font-medium text-muted"
            x-text="loadingProducts ? 'Memuat…' : `${items.length} item`"
        ></span>
    </div>

    <div
        x-show="loadingProducts"
        class="flex items-center gap-3 border-b border-line px-6 py-5 text-sm text-muted"
        data-testid="product-picker-loading"
        role="status"
    >
        <span class="size-4 animate-spin rounded-full border-2 border-line border-t-brand" aria-hidden="true"></span>
        Memuat data produk…
    </div>

    <div
        x-show="productError && !loadingProducts"
        x-cloak
        class="m-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger"
        role="alert"
    >
        <span x-text="productError"></span>
        <button class="font-semibold underline underline-offset-2" type="button" @click="loadProducts">
            Muat ulang produk
        </button>
    </div>

    <div
        x-show="fieldErrors?.items"
        x-cloak
        class="mx-5 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800"
        role="alert"
        x-text="fieldErrors?.items"
    ></div>

    <div x-show="!loadingProducts && !productError" class="border-b border-line bg-canvas/60 px-5 py-4">
        <p class="text-sm font-medium text-ink">Pilih produk via search per baris</p>
        <p class="mt-1 text-xs text-muted">
            Ketik kode barang seperti H-001 atau nama produk. Ini menggantikan dropdown panjang supaya katalog 108 item tetap cepat dipilih.
        </p>
    </div>

    <div x-show="!loadingProducts && !productError" class="overflow-x-auto">
        <table class="w-full min-w-[1040px] text-left">
            <thead class="border-b border-line bg-canvas text-xs font-medium text-muted">
                <tr>
                    <th class="w-[38%] px-6 py-3">Produk / Jasa</th>
                    <th class="w-24 px-3 py-3">Jumlah</th>
                    <th class="w-44 px-3 py-3">Harga</th>
                    <th class="w-44 px-3 py-3 text-right">Total</th>
                    <th class="w-14 px-3 py-3"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                <template x-for="(item, index) in items" :key="item.key">
                    <tr>
                        <td class="px-6 py-4 align-top">
                            <div class="relative" @keydown.escape="item.pickerOpen = false">
                                <label class="sr-only" :for="`product-search-${item.key}`" x-text="`Produk baris ${index + 1}`"></label>
                                <input
                                    :id="`product-search-${item.key}`"
                                    x-model.debounce.120ms="item.productSearch"
                                    type="search"
                                    :aria-label="`Cari produk baris ${index + 1}`"
                                    :data-testid="`product-search-picker-${index + 1}`"
                                    data-validation-field="items"
                                    class="form-control pr-10 font-medium"
                                    :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.items }"
                                    :aria-invalid="Boolean(fieldErrors?.items)"
                                    placeholder="Ketik kode H-001 atau nama produk"
                                    autocomplete="off"
                                    @focus="openProductPicker(item)"
                                    @input="openProductPicker(item)"
                                >
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 inline-flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted hover:bg-brand-50 hover:text-brand-800"
                                    :aria-label="`Buka hasil produk baris ${index + 1}`"
                                    @click="toggleProductPicker(item)"
                                >
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>

                                <div
                                    x-show="item.pickerOpen"
                                    x-cloak
                                    x-transition.origin.top.left
                                    class="absolute left-0 right-0 z-40 mt-2 max-h-72 overflow-y-auto rounded-xl border border-line bg-white p-1.5 shadow-xl shadow-ink/10"
                                    role="listbox"
                                >
                                    <template x-for="product in filteredProductsFor(item)" :key="product.id">
                                        <button
                                            type="button"
                                            class="flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left text-sm hover:bg-brand-50"
                                            :class="Number(item.productId) === Number(product.id) ? 'bg-brand-50 text-brand-900' : 'text-ink'"
                                            role="option"
                                            :aria-selected="Number(item.productId) === Number(product.id)"
                                            @mousedown.prevent="selectProduct(item, product)"
                                        >
                                            <span class="mt-0.5 shrink-0 rounded-md bg-canvas px-2 py-1 font-mono text-xs font-semibold text-muted" x-text="product.sku ?? product.code"></span>
                                            <span class="min-w-0">
                                                <span class="block font-semibold" x-text="product.name"></span>
                                                <span class="mt-0.5 block truncate text-xs text-muted" x-text="productMeta(product)"></span>
                                            </span>
                                        </button>
                                    </template>
                                    <div
                                        x-show="filteredProductsFor(item).length === 0"
                                        class="px-3 py-4 text-sm text-muted"
                                    >
                                        Produk tidak ditemukan. Coba ketik kode H-xxx atau kata dari nama produk.
                                    </div>
                                </div>
                            </div>
                            <select
                                x-model.number="item.productId"
                                :name="`items[${index}][product_id]`"
                                :aria-label="`Produk baris ${index + 1}`"
                                :data-testid="`product-picker-${index + 1}`"
                                data-validation-field="items"
                                class="hidden"
                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.items }"
                                :aria-invalid="Boolean(fieldErrors?.items)"
                                @change="updateProduct(item)"
                            >
                                <template x-for="product in filteredSelectionProducts(item)" :key="product.id">
                                    <option
                                        :value="product.id"
                                        :selected="Number(product.id) === Number(item.productId)"
                                        x-text="`${product.sku ?? product.code} — ${product.name}`"
                                    ></option>
                                </template>
                            </select>
                            <p class="mt-1.5 text-xs text-muted">
                                <span x-text="productFor(item.productId)?.category"></span>
                                <span aria-hidden="true"> · </span>
                                SKU: <span x-text="productFor(item.productId)?.sku"></span>
                            </p>
                            <input type="hidden" :name="`items[${index}][product_name]`" :value="item.productName || productName(item.productId)">
                            <input type="hidden" :name="`items[${index}][sku]`" :value="item.sku">
                            <input type="hidden" :name="`items[${index}][moq_quantity]`" :value="item.moqQuantity">
                            <input type="hidden" :name="`items[${index}][order_increment]`" :value="item.orderIncrement">
                            <input type="hidden" :name="`items[${index}][packaging_unit]`" :value="item.packagingUnit">
                            <input type="hidden" :name="`items[${index}][description]`" :value="cupDescription(item)">

                            <div class="mt-4 grid min-w-[32rem] grid-cols-[5rem_5rem_4.75rem_5rem_7rem] gap-2 rounded-lg border border-line bg-canvas p-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`cup-size-${item.key}`">Ukuran</label>
                                    <select :id="`cup-size-${item.key}`" x-model="item.cupSize" :name="`items[${index}][cup_size]`" class="form-control spec-select">
                                        <option>12 Oz</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`cup-model-${item.key}`">Model</label>
                                    <select :id="`cup-model-${item.key}`" x-model="item.cupModel" :name="`items[${index}][cup_model]`" class="form-control spec-select">
                                        <option>Datar</option>
                                        <option>Oval</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`grammage-${item.key}`">Gramasi</label>
                                    <select :id="`grammage-${item.key}`" x-model="item.grammage" :name="`items[${index}][grammage]`" class="form-control spec-select">
                                        <option>7gr</option>
                                        <option>8gr</option>
                                        <option>9gr</option>
                                        <option>9.5gr</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`ink-${item.key}`">Tinta</label>
                                    <input :id="`ink-${item.key}`" x-model="item.screenPrintingColor" :name="`items[${index}][screen_printing_color]`" class="form-control h-10 px-2 text-xs" placeholder="Hitam">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`jenis-cetak-${item.key}`">Jenis cetak</label>
                                    <select :id="`jenis-cetak-${item.key}`" x-model="item.jenisCetak" :name="`items[${index}][jenis_cetak]`" class="form-control spec-select">
                                        <option>1 warna</option>
                                        <option>2 warna</option>
                                        <option>3 warna</option>
                                    </select>
                                </div>
                            </div>

                            <p class="mt-2 text-xs leading-5 text-muted">
                                <span class="font-medium text-ink" x-text="cupDescription(item)"></span>
                                <span aria-hidden="true"> · </span>
                                Kelipatan jumlah <span x-text="`${item.orderIncrement} ${item.packagingUnit}`"></span>.
                            </p>
                        </td>
                        <td class="px-3 py-4 align-top">
                            <input
                                x-model.number="item.quantity"
                                :name="`items[${index}][quantity]`"
                                :aria-label="`Jumlah ${productName(item.productId)}`"
                                type="number"
                                data-validation-field="items"
                                :min="item.orderIncrement"
                                :step="item.orderIncrement"
                                class="form-control text-center"
                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.items }"
                                :aria-invalid="Boolean(fieldErrors?.items)"
                                @blur="normalizeQuantity(item)"
                            >
                        </td>
                        <td class="px-3 py-4 align-top">
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted">Rp</span>
                                <input
                                    x-model.number="item.price"
                                    :name="`items[${index}][price]`"
                                    :aria-label="`Harga ${productName(item.productId)}`"
                                    type="number"
                                    data-validation-field="items"
                                    min="0"
                                    step="500"
                                    class="form-control pl-9 text-right"
                                    :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.items }"
                                    :aria-invalid="Boolean(fieldErrors?.items)"
                                >
                            </div>
                        </td>
                        <td class="px-3 py-4 align-top text-right">
                            <p class="pt-3 text-sm font-semibold text-ink" x-text="formatCurrency(itemTotal(item))"></p>
                        </td>
                        <td class="px-3 py-4 align-top">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-muted hover:bg-red-50 hover:text-red-700"
                                :aria-label="`Hapus ${productName(item.productId)}`"
                                :data-testid="`remove-invoice-item-${item.key}`"
                                @click="removeItem(item.key)"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 7h16M9 7V4h6v3M7 7l1 14h8l1-14M10 11v6M14 11v6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                </template>

                <tr x-show="items.length === 0">
                    <td colspan="5" class="px-6 py-12 text-center">
                        <div class="mx-auto grid size-10 place-items-center rounded-full bg-canvas text-muted">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" stroke-linejoin="round"/>
                                <path d="m4.5 7.8 7.5 4.3 7.5-4.3M12 12.1V21" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-medium text-ink">Belum ada item tagihan</p>
                        <p class="mt-1 text-xs text-muted">Tambahkan produk atau jasa untuk mulai menghitung invoice.</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="flex flex-col gap-4 border-t border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <button
            type="button"
            data-testid="add-invoice-item"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-brand-300 px-3.5 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50"
            :disabled="loadingProducts || Boolean(productError) || products.length === 0"
            @click="addItem()"
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
            </svg>
            Tambah item
        </button>
        <div class="flex items-baseline justify-between gap-6 sm:justify-end">
            <span class="text-sm text-muted">Subtotal item</span>
            <strong class="text-base font-semibold text-ink" data-testid="items-subtotal" x-text="formatCurrency(subtotal)"></strong>
        </div>
    </div>
</section>
