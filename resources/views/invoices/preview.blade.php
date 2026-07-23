@php
    $items = [
        [
            'name' => 'Paket Desain Identitas Brand',
            'description' => 'Strategi brand, sistem logo, panduan warna, dan brand guideline.',
            'quantity' => 1,
            'price' => 'Rp12.500.000',
            'total' => 'Rp12.500.000',
        ],
        [
            'name' => 'Website Company Profile',
            'description' => 'Desain dan pengembangan website responsif hingga 8 halaman.',
            'quantity' => 1,
            'price' => 'Rp8.750.000',
            'total' => 'Rp8.750.000',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Pratinjau invoice INV-2026-0079">

        <title>Pratinjau INV-2026-0079 — YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body
        x-data="invoicePreviewActions"
    >
        <header class="print-hide sticky top-0 z-20 border-b border-line bg-white/95 backdrop-blur-sm">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:px-6">
                <a
                    href="{{ route('invoices.create') }}"
                    class="inline-flex items-center gap-2 rounded-lg p-2 text-sm font-medium text-muted hover:bg-brand-50 hover:text-brand-800 sm:px-3"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="hidden sm:inline">Kembali ke editor</span>
                    <span class="sm:hidden">Kembali</span>
                </a>
                <span class="hidden h-6 w-px bg-line sm:block"></span>
                <div class="hidden min-w-0 sm:block">
                    <p class="truncate text-sm font-semibold text-ink">Pratinjau invoice</p>
                    <p class="truncate text-xs text-muted">INV-2026-0079 · Data tiruan</p>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <span class="hidden items-center gap-2 text-xs text-muted lg:inline-flex" aria-live="polite">
                        <span class="size-2 rounded-full" :class="invoiceStatus === 'Terkirim' || draftSaved ? 'bg-green-500' : 'bg-brand-400'"></span>
                        <span x-text="invoiceStatus === 'Terkirim' ? 'Invoice terkirim' : (draftSaved ? 'Draft tersimpan' : 'Siap ditinjau')"></span>
                    </span>
                    <button
                        type="button"
                        data-testid="save-preview-draft"
                        class="inline-flex min-w-28 items-center justify-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-70"
                        :disabled="savingDraft"
                        @click="saveDraft()"
                    >
                        <svg x-show="! savingDraft" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M5 4h12l2 2v14H5V4Zm3 0v6h8V4M8 16h8" stroke-linejoin="round"/>
                        </svg>
                        <svg x-show="savingDraft" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                        </svg>
                        <span x-text="savingDraft ? 'Menyimpan…' : (draftSaved ? 'Tersimpan' : 'Simpan draft')"></span>
                    </button>
                    <button
                        type="button"
                        data-testid="preview-email-action"
                        class="hidden min-w-32 items-center justify-center gap-2 rounded-lg border border-brand-300 bg-white px-3.5 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50 disabled:cursor-wait disabled:opacity-70 lg:inline-flex"
                        :disabled="sendingEmail"
                        :aria-busy="sendingEmail"
                        @click="sendEmail()"
                    >
                        <svg x-show="! sendingEmail" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 5h16v14H4zM4 7l8 6 8-6" stroke-linejoin="round"/>
                        </svg>
                        <svg x-show="sendingEmail" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                        </svg>
                        <span x-text="sendingEmail ? 'Mengirim…' : (invoiceStatus === 'Terkirim' ? 'Sudah terkirim' : 'Kirim email')"></span>
                    </button>
                    <button
                        type="button"
                        data-testid="preview-pdf-action"
                        class="hidden min-w-28 items-center justify-center gap-2 rounded-lg border border-brand-300 bg-white px-3.5 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50 disabled:cursor-wait disabled:opacity-70 lg:inline-flex"
                        :disabled="downloadingPdf"
                        :aria-busy="downloadingPdf"
                        @click="downloadPdf()"
                    >
                        <svg x-show="! downloadingPdf" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M7 3h7l4 4v14H7zM14 3v5h4M10 13h5M10 17h3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg x-show="downloadingPdf" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                        </svg>
                        <span x-text="downloadingPdf ? 'Menyiapkan…' : (pdfDownloaded ? 'PDF terunduh' : 'Unduh PDF')"></span>
                    </button>
                    <button
                        type="button"
                        class="hidden items-center gap-2 rounded-lg border border-brand-300 bg-white px-3.5 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50 lg:inline-flex"
                        onclick="window.print()"
                        aria-label="Cetak invoice"
                    >
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z" stroke-linejoin="round"/>
                        </svg>
                        <span class="hidden sm:inline">Cetak</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-3 border-t border-line bg-white lg:hidden">
                <button
                    type="button"
                    data-testid="preview-email-action-mobile"
                    class="inline-flex items-center justify-center gap-2 px-2 py-2.5 text-xs font-semibold text-brand-800 hover:bg-brand-50 disabled:cursor-wait disabled:opacity-70 sm:text-sm"
                    :disabled="sendingEmail"
                    :aria-busy="sendingEmail"
                    @click="sendEmail()"
                >
                    <svg x-show="! sendingEmail" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 5h16v14H4zM4 7l8 6 8-6" stroke-linejoin="round"/>
                    </svg>
                    <svg x-show="sendingEmail" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                    </svg>
                    <span x-text="sendingEmail ? 'Mengirim…' : (invoiceStatus === 'Terkirim' ? 'Terkirim' : 'Kirim email')"></span>
                </button>
                <button
                    type="button"
                    data-testid="preview-pdf-action-mobile"
                    class="inline-flex items-center justify-center gap-2 border-x border-line px-2 py-2.5 text-xs font-semibold text-brand-800 hover:bg-brand-50 disabled:cursor-wait disabled:opacity-70 sm:text-sm"
                    :disabled="downloadingPdf"
                    :aria-busy="downloadingPdf"
                    @click="downloadPdf()"
                >
                    <svg x-show="! downloadingPdf" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M7 3h7l4 4v14H7zM14 3v5h4M10 13h5M10 17h3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <svg x-show="downloadingPdf" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                    </svg>
                    <span x-text="downloadingPdf ? 'Menyiapkan…' : (pdfDownloaded ? 'Terunduh' : 'Unduh PDF')"></span>
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 px-2 py-2.5 text-xs font-semibold text-brand-800 hover:bg-brand-50 sm:text-sm"
                    onclick="window.print()"
                    aria-label="Cetak invoice"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z" stroke-linejoin="round"/>
                    </svg>
                    Cetak
                </button>
            </div>
        </header>

        <main class="invoice-preview-shell px-3 py-6 sm:px-6 sm:py-10">
            <article class="invoice-paper mx-auto max-w-[900px] overflow-hidden rounded-xl bg-white shadow-[0_4px_8px_oklch(0.2_0.02_113/0.12)]" aria-label="Invoice INV-2026-0079">
                <div class="h-2 bg-brand-700"></div>
                <div class="p-5 sm:p-8 md:p-10">
                    <div class="flex flex-col gap-8 border-b border-line pb-8 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <div class="grid size-12 place-items-center rounded-lg bg-brand-700 text-sm font-bold text-white" aria-hidden="true">RK</div>
                                <div>
                                    <p class="text-lg font-bold tracking-[-0.02em] text-ink">Ruang Karya Digital</p>
                                    <p class="mt-0.5 text-xs text-muted">Strategi, desain, dan teknologi</p>
                                </div>
                            </div>
                            <address class="mt-5 max-w-xs text-xs not-italic leading-5 text-muted sm:text-sm sm:leading-6">
                                Jl. Kemang Raya No. 24, Jakarta Selatan 12730<br>
                                halo@ruangkarya.id · +62 21 7179 880<br>
                                NPWP 01.234.567.8-012.000
                            </address>
                        </div>

                        <div class="text-left sm:text-right">
                            <p class="text-3xl font-bold tracking-[-0.035em] text-brand-800">INVOICE</p>
                            <p class="mt-2 font-mono text-sm font-semibold text-ink">INV-2026-0079</p>
                            <span
                                class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                                :class="invoiceStatus === 'Terkirim' ? 'bg-green-100 text-green-800' : 'bg-brand-100 text-brand-800'"
                                x-text="invoiceStatus"
                                data-testid="preview-invoice-status"
                            ></span>
                        </div>
                    </div>

                    <div class="grid gap-8 border-b border-line py-8 sm:grid-cols-[minmax(0,1fr)_17rem]">
                        <div>
                            <p class="text-xs font-semibold text-muted">Ditagihkan kepada</p>
                            <h1 class="mt-2 text-lg font-semibold text-ink">PT Sinar Nusantara</h1>
                            <address class="mt-2 max-w-sm text-sm not-italic leading-6 text-muted">
                                Jl. Jenderal Sudirman No. 88<br>
                                Jakarta Selatan 12190<br>
                                finance@sinarnusantara.co.id · +62 21 555 0198
                            </address>
                        </div>

                        <dl class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-3 text-sm">
                            <dt class="text-muted">Tanggal invoice</dt>
                            <dd class="font-medium text-ink">23 Juli 2026</dd>
                            <dt class="text-muted">Jatuh tempo</dt>
                            <dd class="font-semibold text-red-700">6 Agustus 2026</dd>
                            <dt class="text-muted">Termin</dt>
                            <dd class="font-medium text-ink">14 hari</dd>
                            <dt class="text-muted">Mata uang</dt>
                            <dd class="font-medium text-ink">IDR</dd>
                        </dl>
                    </div>

                    <div class="py-8">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[580px] text-left text-xs sm:text-sm">
                                <thead>
                                    <tr class="border-b-2 border-brand-700 text-xs font-semibold text-muted">
                                        <th class="pb-3 pr-4">Deskripsi</th>
                                        <th class="w-16 px-2 pb-3 text-center">Jumlah</th>
                                        <th class="w-32 px-2 pb-3 text-right">Harga</th>
                                        <th class="w-32 pb-3 pl-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($items as $item)
                                        <tr>
                                            <td class="py-5 pr-4 align-top">
                                                <p class="font-semibold text-ink">{{ $item['name'] }}</p>
                                                <p class="mt-1 max-w-md text-xs leading-5 text-muted">{{ $item['description'] }}</p>
                                            </td>
                                            <td class="px-2 py-5 text-center align-top text-ink">{{ $item['quantity'] }}</td>
                                            <td class="px-2 py-5 text-right align-top text-muted">{{ $item['price'] }}</td>
                                            <td class="py-5 pl-2 text-right align-top font-semibold text-ink">{{ $item['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <dl class="w-full max-w-sm space-y-3 text-sm">
                                <div class="flex justify-between gap-6">
                                    <dt class="text-muted">Subtotal</dt>
                                    <dd class="font-medium text-ink">Rp21.250.000</dd>
                                </div>
                                <div class="flex justify-between gap-6">
                                    <dt class="text-muted">Diskon (5%)</dt>
                                    <dd class="font-medium text-red-700">− Rp1.062.500</dd>
                                </div>
                                <div class="flex justify-between gap-6">
                                    <dt class="text-muted">PPN (11%)</dt>
                                    <dd class="font-medium text-ink">Rp2.220.625</dd>
                                </div>
                                <div class="flex items-end justify-between gap-6 border-t-2 border-brand-700 pt-4">
                                    <dt class="font-semibold text-ink">Total tagihan</dt>
                                    <dd class="text-xl font-bold tracking-[-0.025em] text-brand-800">Rp22.408.125</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div class="grid gap-6 border-t border-line pt-8 sm:grid-cols-2">
                        <section aria-labelledby="payment-heading">
                            <h2 id="payment-heading" class="text-sm font-semibold text-ink">Instruksi pembayaran</h2>
                            <div class="mt-3 rounded-lg bg-canvas p-4 text-sm">
                                <div class="flex justify-between gap-4">
                                    <span class="text-muted">Bank</span>
                                    <span class="font-medium text-ink">Bank Central Asia</span>
                                </div>
                                <div class="mt-2 flex justify-between gap-4">
                                    <span class="text-muted">No. rekening</span>
                                    <span class="font-mono font-semibold text-ink">012 345 6789</span>
                                </div>
                                <div class="mt-2 flex justify-between gap-4">
                                    <span class="text-muted">Atas nama</span>
                                    <span class="font-medium text-ink">PT Ruang Karya Digital</span>
                                </div>
                            </div>
                        </section>

                        <section aria-labelledby="notes-heading">
                            <h2 id="notes-heading" class="text-sm font-semibold text-ink">Catatan</h2>
                            <p class="mt-3 text-sm leading-6 text-muted">
                                Terima kasih telah mempercayakan kebutuhan bisnis Anda kepada kami. Mohon cantumkan nomor invoice pada berita transfer.
                            </p>
                        </section>
                    </div>

                    <footer class="mt-10 flex flex-col gap-2 border-t border-line pt-5 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
                        <p>Invoice ini dibuat secara elektronik dan sah tanpa tanda tangan.</p>
                        <p class="font-medium text-brand-800">YokPrinting.ID</p>
                    </footer>
                </div>
            </article>

            <p class="print-hide mx-auto mt-4 max-w-[900px] text-center text-xs text-muted">
                Pratinjau menggunakan data tiruan. Periksa kembali detail sebelum invoice dikirim.
            </p>
        </main>

        <div
            x-cloak
            x-show="notice"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition duration-150 ease-out"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-2 opacity-0"
            class="print-hide fixed bottom-4 left-4 right-4 z-50 mx-auto flex max-w-md items-start gap-3 rounded-xl p-4 text-white shadow-[0_4px_8px_oklch(0.2_0.02_113/0.22)] sm:left-auto sm:right-6"
            :class="notice?.type === 'error' ? 'bg-red-900' : 'bg-brand-900'"
            :role="notice?.type === 'error' ? 'alert' : 'status'"
            aria-live="polite"
            data-testid="preview-action-notice"
        >
            <span
                class="grid size-8 shrink-0 place-items-center rounded-full"
                :class="notice?.type === 'error' ? 'bg-red-200 text-red-900' : 'bg-brand-300 text-brand-900'"
            >
                <svg x-show="notice?.type !== 'error'" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg x-show="notice?.type === 'error'" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M7 7l10 10M17 7 7 17" stroke-linecap="round"/>
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold" x-text="notice?.title"></p>
                <p class="mt-1 text-xs leading-5 text-brand-100" x-text="notice?.description"></p>
            </div>
            <button type="button" class="rounded-lg p-1.5 text-brand-100 hover:bg-white/10 hover:text-white" @click="notice = null" aria-label="Tutup notifikasi">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </body>
</html>
