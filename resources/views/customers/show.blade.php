<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Detail riwayat pelanggan YokPrinting.ID">

        <title>Detail {{ $customer['code'] }} - YokPrinting.ID</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" aria-hidden="true"></div>

            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="btn-icon mr-3 border-transparent lg:hidden" @click="sidebarOpen = true" aria-controls="app-sidebar" :aria-expanded="sidebarOpen" aria-label="Buka navigasi">
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('customers.index') }}" class="hidden text-muted hover:text-ink sm:inline">Data pelanggan</a>
                        <i class="iconify tabler--chevron-right hidden text-sm text-line sm:block"></i>
                        <span class="truncate font-medium text-ink">Riwayat pelanggan</span>
                    </div>
                    <div class="ml-auto hidden text-sm text-muted sm:block">{{ now(config('app.timezone'))->locale('id')->translatedFormat('l, j F Y') }}</div>
                </header>

                <main class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="badge badge-brand">Detail pelanggan</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="grid size-12 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ $customer['initials'] }}</span>
                                <div>
                                    <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">{{ $customer['name'] }}</h1>
                                    <p class="mt-1 text-sm text-muted">{{ $customer['code'] }} · {{ $customer['segment'] }} · {{ $customer['status'] }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('customers.edit', ['customer' => $customer['code']]) }}" class="btn btn-outline">Edit pelanggan</a>
                            <a href="{{ route('invoices.create') }}" class="btn btn-primary">Buat invoice</a>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan riwayat pelanggan">
                        @foreach ($summaryCards as $card)
                            @php
                                $toneClass = match ($card['tone']) {
                                    'success' => 'badge-success',
                                    'warning' => 'badge-warning',
                                    default => 'badge-brand',
                                };
                            @endphp
                            <article class="card p-5">
                                <p class="text-sm font-medium text-muted">{{ $card['label'] }}</p>
                                <p class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $card['value'] }}</p>
                                <span class="badge mt-5 {{ $toneClass }}">{{ $card['caption'] }}</span>
                            </article>
                        @endforeach
                    </section>

                    <div class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <section class="card" aria-labelledby="customer-invoice-history-heading">
                            <div class="border-b border-line px-5 py-4 sm:px-6">
                                <h2 id="customer-invoice-history-heading" class="font-semibold text-ink">Riwayat invoice</h2>
                                <p class="mt-1 text-sm text-muted">Invoice terbaru, nominal, pembayaran, dan status untuk pelanggan ini.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[780px] text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-xs font-semibold text-muted">
                                            <th class="px-5 py-3 sm:px-6">Invoice</th>
                                            <th class="px-5 py-3">Tanggal</th>
                                            <th class="px-5 py-3">Jatuh tempo</th>
                                            <th class="px-5 py-3 text-right">Nominal</th>
                                            <th class="px-5 py-3 text-right">Tertagih</th>
                                            <th class="px-5 py-3 text-right sm:px-6">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @foreach ($invoices as $invoice)
                                            <tr class="hover:bg-brand-50/45">
                                                <td class="px-5 py-4 font-mono text-xs font-semibold text-brand-800 sm:px-6">{{ $invoice['invoice'] }}</td>
                                                <td class="px-5 py-4 text-muted">{{ $invoice['date'] }}</td>
                                                <td class="px-5 py-4 text-muted">{{ $invoice['due'] }}</td>
                                                <td class="px-5 py-4 text-right font-semibold text-ink">{{ $invoice['amount'] }}</td>
                                                <td class="px-5 py-4 text-right text-muted">{{ $invoice['paid'] }}</td>
                                                <td class="px-5 py-4 text-right sm:px-6">
                                                    <span @class([
                                                        'badge',
                                                        'badge-success' => $invoice['status'] === 'Lunas',
                                                        'badge-brand' => $invoice['status'] === 'Parsial',
                                                    ])>{{ $invoice['status'] }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <aside class="space-y-6">
                            <section class="card p-5 sm:p-6" aria-labelledby="customer-profile-heading">
                                <h2 id="customer-profile-heading" class="font-semibold text-ink">Profil kontak</h2>
                                <dl class="mt-5 space-y-4 text-sm">
                                    <div>
                                        <dt class="text-muted">Email</dt>
                                        <dd class="mt-1 font-medium text-ink">{{ $customer['email'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted">Telepon</dt>
                                        <dd class="mt-1 font-medium text-ink">{{ $customer['phone'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted">Alamat</dt>
                                        <dd class="mt-1 leading-6 text-ink">{{ $customer['address'] }}</dd>
                                    </div>
                                </dl>
                            </section>

                            <section class="card p-5 sm:p-6" aria-labelledby="customer-payment-history-heading">
                                <h2 id="customer-payment-history-heading" class="font-semibold text-ink">Pembayaran terakhir</h2>
                                <div class="mt-5 space-y-4">
                                    @foreach ($payments as $payment)
                                        <article class="rounded-lg border border-line p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="font-semibold text-ink">{{ $payment['amount'] }}</p>
                                                    <p class="mt-1 text-xs text-muted">{{ $payment['date'] }} · {{ $payment['method'] }}</p>
                                                </div>
                                                <span class="badge badge-success">{{ $payment['status'] }}</span>
                                            </div>
                                            <p class="mt-2 font-mono text-xs text-muted">{{ $payment['reference'] }}</p>
                                        </article>
                                    @endforeach
                                </div>
                            </section>
                        </aside>
                    </div>

                    <section class="mt-6 card p-5 sm:p-6" aria-labelledby="customer-activity-heading">
                        <h2 id="customer-activity-heading" class="font-semibold text-ink">Timeline aktivitas</h2>
                        <div class="mt-5 grid gap-4 lg:grid-cols-3">
                            @foreach ($activities as $activity)
                                <article class="rounded-xl border border-line p-4">
                                    <p class="text-xs font-medium text-muted">{{ $activity['time'] }}</p>
                                    <h3 class="mt-2 font-semibold text-ink">{{ $activity['title'] }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-muted">{{ $activity['description'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
