<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Buat invoice baru di YokPrinting.ID">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Buat Invoice Baru — YokPrinting.ID</title>

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
                    <button
                        type="button"
                        class="btn-icon mr-3 border-transparent lg:hidden"
                        @click="sidebarOpen = true"
                        aria-controls="app-sidebar"
                        :aria-expanded="sidebarOpen"
                        aria-label="Buka navigasi"
                    >
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="#" class="hidden text-muted hover:text-ink sm:inline">Invoice</a>
                        <i class="iconify tabler--chevron-right hidden text-sm text-line sm:block"></i>
                        <span class="truncate font-medium text-ink">Buat baru</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <x-notification-bell />
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">{{ now(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y') }}</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex items-center gap-2">
                                <span class="badge badge-brand">Invoice baru</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Buat invoice baru</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Lengkapi detail tagihan, item, dan jatuh tempo. Kamu bisa meninjau ulang sebelum mengirimkannya.</p>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-muted">
                            <span class="inline-flex size-2 rounded-full bg-brand-400"></span>
                            Perubahan tersimpan otomatis
                        </div>
                    </div>

                    <form
                        class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
                        action="#"
                        method="post"
                        novalidate
                        x-data="invoiceDraftForm"
                        @submit.prevent="submitDraft($event)"
                        @input="clearFieldError($event.target.dataset.validationField || $event.target.name)"
                        @change="clearFieldError($event.target.dataset.validationField || $event.target.name)"
                        @customer-selected="clearFieldError('customer_id')"
                        @invoice-preview-requested="previewDraft($event.detail.url, $event)"
                    >
                        <div
                            x-cloak
                            x-show="validationMessages.length > 0"
                            class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 xl:col-span-2"
                            role="alert"
                            data-testid="invoice-validation-summary"
                        >
                            <p class="font-semibold">Periksa kembali data invoice</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <template x-for="message in validationMessages" :key="message">
                                    <li x-text="message"></li>
                                </template>
                            </ul>
                        </div>

                        <div class="min-w-0 space-y-6">
                            <section class="card" aria-labelledby="invoice-details-heading">
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 id="invoice-details-heading" class="font-semibold text-ink">Detail invoice</h2>
                                    <p class="mt-1 text-sm text-muted">Informasi utama yang akan tampil pada dokumen invoice.</p>
                                </div>

                                <div class="space-y-6 p-5 sm:p-6">
                                    <x-customer-picker />

                                    <div class="grid gap-5 md:grid-cols-3">
                                        <div>
                                            <label for="invoice-number" class="mb-2 block text-sm font-medium text-ink">Nomor invoice</label>
                                            <div class="relative">
                                                <input
                                                    id="invoice-number"
                                                    name="invoice_number"
                                                    data-validation-field="invoice_number"
                                                    type="text"
                                                    value=""
                                                    placeholder="Dibuat otomatis saat disimpan"
                                                    class="form-control pr-10 font-medium"
                                                    :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.invoice_number }"
                                                    :aria-invalid="Boolean(fieldErrors.invoice_number)"
                                                    aria-describedby="invoice-number-error"
                                                >
                                                <i class="iconify tabler--sparkles pointer-events-none absolute right-3 top-1/2 text-base -translate-y-1/2 text-muted"></i>
                                            </div>
                                            <p id="invoice-number-error" x-show="fieldErrors.invoice_number" x-text="fieldErrors.invoice_number" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                            <p class="mt-1.5 text-xs text-muted">Nomor dibuat otomatis.</p>
                                        </div>
                                        <div>
                                            <label for="issue-date" class="mb-2 block text-sm font-medium text-ink">Tanggal invoice</label>
                                            <input
                                                id="issue-date"
                                                name="issue_date"
                                                data-validation-field="issue_date"
                                                type="date"
                                                value="{{ now(config('app.timezone'))->toDateString() }}"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.issue_date }"
                                                :aria-invalid="Boolean(fieldErrors.issue_date)"
                                                aria-describedby="issue-date-error"
                                            >
                                            <p id="issue-date-error" x-show="fieldErrors.issue_date" x-text="fieldErrors.issue_date" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>
                                        <div>
                                            <label for="due-date" class="mb-2 block text-sm font-medium text-ink">Jatuh tempo</label>
                                            <input
                                                id="due-date"
                                                name="due_date"
                                                data-validation-field="due_date"
                                                type="date"
                                                value="{{ now(config('app.timezone'))->addDays(14)->toDateString() }}"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.due_date }"
                                                :aria-invalid="Boolean(fieldErrors.due_date)"
                                                aria-describedby="due-date-error"
                                            >
                                            <p id="due-date-error" x-show="fieldErrors.due_date" x-text="fieldErrors.due_date" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                            <p class="mt-1.5 text-xs text-muted">14 hari setelah diterbitkan.</p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <x-invoice-items :hide-legacy-specs="true" />

                            <section class="card p-5 sm:p-6" aria-labelledby="additional-info-heading">
                                <h2 id="additional-info-heading" class="font-semibold text-ink">Informasi tambahan</h2>
                                <div class="mt-5 grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label for="production-status" class="mb-2 block text-sm font-medium text-ink">Status produksi</label>
                                        <select id="production-status" name="production_status" class="form-control">
                                            <option value="draft">Drafting</option>
                                            <option value="awaiting_dp" selected>Menunggu DP</option>
                                            <option value="design_acc">ACC Mockup/Desain</option>
                                            <option value="in_production">Proses Sablon/Cetak</option>
                                            <option value="ready_for_pickup">Siap Diambil/Kirim</option>
                                            <option value="completed">Lunas & Selesai</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="dp-required-percent" class="mb-2 block text-sm font-medium text-ink">Minimal DP produksi</label>
                                        <div class="relative">
                                            <input
                                                id="dp-required-percent"
                                                name="dp_required_percent"
                                                data-validation-field="dp_required_percent"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="1"
                                                value="50"
                                                class="form-control pr-9"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.dp_required_percent }"
                                            >
                                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted">%</span>
                                        </div>
                                        <p x-show="fieldErrors.dp_required_percent" x-text="fieldErrors.dp_required_percent" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                    </div>
                                </div>
                                <div class="mt-5 grid gap-5 md:grid-cols-3">
                                    <div>
                                        <label for="notes" class="mb-2 block text-sm font-medium text-ink">Catatan untuk pelanggan</label>
                                        <textarea id="notes" name="notes" rows="4" class="form-control min-h-28" placeholder="Tulis catatan yang akan tampil di invoice">Terima kasih telah mempercayakan kebutuhan bisnis Anda kepada kami.</textarea>
                                    </div>
                                    <div>
                                        <label for="terms" class="mb-2 block text-sm font-medium text-ink">Syarat pembayaran</label>
                                        <textarea id="terms" name="terms" rows="4" class="form-control min-h-28" placeholder="Tulis syarat pembayaran">Minimal DP 50% sebelum produksi. Pelunasan dilakukan sebelum barang dikirim atau diambil.</textarea>
                                    </div>
                                    <div>
                                        <label for="design-notes" class="mb-2 block text-sm font-medium text-ink">Catatan desain/produksi</label>
                                        <textarea id="design-notes" name="design_notes" rows="4" class="form-control min-h-28" placeholder="Contoh: Logo tengah, tinta hitam, jarak 2 cm dari bibir cup">Tinta hitam pekat, posisi logo tengah, tunggu ACC mockup sebelum naik produksi.</textarea>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <aside class="space-y-4 xl:sticky xl:top-22" aria-label="Ringkasan invoice">
                            <x-invoice-totals />

                            <div class="flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                                <i class="iconify tabler--info-circle mt-0.5 text-lg shrink-0 text-brand-700"></i>
                                <p class="leading-5">Invoice akan disimpan ke backend, termasuk spesifikasi cup, DP, dan catatan produksi.</p>
                            </div>
                        </aside>

                        <div
                            x-cloak
                            x-show="draftSaved || errorMessage"
                            x-transition:enter="transition duration-200 ease-out"
                            x-transition:enter-start="translate-y-2 opacity-0"
                            x-transition:enter-end="translate-y-0 opacity-100"
                            x-transition:leave="transition duration-150 ease-out"
                            x-transition:leave-start="translate-y-0 opacity-100"
                            x-transition:leave-end="translate-y-2 opacity-0"
                            class="fixed bottom-4 left-4 right-4 z-50 mx-auto flex max-w-md items-start gap-3 rounded-xl p-4 text-white shadow-[0_4px_8px_oklch(0.2_0.02_113/0.22)] sm:left-auto sm:right-6"
                            :class="errorMessage ? 'bg-red-800' : (stockAlerts.length > 0 ? 'bg-amber-700' : 'bg-brand-900')"
                            :role="errorMessage ? 'alert' : 'status'"
                            aria-live="polite"
                            data-testid="draft-api-notice"
                        >
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-white/15">
                                <i x-show="! errorMessage" class="iconify tabler--check text-base"></i>
                                <i x-show="errorMessage" class="iconify tabler--alert-circle text-base"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold" x-text="errorMessage ? (errorTitle || 'Invoice gagal disimpan') : 'Invoice tersimpan via API'"></p>
                                <p
                                    class="mt-1 text-xs leading-5 text-white/80"
                                    x-text="errorMessage || `ID ${savedDraftId} · disimpan pukul ${savedAt}`"
                                ></p>
                                <template x-if="!errorMessage && stockAlerts.length > 0">
                                    <p class="mt-1.5 text-xs leading-5 text-white/90">
                                        ⚠️ Stok
                                        <template x-for="(alert, index) in stockAlerts" :key="alert.product_id">
                                            <span>
                                                <span x-text="alert.name"></span><span x-text="alert.is_negative ? ' sudah minus' : ' menipis'"></span><span x-text="` (${alert.stock})`"></span><span x-show="index < stockAlerts.length - 1">, </span>
                                            </span>
                                        </template>
                                    </p>
                                </template>
                            </div>
                            <button type="button" class="rounded-lg p-1.5 text-white/80 hover:bg-white/10 hover:text-white" @click="dismissDraftNotice()" aria-label="Tutup notifikasi draft">
                                <i class="iconify tabler--x text-base"></i>
                            </button>
                        </div>
                    </form>
                </main>
            </div>
        </div>
    </body>
</html>
