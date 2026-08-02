<div
    x-data="customerPicker"
    x-id="['customer-listbox']"
    class="relative"
    @click.outside="hide"
    @keydown.escape.window="hide"
>
    <div class="mb-2 flex items-center justify-between gap-4">
        <label class="form-label mb-0" for="customer-search">Pelanggan <span aria-hidden="true">*</span></label>
        <button class="text-sm font-semibold text-brand transition hover:text-brand-strong" type="button">
            + Pelanggan baru
        </button>
    </div>

    <input name="customer_id" type="hidden" :value="selected?.id ?? ''">
    <input name="customer_name" type="hidden" :value="selected?.name ?? ''">
    <input name="customer_email" type="hidden" :value="selected?.email ?? ''">
    <input name="customer_phone" type="hidden" :value="selected?.phone ?? ''">
    <input name="customer_address" type="hidden" :value="selected?.address ?? ''">

    <button
        class="form-control flex min-h-16 w-full items-center justify-between gap-4 text-left"
        data-testid="customer-picker-trigger"
        data-validation-field="customer_id"
        x-ref="trigger"
        type="button"
        role="combobox"
        :aria-controls="$id('customer-listbox')"
        :aria-expanded="open"
        :aria-invalid="Boolean(fieldErrors?.customer_id)"
        :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors?.customer_id }"
        :disabled="loading"
        @click="show"
    >
        <span class="flex min-w-0 items-center gap-3">
            <span
                class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-xs font-bold tracking-wide text-brand"
                x-text="selected?.initials ?? '…'"
            ></span>
            <span class="min-w-0">
                <span
                    class="block truncate text-sm font-semibold text-ink"
                    x-text="loading ? 'Memuat pelanggan…' : (selected?.name ?? 'Pilih pelanggan')"
                ></span>
                <span
                    class="mt-0.5 block truncate text-xs text-muted"
                    x-text="selected?.email ?? 'Data dari API pelanggan'"
                ></span>
            </span>
        </span>
        <svg class="size-4 shrink-0 text-muted transition" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div
        x-show="loading"
        class="mt-3 flex items-center gap-3 rounded-xl border border-line bg-surface-soft px-4 py-3 text-sm text-muted"
        data-testid="customer-picker-loading"
        role="status"
    >
        <span class="size-4 animate-spin rounded-full border-2 border-line border-t-brand" aria-hidden="true"></span>
        Memuat data pelanggan…
    </div>

    <div
        x-show="errorMessage && !loading"
        x-cloak
        class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger"
        role="alert"
    >
        <span x-text="errorMessage"></span>
        <button class="font-semibold underline underline-offset-2" type="button" @click="loadCustomers">
            Muat ulang pelanggan
        </button>
    </div>

    <div
        x-show="open && !loading && !errorMessage"
        x-cloak
        class="absolute z-50 mt-2 w-full overflow-hidden rounded-2xl border border-line bg-surface shadow-xl shadow-slate-900/10"
    >
        <div class="border-b border-line p-3">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.7"/>
                    <path d="m13 13 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
                <input
                    id="customer-search"
                    x-ref="search"
                    x-model="query"
                    class="form-control min-h-10 pl-9"
                    type="search"
                    placeholder="Cari nama, email, atau telepon"
                    autocomplete="off"
                    @keydown.arrow-down.prevent="$refs.customerOptions?.querySelector('[role=option]')?.focus()"
                >
            </div>
        </div>

        <div
            x-ref="customerOptions"
            :id="$id('customer-listbox')"
            class="max-h-64 overflow-y-auto p-2"
            role="listbox"
            aria-label="Daftar pelanggan"
        >
            <template x-for="customer in filteredCustomers" :key="customer.id">
                <button
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-surface-soft focus:bg-surface-soft focus:outline-none"
                    type="button"
                    role="option"
                    :aria-selected="selected?.id === customer.id"
                    @click="choose(customer); $dispatch('customer-selected')"
                >
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-xs font-bold text-brand"
                        x-text="customer.initials"
                    ></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-ink" x-text="customer.name"></span>
                        <span class="mt-0.5 block truncate text-xs text-muted" x-text="customer.email"></span>
                    </span>
                    <svg x-show="selected?.id === customer.id" class="size-4 shrink-0 text-brand" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="m4 10 3.5 3.5L16 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </template>

            <p x-show="filteredCustomers.length === 0" class="px-3 py-6 text-center text-sm text-muted">
                Pelanggan tidak ditemukan.
            </p>
        </div>
    </div>

    <p
        x-show="fieldErrors?.customer_id"
        x-text="fieldErrors?.customer_id"
        class="mt-2 text-xs font-medium text-red-700"
    ></p>

    <div
        x-show="selected && !loading && !errorMessage"
        x-cloak
        class="mt-3 grid gap-3 rounded-xl border border-line bg-surface-soft p-4 text-sm sm:grid-cols-2"
    >
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Kontak</p>
            <p class="mt-1 font-medium text-ink" x-text="selected?.email"></p>
            <p class="mt-0.5 text-muted" x-text="selected?.phone"></p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Alamat penagihan</p>
            <p class="mt-1 leading-relaxed text-ink" x-text="selected?.address"></p>
        </div>
    </div>
</div>
