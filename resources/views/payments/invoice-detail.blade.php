@php
    $invoice = [
        'number' => 'INV-2026-0084',
        'status' => 'Menunggu pembayaran',
        'customer' => 'PT Sinar Nusantara',
        'email' => 'finance@sinarnusantara.co.id',
        'address' => 'Jl. Jenderal Sudirman No. 88, Jakarta Selatan',
        'issued_at' => '23 Juli 2026',
        'due_at' => '30 Juli 2026',
        'terms' => 'Net 7',
        'subtotal' => 'Rp18.000.000',
        'tax' => 'Rp1.980.000',
        'discount' => '- Rp1.530.000',
        'total' => 'Rp18.450.000',
        'paid' => 'Rp12.000.000',
        'remaining' => 'Rp6.450.000',
        'progress' => 65,
    ];

    $items = [
        ['name' => 'Paket desain brand refresh', 'quantity' => 1, 'price' => 'Rp12.000.000', 'total' => 'Rp12.000.000'],
        ['name' => 'Cetak katalog premium 500 eksemplar', 'quantity' => 1, 'price' => 'Rp6.000.000', 'total' => 'Rp6.000.000'],
    ];

    $payments = [
        ['date' => '24 Juli 2026', 'method' => 'Transfer BCA', 'reference' => 'BCA-77219', 'amount' => 'Rp8.000.000', 'status' => 'Terverifikasi'],
        ['date' => '26 Juli 2026', 'method' => 'Transfer BCA', 'reference' => 'BCA-77302', 'amount' => 'Rp4.000.000', 'status' => 'Terverifikasi'],
    ];

    $paymentMethods = [
        ['label' => 'Bank', 'value' => 'Bank Central Asia'],
        ['label' => 'No. rekening', 'value' => '012 345 6789'],
        ['label' => 'Atas nama', 'value' => 'PT Ruang Karya Digital'],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Detail invoice dan pembayaran YokPrinting.ID">

        <title>Detail {{ $invoice['number'] }} - YokPrinting.ID</title>

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
                        class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800 lg:hidden"
                        @click="sidebarOpen = true"
                        aria-controls="app-sidebar"
                        :aria-expanded="sidebarOpen"
                        aria-label="Buka navigasi"
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
                        </svg>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('dashboard') }}" class="hidden text-muted hover:text-ink sm:inline">Dashboard</a>
                        <svg class="hidden size-4 text-line sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate font-medium text-ink">Detail pembayaran</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" class="relative rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800" aria-label="Lihat notifikasi">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">Kamis, 23 Juli 2026</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Manajemen pembayaran</span>
                                <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-900">{{ $invoice['status'] }}</span>
                                <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent">Data tiruan</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Detail {{ $invoice['number'] }}</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau rincian tagihan, pembayaran masuk, dan sisa outstanding untuk invoice ini.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('invoices.preview') }}" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M7 3h7l4 4v14H7zM14 3v5h4M10 13h5M10 17h3" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Lihat invoice
                            </a>
                            <a href="#record-payment" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                </svg>
                                Catat pembayaran
                            </a>
                        </div>
                    </div>

                    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <div class="min-w-0 space-y-6">
                            <section class="rounded-xl bg-white border border-line" aria-labelledby="invoice-info-heading">
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 id="invoice-info-heading" class="font-semibold text-ink">Informasi invoice</h2>
                                    <p class="mt-1 text-sm text-muted">Detail pelanggan, tanggal, dan termin pembayaran.</p>
                                </div>
                                <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold text-muted">Ditagihkan kepada</p>
                                        <h3 class="mt-2 text-lg font-semibold text-ink">{{ $invoice['customer'] }}</h3>
                                        <p class="mt-2 text-sm leading-6 text-muted">{{ $invoice['address'] }}<br>{{ $invoice['email'] }}</p>
                                    </div>
                                    <dl class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-3 text-sm">
                                        <dt class="text-muted">Nomor invoice</dt>
                                        <dd class="font-mono font-semibold text-ink">{{ $invoice['number'] }}</dd>
                                        <dt class="text-muted">Tanggal invoice</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['issued_at'] }}</dd>
                                        <dt class="text-muted">Jatuh tempo</dt>
                                        <dd class="font-semibold text-red-700">{{ $invoice['due_at'] }}</dd>
                                        <dt class="text-muted">Termin</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['terms'] }}</dd>
                                    </dl>
                                </div>
                            </section>

                            <section class="rounded-xl bg-white border border-line" aria-labelledby="invoice-items-heading">
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 id="invoice-items-heading" class="font-semibold text-ink">Rincian tagihan</h2>
                                    <p class="mt-1 text-sm text-muted">Item invoice dan total yang perlu diselesaikan.</p>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[620px] text-left text-sm">
                                        <thead>
                                            <tr class="border-b border-line text-xs font-semibold text-muted">
                                                <th class="px-5 py-3 sm:px-6">Item</th>
                                                <th class="px-5 py-3 text-center">Jumlah</th>
                                                <th class="px-5 py-3 text-right">Harga</th>
                                                <th class="px-5 py-3 text-right sm:px-6">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-line">
                                            @foreach ($items as $item)
                                                <tr>
                                                    <td class="px-5 py-4 font-medium text-ink sm:px-6">{{ $item['name'] }}</td>
                                                    <td class="px-5 py-4 text-center text-muted">{{ $item['quantity'] }}</td>
                                                    <td class="px-5 py-4 text-right text-muted">{{ $item['price'] }}</td>
                                                    <td class="px-5 py-4 text-right font-semibold text-ink sm:px-6">{{ $item['total'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <dl class="ml-auto w-full max-w-sm space-y-3 px-5 py-5 text-sm sm:px-6">
                                    <div class="flex justify-between gap-6">
                                        <dt class="text-muted">Subtotal</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['subtotal'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-6">
                                        <dt class="text-muted">Diskon</dt>
                                        <dd class="font-medium text-red-700">{{ $invoice['discount'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-6">
                                        <dt class="text-muted">PPN 11%</dt>
                                        <dd class="font-medium text-ink">{{ $invoice['tax'] }}</dd>
                                    </div>
                                    <div class="flex items-end justify-between gap-6 border-t border-line pt-4">
                                        <dt class="font-semibold text-ink">Total invoice</dt>
                                        <dd class="text-lg font-bold tracking-[-0.025em] text-brand-800">{{ $invoice['total'] }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section
                                id="record-payment"
                                class="rounded-xl bg-white border border-line"
                                aria-labelledby="record-payment-heading"
                                x-data="recordPaymentForm"
                            >
                                <div class="border-b border-line px-5 py-4 sm:px-6">
                                    <h2 id="record-payment-heading" class="font-semibold text-ink">Catat pembayaran</h2>
                                    <p class="mt-1 text-sm text-muted">Form data tiruan untuk mencatat pembayaran masuk invoice ini.</p>
                                </div>

                                <form class="space-y-5 p-5 sm:p-6" action="#" method="post" novalidate @submit.prevent="submit()">
                                    <div
                                        x-cloak
                                        x-show="validationMessages.length > 0"
                                        class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900"
                                        role="alert"
                                        data-testid="payment-validation-summary"
                                    >
                                        <p class="font-semibold">Periksa data pembayaran</p>
                                        <ul class="mt-2 list-disc space-y-1 pl-5">
                                            <template x-for="message in validationMessages" :key="message">
                                                <li x-text="message"></li>
                                            </template>
                                        </ul>
                                    </div>

                                    <div
                                        x-cloak
                                        x-show="savedPayment"
                                        x-transition.opacity
                                        class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900"
                                        role="status"
                                        data-testid="payment-saved-notice"
                                    >
                                        <p class="font-semibold">Pembayaran tiruan tercatat</p>
                                        <p class="mt-1 leading-5" x-text="`${savedPayment.amount} via ${savedPayment.method} dengan referensi ${savedPayment.reference}.`"></p>
                                    </div>

                                    <div class="grid gap-5 md:grid-cols-2">
                                        <div>
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <label for="payment-amount" class="block text-sm font-medium text-ink">Nominal pembayaran</label>
                                                <button type="button" class="text-xs font-semibold text-brand-800 hover:text-brand-900" @click="useRemainingAmount()">Gunakan sisa</button>
                                            </div>
                                            <input
                                                id="payment-amount"
                                                name="amount"
                                                type="number"
                                                min="1"
                                                step="1000"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.amount }"
                                                x-model.number="form.amount"
                                                @input="clearFieldError('amount')"
                                                aria-describedby="payment-amount-help payment-amount-error"
                                            >
                                            <p id="payment-amount-help" class="mt-1.5 text-xs text-muted">Sisa tagihan: {{ $invoice['remaining'] }}. Nilai saat ini: <span x-text="formattedAmount"></span>.</p>
                                            <p id="payment-amount-error" x-show="fieldErrors.amount" x-text="fieldErrors.amount" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>

                                        <div>
                                            <label for="payment-date" class="mb-2 block text-sm font-medium text-ink">Tanggal pembayaran</label>
                                            <input
                                                id="payment-date"
                                                name="paid_at"
                                                type="date"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.paidAt }"
                                                x-model="form.paidAt"
                                                @input="clearFieldError('paidAt')"
                                                aria-describedby="payment-date-error"
                                            >
                                            <p id="payment-date-error" x-show="fieldErrors.paidAt" x-text="fieldErrors.paidAt" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>

                                        <div>
                                            <label for="payment-method" class="mb-2 block text-sm font-medium text-ink">Metode pembayaran</label>
                                            <select
                                                id="payment-method"
                                                name="method"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.method }"
                                                x-model="form.method"
                                                @change="clearFieldError('method')"
                                                aria-describedby="payment-method-error"
                                            >
                                                <option>Transfer BCA</option>
                                                <option>Transfer Mandiri</option>
                                                <option>Kartu kredit</option>
                                                <option>Tunai</option>
                                            </select>
                                            <p id="payment-method-error" x-show="fieldErrors.method" x-text="fieldErrors.method" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>

                                        <div>
                                            <label for="payment-reference" class="mb-2 block text-sm font-medium text-ink">Nomor referensi</label>
                                            <input
                                                id="payment-reference"
                                                name="reference"
                                                type="text"
                                                class="form-control"
                                                :class="{ 'border-red-400 ring-2 ring-red-100': fieldErrors.reference }"
                                                x-model="form.reference"
                                                @input="clearFieldError('reference')"
                                                aria-describedby="payment-reference-error"
                                            >
                                            <p id="payment-reference-error" x-show="fieldErrors.reference" x-text="fieldErrors.reference" class="mt-1.5 text-xs font-medium text-red-700"></p>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="payment-notes" class="mb-2 block text-sm font-medium text-ink">Catatan internal</label>
                                        <textarea id="payment-notes" name="notes" rows="3" class="form-control min-h-24" x-model="form.notes"></textarea>
                                    </div>

                                    <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-xs leading-5 text-muted">Form ini belum menyimpan ke backend. Data dipakai untuk verifikasi alur frontend pembayaran.</p>
                                        <button
                                            type="submit"
                                            class="inline-flex min-w-40 items-center justify-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800 disabled:cursor-wait disabled:opacity-70"
                                            :disabled="saving"
                                        >
                                            <svg x-show="! saving" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <svg x-show="saving" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke-linecap="round"/>
                                            </svg>
                                            <span x-text="saving ? 'Mencatat...' : 'Simpan pembayaran'"></span>
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </div>

                        <aside class="space-y-6 xl:sticky xl:top-22" aria-label="Section pembayaran">
                            <section class="rounded-xl bg-white p-5 border border-line sm:p-6" aria-labelledby="payment-summary-heading">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 id="payment-summary-heading" class="font-semibold text-ink">Pembayaran</h2>
                                        <p class="mt-1 text-sm text-muted">Status pelunasan invoice ini.</p>
                                    </div>
                                    <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-900">Outstanding</span>
                                </div>

                                <div class="mt-6">
                                    <div class="flex items-end justify-between gap-4">
                                        <div>
                                            <p class="text-xs font-semibold text-muted">Sudah dibayar</p>
                                            <p class="mt-1 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $invoice['paid'] }}</p>
                                        </div>
                                        <p class="text-sm font-semibold text-brand-800">{{ $invoice['progress'] }}%</p>
                                    </div>
                                    <div class="mt-4 h-3 rounded-full bg-canvas">
                                        <div class="h-3 rounded-full bg-brand-600" style="width: {{ $invoice['progress'] }}%"></div>
                                    </div>
                                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">
                                        <p class="text-xs font-semibold text-red-800">Sisa tagihan</p>
                                        <p class="mt-1 text-xl font-bold tracking-[-0.025em] text-red-900">{{ $invoice['remaining'] }}</p>
                                        <p class="mt-2 text-xs leading-5 text-red-800">Jatuh tempo {{ $invoice['due_at'] }}. Kirim pengingat bila belum ada pembayaran lanjutan.</p>
                                    </div>
                                </div>

                                <div class="mt-5 grid gap-2">
                                    <a href="#record-payment" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                        </svg>
                                        Catat pembayaran
                                    </a>
                                    <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg border border-brand-300 bg-white px-3.5 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="M4 5h16v14H4zM4 7l8 6 8-6" stroke-linejoin="round"/>
                                        </svg>
                                        Kirim pengingat
                                    </button>
                                </div>
                            </section>

                            <section class="rounded-xl bg-white border border-line" aria-labelledby="payment-history-heading">
                                <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
                                    <div>
                                        <h2 id="payment-history-heading" class="font-semibold text-ink">Riwayat pembayaran</h2>
                                        <p class="mt-1 text-sm text-muted">Pembayaran yang sudah dicatat.</p>
                                    </div>
                                    <a href="{{ route('payments.history.index') }}" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-brand-800 hover:bg-brand-50">Lihat semua</a>
                                </div>
                                <ol class="divide-y divide-line">
                                    @foreach ($payments as $payment)
                                        <li class="px-5 py-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-sm font-semibold text-ink">{{ $payment['method'] }}</p>
                                                    <p class="mt-1 text-xs text-muted">{{ $payment['date'] }} - {{ $payment['reference'] }}</p>
                                                </div>
                                                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">{{ $payment['status'] }}</span>
                                            </div>
                                            <p class="mt-3 text-sm font-semibold text-brand-800">{{ $payment['amount'] }}</p>
                                        </li>
                                    @endforeach
                                </ol>
                            </section>

                            <section class="rounded-xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-900" aria-labelledby="payment-method-heading">
                                <h2 id="payment-method-heading" class="font-semibold">Instruksi pembayaran</h2>
                                <dl class="mt-4 space-y-3">
                                    @foreach ($paymentMethods as $method)
                                        <div class="flex justify-between gap-4">
                                            <dt class="text-brand-800">{{ $method['label'] }}</dt>
                                            <dd class="text-right font-semibold">{{ $method['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </section>
                        </aside>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
