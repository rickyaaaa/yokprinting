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
                            <select
                                x-model.number="item.productId"
                                :name="`items[${index}][product_id]`"
                                :aria-label="`Produk baris ${index + 1}`"
                                :data-testid="`product-picker-${index + 1}`"
                                data-validation-field="items"
                                class="form-control font-medium"
                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.items }"
                                :aria-invalid="Boolean(fieldErrors?.items)"
                                @change="updateProduct(item)"
                            >
                                <template x-for="product in products" :key="product.id">
                                    <option
                                        :value="product.id"
                                        :selected="Number(product.id) === Number(item.productId)"
                                        x-text="product.name"
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

                            <div class="mt-4 grid gap-3 rounded-lg border border-line bg-canvas p-3 sm:grid-cols-5">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`cup-size-${item.key}`">Ukuran</label>
                                    <select :id="`cup-size-${item.key}`" x-model="item.cupSize" :name="`items[${index}][cup_size]`" class="form-control h-9 text-xs">
                                        <option>12 Oz</option>
                                        <option>14 Oz</option>
                                        <option>16 Oz</option>
                                        <option>18 Oz</option>
                                        <option>22 Oz</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`cup-model-${item.key}`">Model</label>
                                    <select :id="`cup-model-${item.key}`" x-model="item.cupModel" :name="`items[${index}][cup_model]`" class="form-control h-9 text-xs">
                                        <option>Datar</option>
                                        <option>Oval</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`grammage-${item.key}`">Gramasi</label>
                                    <select :id="`grammage-${item.key}`" x-model="item.grammage" :name="`items[${index}][grammage]`" class="form-control h-9 text-xs">
                                        <option>7gr</option>
                                        <option>8gr</option>
                                        <option>9gr</option>
                                        <option>9.5gr</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`ink-${item.key}`">Tinta</label>
                                    <input :id="`ink-${item.key}`" x-model="item.screenPrintingColor" :name="`items[${index}][screen_printing_color]`" class="form-control h-9 text-xs" placeholder="Hitam">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-muted" :for="`sides-${item.key}`">Sisi</label>
                                    <select :id="`sides-${item.key}`" x-model.number="item.sides" :name="`items[${index}][sides]`" class="form-control h-9 text-xs">
                                        <option :value="1">1 Sisi</option>
                                        <option :value="2">2 Sisi</option>
                                    </select>
                                </div>
                            </div>

                            <p class="mt-2 text-xs leading-5 text-muted">
                                <span class="font-medium text-ink" x-text="cupDescription(item)"></span>
                                <span aria-hidden="true"> Â· </span>
                                MOQ <span x-text="`${item.moqQuantity} ${item.packagingUnit}`"></span>, kelipatan <span x-text="`${item.orderIncrement} ${item.packagingUnit}`"></span>.
                            </p>
                        </td>
                        <td class="px-3 py-4 align-top">
                            <input
                                x-model.number="item.quantity"
                                :name="`items[${index}][quantity]`"
                                :aria-label="`Jumlah ${productName(item.productId)}`"
                                type="number"
                                data-validation-field="items"
                                min="1"
                                step="1"
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
                                    step="1000"
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
