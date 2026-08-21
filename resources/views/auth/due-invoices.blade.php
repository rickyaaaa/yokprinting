<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Daftar invoice jatuh tempo YokPrinting.ID">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Invoice Jatuh Tempo - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="min-h-screen bg-canvas">
            <header class="border-b border-line bg-white/95 px-4 py-4 backdrop-blur-sm sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-[1280px] items-center justify-between gap-4">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                        <i class="iconify tabler--chevron-left text-base"></i>
                        Kembali ke dashboard
                    </a>
                    <a href="{{ route('payments.receivables.index') }}" class="hidden text-sm font-semibold text-muted hover:text-ink sm:inline">Halaman piutang</a>
                </div>
            </header>

            <div class="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="badge badge-danger">Dari notifikasi</span>
                        </div>
                        <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar invoice jatuh tempo</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">Daftar tindak lanjut dari notifikasi dashboard untuk invoice overdue dan invoice yang segera jatuh tempo.</p>
                    </div>
                </div>

                <section class="mb-6 grid gap-4 md:grid-cols-4" aria-label="Ringkasan invoice jatuh tempo">
                    @foreach ($summaryCards as $card)
                        <article class="card p-5">
                            <p class="text-xs font-semibold text-muted">{{ $card['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold {{ ($card['tone'] ?? null) === 'danger' ? 'text-red-800' : 'text-ink' }}">{{ $card['value'] }}</p>
                        </article>
                    @endforeach
                </section>

                <section
                    class="card"
                    aria-labelledby="due-invoice-table-heading"
                    x-data='dueInvoiceFollowUpTable(@json($dueInvoices))'
                >
                    <div class="border-b border-line px-5 py-4 sm:px-6">
                        <h2 id="due-invoice-table-heading" class="font-semibold text-ink">Antrian follow-up invoice</h2>
                        <p class="mt-1 text-sm text-muted">Diambil langsung dari invoice yang sudah terkirim dan belum lunas.</p>
                    </div>

                    <div
                        x-show="message"
                        x-cloak
                        x-text="message"
                        class="mx-5 mt-4 rounded-lg px-3.5 py-2.5 text-sm font-medium sm:mx-6"
                        :class="messageTone === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                    ></div>

                    <div class="grid gap-4 border-b border-line p-5 sm:grid-cols-2 sm:p-6">
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Cari invoice/customer</span>
                            <input type="search" class="form-control mt-1.5" placeholder="INV-2026 atau nama customer" x-model.debounce.150ms="query" data-testid="due-invoice-search">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Status</span>
                            <select class="form-control mt-1.5" x-model="statusFilter" data-testid="due-invoice-status-filter">
                                <option value="all">Semua status</option>
                                <option>Overdue</option>
                                <option>Due soon</option>
                                <option>Scheduled</option>
                            </select>
                        </label>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-left text-sm">
                            <thead class="bg-canvas text-xs font-semibold text-muted">
                                <tr>
                                    <th scope="col" class="px-5 py-3 sm:px-6">
                                        <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('invoice')">
                                            Invoice
                                            <span class="font-mono text-[0.65rem]" x-text="sortIndicator('invoice')"></span>
                                        </button>
                                    </th>
                                    <th scope="col" class="px-4 py-3">Customer</th>
                                    <th scope="col" class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('dueSort')">
                                            Jatuh tempo
                                            <span class="font-mono text-[0.65rem]" x-text="sortIndicator('dueSort')"></span>
                                        </button>
                                    </th>
                                    <th scope="col" class="px-4 py-3">
                                        <button type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy('outstandingValue')">
                                            Nominal
                                            <span class="font-mono text-[0.65rem]" x-text="sortIndicator('outstandingValue')"></span>
                                        </button>
                                    </th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="px-4 py-3">Follow-up</th>
                                    <th scope="col" class="px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                <template x-for="invoice in filteredInvoices" :key="invoice.invoice">
                                    <tr>
                                        <td class="px-5 py-4 font-mono text-xs font-semibold text-brand-800 sm:px-6">
                                            <a :href="`/payments/invoices/${invoice.invoice}`" class="hover:text-brand-900" x-text="invoice.invoice"></a>
                                        </td>
                                        <td class="px-4 py-4 font-semibold text-ink" x-text="invoice.customer"></td>
                                        <td class="px-4 py-4">
                                            <span class="block text-ink" x-text="invoice.due"></span>
                                            <span class="mt-1 block text-xs text-muted" x-text="invoice.days"></span>
                                        </td>
                                        <td class="px-4 py-4 font-semibold text-ink" x-text="invoice.amount"></td>
                                        <td class="px-4 py-4">
                                            <span class="badge" :class="statusClass(invoice.status)" x-text="invoice.status"></span>
                                        </td>
                                        <td class="px-4 py-4 text-xs text-muted" x-text="invoice.lastFollowUpLabel"></td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col items-start gap-1">
                                                <a :href="`/payments/invoices/${invoice.invoice}`" class="btn btn-sm btn-outline">
                                                    Lihat invoice
                                                </a>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline disabled:cursor-not-allowed"
                                                    :disabled="markingInvoice === invoice.invoice"
                                                    @click="markFollowUp(invoice)"
                                                >
                                                    <span x-text="markingInvoice === invoice.invoice ? 'Menyimpan...' : 'Tandai follow-up'"></span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="filteredInvoices.length === 0">
                                    <td colspan="7" class="px-5 py-10 text-center text-sm text-muted sm:px-6">
                                        Tidak ada invoice yang cocok dengan filter saat ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>
