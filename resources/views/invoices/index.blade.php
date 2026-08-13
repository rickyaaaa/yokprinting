<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Daftar invoice YokPrinting.ID">

        <title>Daftar Invoice - YokPrinting.ID</title>

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

            <div class="min-w-0 flex-1" x-data="{ query: '', status: 'all', invoices: @js($invoiceRows) }">
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
                        <span class="truncate font-medium text-ink">Daftar invoice</span>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" class="relative rounded-lg p-2 text-muted hover:bg-brand-50 hover:text-brand-800" aria-label="Lihat notifikasi">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="absolute right-1.5 top-1.5 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
                        </button>
                        <span class="hidden h-6 w-px bg-line sm:block"></span>
                        <span class="hidden text-sm text-muted sm:inline">{{ now(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y') }}</span>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Daftar invoice</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Daftar Invoice</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau invoice terbit, status pembayaran, jatuh tempo, dan akses cepat ke detail pembayaran.</p>
                        </div>
                        <a href="{{ route('invoices.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                            </svg>
                            Buat invoice
                        </a>
                    </div>

                    <section class="grid gap-4 md:grid-cols-3" aria-label="Ringkasan daftar invoice">
                        @foreach ($summaryCards as $card)
                            <article class="rounded-xl border border-line bg-white p-5">
                                <p class="text-sm font-medium text-muted">{{ $card['label'] }}</p>
                                <p class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $card['value'] }}</p>
                                <p class="mt-3 text-xs text-muted">{{ $card['caption'] }}</p>
                            </article>
                        @endforeach
                    </section>

                    <section class="mt-6 rounded-xl border border-line bg-white" aria-labelledby="invoice-table-heading">
                        <div class="flex flex-col gap-4 border-b border-line px-5 py-4 sm:px-6 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 id="invoice-table-heading" class="font-semibold text-ink">Semua invoice</h2>
                                <p class="mt-1 text-sm text-muted">Cari berdasarkan nomor invoice, pelanggan, email, atau status.</p>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                <label class="sr-only" for="invoice-search">Cari invoice</label>
                                <input id="invoice-search" x-model.debounce.150ms="query" type="search" class="form-control sm:w-72" placeholder="Cari invoice atau pelanggan">
                                <label class="sr-only" for="invoice-status">Filter status</label>
                                <select id="invoice-status" x-model="status" class="form-control sm:w-44">
                                    <option value="all">Semua status</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Menunggu">Menunggu</option>
                                    <option value="Parsial">Parsial</option>
                                    <option value="Lunas">Lunas</option>
                                    <option value="Overdue">Overdue</option>
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-line text-sm">
                                <thead class="bg-surface-low text-left text-xs font-semibold uppercase tracking-[0.08em] text-muted">
                                    <tr>
                                        <th class="px-5 py-3 sm:px-6">Invoice</th>
                                        <th class="px-5 py-3 sm:px-6">Pelanggan</th>
                                        <th class="px-5 py-3 sm:px-6">Tanggal</th>
                                        <th class="px-5 py-3 sm:px-6">Jatuh tempo</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Total</th>
                                        <th class="px-5 py-3 sm:px-6">Status pembayaran</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <template
                                        x-for="invoice in invoices.filter((row) => {
                                            const keyword = query.trim().toLowerCase();
                                            const matchesStatus = status === 'all' || row.status === status;
                                            const matchesKeyword = ! keyword || `${row.number} ${row.customer} ${row.email} ${row.status}`.toLowerCase().includes(keyword);

                                            return matchesStatus && matchesKeyword;
                                        })"
                                        :key="invoice.number"
                                    >
                                        <tr class="hover:bg-surface-low">
                                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-ink sm:px-6" x-text="invoice.number"></td>
                                            <td class="px-5 py-4 sm:px-6">
                                                <p class="font-medium text-ink" x-text="invoice.customer"></p>
                                                <p class="mt-0.5 text-xs text-muted" x-text="invoice.email"></p>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted sm:px-6" x-text="invoice.issue_date"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-muted sm:px-6" x-text="invoice.due_date"></td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right font-semibold text-ink sm:px-6" x-text="invoice.amount"></td>
                                            <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                                <span
                                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                    :class="{
                                                        'bg-green-100 text-green-800': invoice.tone === 'success',
                                                        'bg-yellow-100 text-yellow-900': invoice.tone === 'warning',
                                                        'bg-brand-100 text-brand-800': invoice.tone === 'brand',
                                                        'bg-red-100 text-red-800': invoice.tone === 'danger',
                                                        'bg-blue-100 text-blue-800': invoice.tone === 'info'
                                                    }"
                                                    x-text="invoice.status"
                                                ></span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                                <a :href="`/payments/invoices/${invoice.number}`" class="font-semibold text-brand-700 hover:text-brand-900">Detail</a>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
