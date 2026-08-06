<section
    x-data="{
        subtotal: 21250000,
        discountType: 'percentage',
        discountValue: 5,
        taxEnabled: true,
        taxRate: 11,
        shippingCost: 0,
        isFreeShipping: false,
        clamp(value, minimum, maximum) {
            return Math.min(maximum, Math.max(minimum, Number(value) || 0));
        },
        get discountAmount() {
            if (this.discountType === 'fixed') {
                return Math.min(this.subtotal, Math.max(0, Number(this.discountValue) || 0));
            }

            return this.subtotal * this.clamp(this.discountValue, 0, 100) / 100;
        },
        get taxableBase() {
            return Math.max(0, this.subtotal - this.discountAmount);
        },
        get taxAmount() {
            return this.taxEnabled
                ? this.taxableBase * this.clamp(this.taxRate, 0, 100) / 100
                : 0;
        },
        get grandTotal() {
            return this.taxableBase + this.taxAmount + (this.isFreeShipping ? 0 : Math.max(0, Number(this.shippingCost) || 0));
        },
        get shippingLabel() {
            return this.isFreeShipping ? 'Free ongkir ditanggung perusahaan' : 'Ongkir dibayar customer';
        },
        normalizeShipping() {
            this.shippingCost = Math.max(0, Number(this.shippingCost) || 0);
        },
        normalizeDiscount() {
            const maximum = this.discountType === 'percentage' ? 100 : this.subtotal;
            this.discountValue = this.clamp(this.discountValue, 0, maximum);
        },
        normalizeTax() {
            this.taxRate = this.clamp(this.taxRate, 0, 100);
        },
        formatCurrency(value) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0,
            }).format(Math.round(value));
        },
    }"
    @invoice-subtotal-changed.window="subtotal = Math.max(0, Number($event.detail.subtotal) || 0); normalizeDiscount()"
    @invoice-draft-restore.window="
        discountType = $event.detail.discount?.type ?? discountType;
        discountValue = Number($event.detail.discount?.value ?? discountValue) || 0;
        taxEnabled = Boolean($event.detail.tax?.enabled);
        taxRate = Number($event.detail.tax?.rate ?? taxRate) || 0;
        shippingCost = Number($event.detail.shipping_cost ?? shippingCost) || 0;
        isFreeShipping = Boolean($event.detail.is_free_shipping);
        normalizeDiscount();
        normalizeTax();
        normalizeShipping();
    "
    class="overflow-hidden rounded-xl bg-white border border-line"
>
    <div class="border-b border-line px-5 py-4">
        <h2 class="font-semibold text-ink">Ringkasan</h2>
        <p class="mt-1 text-sm text-muted">Nilai invoice dalam Rupiah.</p>
    </div>

    <div class="border-b border-line bg-canvas px-5 py-4">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-ink">Pajak & diskon</h3>
                <p class="mt-0.5 text-xs text-muted">Kalkulasi diperbarui otomatis.</p>
            </div>
            <svg class="size-5 text-brand-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-3">
            <div>
                <label for="discount-type" class="mb-1.5 block text-xs font-medium text-muted">Jenis diskon</label>
                <select id="discount-type" x-model="discountType" name="discount_type" class="form-control" @change="normalizeDiscount()">
                    <option value="percentage">Persentase (%)</option>
                    <option value="fixed">Nominal (Rp)</option>
                </select>
            </div>
            <div>
                <label for="discount-value" class="mb-1.5 block text-xs font-medium text-muted">Nilai</label>
                <div class="relative">
                    <span
                        class="pointer-events-none absolute top-1/2 -translate-y-1/2 text-xs text-muted"
                        :class="discountType === 'fixed' ? 'left-3' : 'right-3'"
                        x-text="discountType === 'fixed' ? 'Rp' : '%'"
                    ></span>
                    <input
                        id="discount-value"
                        x-model.number="discountValue"
                        name="discount_value"
                        data-validation-field="discount_value"
                        type="number"
                        min="0"
                        :max="discountType === 'percentage' ? 100 : subtotal"
                        class="form-control text-right"
                        :class="[
                            discountType === 'fixed' ? 'pl-9' : 'pr-8',
                            fieldErrors?.discount_value ? 'border-red-400 ring-2 ring-red-100' : '',
                        ]"
                        :aria-invalid="Boolean(fieldErrors?.discount_value)"
                        @blur="normalizeDiscount()"
                    >
                </div>
                <p x-show="fieldErrors?.discount_value" x-text="fieldErrors?.discount_value" class="mt-1.5 text-xs font-medium text-red-700"></p>
            </div>
        </div>

        <div class="mt-4 flex items-end gap-3">
            <label class="flex min-h-11 flex-1 cursor-pointer items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-ink">
                <input type="checkbox" x-model="taxEnabled" name="tax_enabled" class="size-4 accent-brand-700">
                Terapkan PPN
            </label>
            <div class="w-24">
                <label for="tax-rate" class="mb-1.5 block text-xs font-medium text-muted">Tarif</label>
                <div class="relative">
                    <input
                        id="tax-rate"
                        x-model.number="taxRate"
                        name="tax_rate"
                        data-validation-field="tax_rate"
                        type="number"
                        min="0"
                        max="100"
                        class="form-control pr-8 text-right disabled:cursor-not-allowed disabled:bg-line/40 disabled:text-muted"
                        :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.tax_rate }"
                        :aria-invalid="Boolean(fieldErrors?.tax_rate)"
                        :disabled="! taxEnabled"
                        @blur="normalizeTax()"
                    >
                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted">%</span>
                </div>
                <p x-show="fieldErrors?.tax_rate" x-text="fieldErrors?.tax_rate" class="mt-1.5 text-xs font-medium text-red-700"></p>
            </div>
        </div>

        <div class="mt-4 grid gap-3">
            <div>
                <label for="shipping-cost" class="mb-1.5 block text-xs font-medium text-muted">Ongkir</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-muted">Rp</span>
                    <input
                        id="shipping-cost"
                        x-model.number="shippingCost"
                        name="shipping_cost"
                        data-validation-field="shipping_cost"
                        type="number"
                        min="0"
                        step="1000"
                        class="form-control pl-9 text-right"
                        :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.shipping_cost }"
                        :aria-invalid="Boolean(fieldErrors?.shipping_cost)"
                        @blur="normalizeShipping()"
                    >
                </div>
                <p x-show="fieldErrors?.shipping_cost" x-text="fieldErrors?.shipping_cost" class="mt-1.5 text-xs font-medium text-red-700"></p>
            </div>
            <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm font-medium text-ink">
                <span>
                    <span class="block">Free Ongkir</span>
                    <span class="mt-0.5 block text-xs font-normal text-muted" x-text="shippingLabel"></span>
                </span>
                <input type="checkbox" x-model="isFreeShipping" name="is_free_shipping" class="size-4 accent-brand-700">
            </label>
        </div>
    </div>

    <div class="space-y-3 px-5 py-5 text-sm" aria-live="polite">
        <div class="flex items-center justify-between gap-4 text-muted">
            <span>Subtotal</span>
            <span class="font-medium text-ink" data-testid="summary-subtotal" x-text="formatCurrency(subtotal)"></span>
        </div>
        <div class="flex items-center justify-between gap-4 text-muted">
            <span>
                Diskon
                <span class="text-xs" x-show="discountType === 'percentage'" x-text="`(${clamp(discountValue, 0, 100)}%)`"></span>
            </span>
            <span class="font-medium text-red-700" data-testid="summary-discount" x-text="`− ${formatCurrency(discountAmount)}`"></span>
        </div>
        <div class="flex items-center justify-between gap-4 text-muted">
            <span>
                PPN
                <span class="text-xs" x-show="taxEnabled" x-text="`(${clamp(taxRate, 0, 100)}%)`"></span>
            </span>
            <span class="font-medium text-ink" data-testid="summary-tax" x-text="formatCurrency(taxAmount)"></span>
        </div>
        <div class="flex items-center justify-between gap-4 text-muted">
            <span x-text="isFreeShipping ? 'Ongkir gratis' : 'Ongkir'"></span>
            <span class="font-medium" :class="isFreeShipping ? 'text-red-700' : 'text-ink'" data-testid="summary-shipping" x-text="isFreeShipping ? `− ${formatCurrency(shippingCost)}` : formatCurrency(shippingCost)"></span>
        </div>
        <div class="my-1 border-t border-dashed border-line"></div>
        <div class="flex items-end justify-between gap-4">
            <span class="font-medium text-ink">Total tagihan</span>
            <p class="text-xl font-bold tracking-[-0.025em] text-brand-800" data-testid="summary-grand-total" x-text="formatCurrency(grandTotal)"></p>
        </div>
    </div>

    <div class="space-y-3 border-t border-line bg-canvas p-5">
        <button
            type="submit"
            data-testid="save-invoice-draft"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-800 active:translate-y-px disabled:cursor-wait disabled:opacity-70"
            :disabled="savingDraft"
        >
            <svg x-show="! savingDraft" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M5 4h12l2 2v14H5V4Zm3 0v6h8V4M8 16h8" stroke-linejoin="round"/>
            </svg>
            <svg x-show="savingDraft" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
            </svg>
            <span x-text="savingDraft ? 'Menyimpan invoice…' : (draftSaved ? 'Invoice tersimpan' : 'Simpan invoice')"></span>
        </button>
        <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-brand-300 bg-white px-4 py-3 text-sm font-semibold text-brand-800 hover:bg-brand-50"
            @click="$dispatch('invoice-preview-requested', { url: '{{ route('invoices.preview') }}' })"
        >
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="2.5"/>
            </svg>
            Pratinjau invoice
        </button>
    </div>
</section>
