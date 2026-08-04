<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Dashboard bisnis YokPrinting.ID">

        <title>Dashboard Bisnis - YokPrinting.ID</title>

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
                        <span class="hidden text-muted sm:inline">Ringkasan</span>
                        <svg class="hidden size-4 text-line sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="truncate font-medium text-ink">Dashboard bisnis</span>
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
                            <div class="mb-2 flex items-center gap-2">
                                <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">Dashboard bisnis</span>
                            </div>
                            <h1 class="text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Ringkasan keuangan</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Pantau pendapatan, tagihan berjalan, dan aktivitas invoice terbaru dalam satu layar.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-3.5 py-2 text-sm font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 7h16M7 4v6M17 4v6M6 12h12v8H6z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Bulan ini
                            </button>
                            <a href="{{ route('invoices.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                                </svg>
                                Buat invoice
                            </a>
                        </div>
                    </div>

                    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan keuangan utama">
                        @foreach ($summaryCards as $card)
                            @php
                                $toneClass = match ($card['changeTone']) {
                                    'success' => 'bg-green-100 text-green-800',
                                    'warning' => 'bg-yellow-100 text-yellow-900',
                                    'danger' => 'bg-red-100 text-red-800',
                                    default => 'bg-brand-100 text-brand-800',
                                };
                            @endphp
                            <article class="rounded-xl bg-white p-5 border border-line">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-muted">{{ $card['label'] }}</p>
                                        <p class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink">{{ $card['value'] }}</p>
                                    </div>
                                    <span class="grid size-10 place-items-center rounded-lg bg-brand-50 text-brand-800" aria-hidden="true">
                                        @if ($card['icon'] === 'revenue')
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M4 19V5M4 19h16M8 16l3-4 3 2 5-7" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        @elseif ($card['icon'] === 'paid')
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M4 7h16v10H4zM7 11h3M15 15h2M9 19h6" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        @elseif ($card['icon'] === 'pending')
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <circle cx="12" cy="12" r="8"/>
                                                <path d="M12 8v5l3 2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        @else
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M12 8v5M12 17h.01" stroke-linecap="round"/>
                                                <circle cx="12" cy="12" r="9"/>
                                            </svg>
                                        @endif
                                    </span>
                                </div>
                                <div class="mt-5 flex items-center gap-2 text-xs">
                                    <span class="rounded-full px-2 py-1 font-semibold {{ $toneClass }}">{{ $card['change'] }}</span>
                                    <span class="text-muted">{{ $card['caption'] }}</span>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    <section class="mt-6 rounded-xl bg-white border border-line" aria-labelledby="production-queue-heading">
                        <div class="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div>
                                <h2 id="production-queue-heading" class="font-semibold text-ink">Antrean produksi sablon cup</h2>
                                <p class="mt-1 text-sm text-muted">Ringkasan status Design → DP → Print → Ship untuk order YokPrinting.</p>
                            </div>
                            <span class="w-fit rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800">{{ count($productionQueue) }} order aktif</span>
                        </div>
                        <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-3">
                            @foreach ($productionQueue as $order)
                                @php
                                    $queueClass = match ($order['tone']) {
                                        'success' => 'bg-green-100 text-green-800',
                                        'warning' => 'bg-yellow-100 text-yellow-900',
                                        default => 'bg-brand-100 text-brand-800',
                                    };
                                @endphp
                                <article class="rounded-lg border border-line bg-canvas p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-mono text-xs font-semibold text-muted">{{ $order['invoice'] }}</p>
                                            <h3 class="mt-1 truncate text-sm font-semibold text-ink">{{ $order['customer'] }}</h3>
                                        </div>
                                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $queueClass }}">{{ $order['status'] }}</span>
                                    </div>
                                    <p class="mt-4 text-sm leading-6 text-ink">{{ $order['spec'] }}</p>
                                    <p class="mt-2 text-xs font-medium text-muted">{{ $order['eta'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    <div class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                        <section
                            class="rounded-xl bg-white border border-line"
                            aria-labelledby="revenue-heading"
                            x-data="dashboardRevenueChart"
                        >
                            <div class="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div>
                                    <h2 id="revenue-heading" class="font-semibold text-ink">Tren pendapatan</h2>
                                    <p class="mt-1 text-sm text-muted" x-text="currentDataset.label"></p>
                                </div>
                                <div class="inline-flex w-fit rounded-lg bg-canvas p-1 text-xs font-semibold text-muted" aria-label="Pilih periode grafik">
                                    <template x-for="period in periods" :key="period.key">
                                        <button
                                            type="button"
                                            class="rounded-md px-3 py-1.5 hover:text-ink"
                                            :class="selectedPeriod === period.key ? 'bg-white text-ink border border-line' : ''"
                                            :aria-pressed="selectedPeriod === period.key"
                                            @click="selectPeriod(period.key)"
                                            x-text="period.label"
                                        ></button>
                                    </template>
                                </div>
                            </div>

                            <div class="p-5 sm:p-6">
                                <div class="mb-5 grid gap-4 md:grid-cols-[16rem_minmax(0,1fr)]">
                                    <div class="rounded-lg border border-line bg-canvas p-4">
                                        <p class="text-xs font-semibold text-muted">Total invoice terbit</p>
                                        <p class="mt-2 text-2xl font-semibold tracking-[-0.025em] text-ink" x-text="currentDataset.headline"></p>
                                    </div>
                                    <div class="rounded-lg border border-line bg-canvas p-4">
                                        <p class="text-xs font-semibold text-muted">Catatan periode</p>
                                        <p class="mt-2 text-sm leading-6 text-ink" x-text="currentDataset.caption"></p>
                                    </div>
                                </div>

                                <div class="relative h-80 rounded-lg border border-line bg-canvas p-4" aria-label="Grafik pendapatan interaktif">
                                    <canvas x-ref="revenueChart"></canvas>
                                </div>

                                <div class="mt-5 grid gap-4 md:grid-cols-3">
                                    @foreach ($cashflowSegments as $segment)
                                        <div class="rounded-lg border border-line p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="text-sm text-muted">{{ $segment['label'] }}</span>
                                                <span class="text-sm font-semibold text-ink">{{ $segment['value'] }}</span>
                                            </div>
                                            <div class="mt-3 h-2 rounded-full bg-canvas">
                                                <div class="h-2 rounded-full {{ $segment['class'] }}" style="width: {{ $segment['value'] }}"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        <aside class="space-y-6">
                            <section class="rounded-xl bg-white border border-line" aria-labelledby="due-notification-heading" data-testid="due-notification-card">
                                <div class="border-b border-line px-5 py-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-800">Notifikasi jatuh tempo</p>
                                            <h2 id="due-notification-heading" class="mt-3 font-semibold text-ink">Invoice perlu ditindaklanjuti</h2>
                                            <p class="mt-1 text-sm leading-6 text-muted">3 invoice masuk prioritas reminder pembayaran.</p>
                                        </div>
                                        <span class="grid size-10 place-items-center rounded-lg bg-red-50 text-red-800" aria-hidden="true">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M12 8v5M12 17h.01" stroke-linecap="round"/>
                                                <circle cx="12" cy="12" r="9"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>

                                <div class="divide-y divide-line">
                                    @foreach ($dueNotifications as $notification)
                                        @php
                                            $toneClass = match ($notification['tone']) {
                                                'danger' => 'bg-red-100 text-red-800',
                                                'warning' => 'bg-yellow-100 text-yellow-900',
                                                default => 'bg-accent-soft text-accent',
                                            };
                                        @endphp
                                        <article class="p-5">
                                            <div class="flex items-start justify-between gap-3">
                                                <div>
                                                    <p class="text-sm font-semibold text-ink">{{ $notification['invoice'] }}</p>
                                                    <p class="mt-1 text-xs text-muted">{{ $notification['customer'] }}</p>
                                                </div>
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $toneClass }}">{{ $notification['title'] }}</span>
                                            </div>
                                            <div class="mt-4 flex items-center justify-between gap-3">
                                                <span class="text-sm font-semibold text-ink">{{ $notification['amount'] }}</span>
                                                <button type="button" class="rounded-lg border border-line bg-white px-3 py-1.5 text-xs font-semibold text-ink hover:bg-brand-50 hover:text-brand-800">
                                                    {{ $notification['action'] }}
                                                </button>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>

                                <div class="border-t border-line px-5 py-4">
                                    <a href="{{ route('notifications.due-invoices.index') }}" class="inline-flex w-full items-center justify-center rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                                        Lihat daftar jatuh tempo
                                    </a>
                                </div>
                            </section>

                            <section class="rounded-xl border border-yellow-200 bg-yellow-50" aria-labelledby="low-stock-heading">
                                <div class="border-b border-yellow-200 px-5 py-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-950">Inventori</p>
                                            <h2 id="low-stock-heading" class="mt-3 font-semibold text-yellow-950">Ringkasan stok menipis</h2>
                                            <p class="mt-1 text-sm leading-6 text-yellow-900">{{ count($lowStockProducts) }} produk berada di bawah minimum stok.</p>
                                        </div>
                                        <span class="grid size-10 place-items-center rounded-lg bg-yellow-100 text-yellow-900" aria-hidden="true">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M12 8v5M12 17h.01" stroke-linecap="round"/>
                                                <path d="M10.3 4.6 2.8 18a1.7 1.7 0 0 0 1.5 2.5h15.4a1.7 1.7 0 0 0 1.5-2.5L13.7 4.6a2 2 0 0 0-3.4 0Z" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                                <div class="divide-y divide-yellow-200">
                                    @foreach ($lowStockProducts as $product)
                                        <article class="px-5 py-4">
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-semibold text-yellow-950">{{ $product['name'] }}</p>
                                                    <p class="mt-0.5 font-mono text-xs text-yellow-800">{{ $product['sku'] }}</p>
                                                </div>
                                                <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-950">{{ $product['urgency'] }}</span>
                                            </div>
                                            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                                <div class="rounded-lg bg-white/70 p-3">
                                                    <dt class="text-xs font-medium text-yellow-800">Stok saat ini</dt>
                                                    <dd class="mt-1 font-semibold text-yellow-950">{{ $product['stock'] }}</dd>
                                                </div>
                                                <div class="rounded-lg bg-white/70 p-3">
                                                    <dt class="text-xs font-medium text-yellow-800">Minimum stok</dt>
                                                    <dd class="mt-1 font-semibold text-yellow-950">{{ $product['minimum'] }}</dd>
                                                </div>
                                            </dl>
                                        </article>
                                    @endforeach
                                </div>
                                <div class="border-t border-yellow-200 px-5 py-4">
                                    <a href="{{ route('products.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-yellow-100 px-3 py-2 text-sm font-semibold text-yellow-950 hover:bg-yellow-200">
                                        Lihat katalog produk
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                </div>
                            </section>

                            <section
                                class="rounded-xl bg-white border border-line"
                                aria-labelledby="activity-heading"
                                x-data="dashboardRecentActivities"
                            >
                                <div class="border-b border-line px-5 py-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 id="activity-heading" class="font-semibold text-ink">Aktivitas terbaru</h2>
                                            <p class="mt-1 text-sm text-muted">Pergerakan invoice dan pembayaran hari ini.</p>
                                        </div>
                                        <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-800" x-text="`${visibleActivities.length} aktivitas`"></span>
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-1 rounded-lg bg-canvas p-1 text-xs font-semibold text-muted" aria-label="Filter aktivitas">
                                        <template x-for="filter in filters" :key="filter.key">
                                            <button
                                                type="button"
                                                class="rounded-md px-2.5 py-1.5 hover:text-ink"
                                                :class="activeFilter === filter.key ? 'bg-white text-ink border border-line' : ''"
                                                :aria-pressed="activeFilter === filter.key"
                                                @click="setFilter(filter.key)"
                                                x-text="filter.label"
                                            ></button>
                                        </template>
                                    </div>
                                </div>
                                <ol class="divide-y divide-line">
                                    <template x-for="activity in visibleActivities" :key="activity.id">
                                        <li class="flex gap-3 px-5 py-4">
                                            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full" :class="toneClass(activity.tone)" aria-hidden="true">
                                                <span class="size-2 rounded-full bg-current"></span>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                    <p class="text-sm font-semibold text-ink" x-text="activity.title"></p>
                                                    <time
                                                        class="shrink-0 text-xs font-medium text-muted"
                                                        :datetime="activity.occurredAt"
                                                        :title="formattedTime(activity.occurredAt)"
                                                        x-text="relativeTime(activity.occurredAt)"
                                                    ></time>
                                                </div>
                                                <p class="mt-1 text-sm leading-5 text-muted" x-text="activity.description"></p>
                                            </div>
                                        </li>
                                    </template>
                                </ol>
                            </section>
                        </aside>
                    </div>

                    <section class="mt-6 rounded-xl bg-white border border-line" aria-labelledby="upcoming-heading">
                        <div class="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div>
                                <h2 id="upcoming-heading" class="font-semibold text-ink">Invoice yang perlu dipantau</h2>
                                <p class="mt-1 text-sm text-muted">Prioritas pembayaran dan follow-up dalam beberapa hari ke depan.</p>
                            </div>
                            <a href="{{ route('payments.receivables.index') }}" class="inline-flex w-fit items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50">
                                Lihat semua
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="m9 18 6-6-6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[720px] text-left text-sm">
                                <thead>
                                    <tr class="border-b border-line text-xs font-semibold text-muted">
                                        <th class="px-5 py-3 sm:px-6">Pelanggan</th>
                                        <th class="px-5 py-3">Invoice</th>
                                        <th class="px-5 py-3 text-right">Nominal</th>
                                        <th class="px-5 py-3">Jatuh tempo</th>
                                        <th class="px-5 py-3 text-right sm:px-6">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($upcomingInvoices as $invoice)
                                        @php
                                            $statusClass = match ($invoice['status']) {
                                                'Terkirim', 'ACC desain' => 'bg-brand-100 text-brand-800',
                                                'Lewat tempo' => 'bg-red-100 text-red-800',
                                                default => 'bg-yellow-100 text-yellow-900',
                                            };
                                        @endphp
                                        <tr>
                                            <td class="px-5 py-4 font-medium text-ink sm:px-6">{{ $invoice['customer'] }}</td>
                                            <td class="px-5 py-4 font-mono text-xs font-semibold text-muted">
                                                <a href="{{ route('payments.invoices.show', $invoice['invoice']) }}" class="hover:text-brand-800">{{ $invoice['invoice'] }}</a>
                                            </td>
                                            <td class="px-5 py-4 text-right font-semibold text-ink">{{ $invoice['amount'] }}</td>
                                            <td class="px-5 py-4 text-muted">{{ $invoice['due'] }}</td>
                                            <td class="px-5 py-4 text-right sm:px-6">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $invoice['status'] }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
