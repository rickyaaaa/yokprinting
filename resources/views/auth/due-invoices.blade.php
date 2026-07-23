@php
    $dueInvoices = [
        ['invoice' => 'INV-2026-0078', 'customer' => 'PT Bumi Lestari', 'amount' => 'Rp5.600.000', 'due' => '21 Jul 2026', 'days' => 'Lewat 3 hari', 'status' => 'Overdue', 'owner' => 'Maya Lestari', 'lastFollowUp' => 'Email dikirim 22 Jul', 'nextAction' => 'Telepon finance'],
        ['invoice' => 'INV-2026-0084', 'customer' => 'PT Sinar Nusantara', 'amount' => 'Rp18.450.000', 'due' => '25 Jul 2026', 'days' => 'Besok', 'status' => 'Due soon', 'owner' => 'Andi Pratama', 'lastFollowUp' => 'Belum ada', 'nextAction' => 'Kirim reminder'],
        ['invoice' => 'INV-2026-0082', 'customer' => 'CV Lautan Rasa', 'amount' => 'Rp12.750.000', 'due' => '29 Jul 2026', 'days' => '5 hari lagi', 'status' => 'Scheduled', 'owner' => 'Riko Saputra', 'lastFollowUp' => 'WhatsApp 23 Jul', 'nextAction' => 'Jadwalkan follow-up'],
        ['invoice' => 'INV-2026-0081', 'customer' => 'PT Nusa Digital', 'amount' => 'Rp9.200.000', 'due' => '31 Jul 2026', 'days' => '7 hari lagi', 'status' => 'Scheduled', 'owner' => 'Maya Lestari', 'lastFollowUp' => 'Email 24 Jul', 'nextAction' => 'Pantau pembayaran'],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Daftar invoice jatuh tempo YokPrinting.ID">

        <title>Invoice Jatuh Tempo - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="min-h-screen bg-canvas">
            <header class="border-b border-line bg-white/95 px-4 py-4 backdrop-blur-sm sm:px-6 lg:px-8">
                <div class="mx-auto flex w-full max-w-[1280px] items-center justify-between gap-4">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="m15 18-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Kembali ke dashboard
                    </a>
                    <a href="{{ route('payments.receivables.index') }}" class="hidden text-sm font-semibold text-muted hover:text-ink sm:inline">Halaman piutang</a>
                </div>
            </header>

            <div class="mx-auto w-full max-w-[1280px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Dari notifikasi</span>
                            <span class="rounded-full bg-accent-soft px-2.5 py-1 text-xs font-medium text-accent">Data tiruan</span>
                        </div>
                        <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar invoice jatuh tempo</h1>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-muted">Daftar tindak lanjut dari notifikasi dashboard untuk invoice overdue dan invoice yang segera jatuh tempo.</p>
                    </div>
                    <button type="button" class="inline-flex w-fit items-center rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800" data-testid="bulk-reminder-placeholder">
                        Kirim reminder massal
                    </button>
                </div>

                <section class="mb-6 grid gap-4 md:grid-cols-4" aria-label="Ringkasan invoice jatuh tempo">
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Total perlu follow-up</p>
                        <p class="mt-2 text-2xl font-semibold text-ink">4</p>
                    </article>
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Overdue</p>
                        <p class="mt-2 text-2xl font-semibold text-red-800">1</p>
                    </article>
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Jatuh tempo 7 hari</p>
                        <p class="mt-2 text-2xl font-semibold text-yellow-900">3</p>
                    </article>
                    <article class="rounded-xl bg-white p-5 border border-line">
                        <p class="text-xs font-semibold text-muted">Nilai outstanding</p>
                        <p class="mt-2 text-2xl font-semibold text-ink">Rp45,9 jt</p>
                    </article>
                </section>

                <section class="rounded-xl bg-white border border-line" aria-labelledby="due-invoice-table-heading" x-data="{ status: 'all', owner: 'all' }">
                    <div class="border-b border-line px-5 py-4 sm:px-6">
                        <h2 id="due-invoice-table-heading" class="font-semibold text-ink">Antrian follow-up invoice</h2>
                        <p class="mt-1 text-sm text-muted">Filter visual disiapkan untuk endpoint notifikasi jatuh tempo pada backend.</p>
                    </div>

                    <div class="grid gap-4 border-b border-line p-5 sm:grid-cols-2 lg:grid-cols-4 sm:p-6">
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Cari invoice/customer</span>
                            <input class="form-control mt-1.5" placeholder="INV-2026 atau nama customer" data-testid="due-invoice-search">
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Status</span>
                            <select class="form-control mt-1.5" x-model="status" data-testid="due-invoice-status-filter">
                                <option value="all">Semua status</option>
                                <option>Overdue</option>
                                <option>Due soon</option>
                                <option>Scheduled</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">PIC follow-up</span>
                            <select class="form-control mt-1.5" x-model="owner" data-testid="due-invoice-owner-filter">
                                <option value="all">Semua PIC</option>
                                <option>Andi Pratama</option>
                                <option>Maya Lestari</option>
                                <option>Riko Saputra</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-medium text-ink">Urutkan</span>
                            <select class="form-control mt-1.5" data-testid="due-invoice-sort">
                                <option>Jatuh tempo terdekat</option>
                                <option>Nominal terbesar</option>
                                <option>Follow-up terakhir</option>
                            </select>
                        </label>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-line text-left text-sm">
                            <thead class="bg-canvas text-xs font-semibold text-muted">
                                <tr>
                                    <th scope="col" class="px-5 py-3 sm:px-6">Invoice</th>
                                    <th scope="col" class="px-4 py-3">Customer</th>
                                    <th scope="col" class="px-4 py-3">Jatuh tempo</th>
                                    <th scope="col" class="px-4 py-3">Nominal</th>
                                    <th scope="col" class="px-4 py-3">PIC</th>
                                    <th scope="col" class="px-4 py-3">Follow-up</th>
                                    <th scope="col" class="px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($dueInvoices as $invoice)
                                    <tr>
                                        <td class="px-5 py-4 sm:px-6">
                                            <span class="block font-semibold text-ink">{{ $invoice['invoice'] }}</span>
                                            <span class="mt-1 inline-flex rounded-full {{ $invoice['status'] === 'Overdue' ? 'bg-red-100 text-red-800' : ($invoice['status'] === 'Due soon' ? 'bg-yellow-100 text-yellow-900' : 'bg-accent-soft text-accent') }} px-2.5 py-1 text-xs font-semibold">{{ $invoice['status'] }}</span>
                                        </td>
                                        <td class="px-4 py-4 font-semibold text-ink">{{ $invoice['customer'] }}</td>
                                        <td class="px-4 py-4">
                                            <span class="block text-ink">{{ $invoice['due'] }}</span>
                                            <span class="mt-1 block text-xs text-muted">{{ $invoice['days'] }}</span>
                                        </td>
                                        <td class="px-4 py-4 font-semibold text-ink">{{ $invoice['amount'] }}</td>
                                        <td class="px-4 py-4 text-muted">{{ $invoice['owner'] }}</td>
                                        <td class="px-4 py-4">
                                            <span class="block text-xs text-muted">{{ $invoice['lastFollowUp'] }}</span>
                                            <span class="mt-1 block font-semibold text-ink">{{ $invoice['nextAction'] }}</span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <button type="button" class="rounded-lg border border-line bg-white px-3 py-1.5 text-xs font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                                Tandai follow-up
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>
