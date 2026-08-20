@php
    $reportConfig = [
        'endpoint' => route('api.reports.customer-sales.index'),
        'dateFrom' => now()->startOfMonth()->toDateString(),
        'dateTo' => now()->toDateString(),
        'exportEndpoints' => [
            'csv' => route('api.reports.customer-sales.export'),
            'pdf' => route('api.reports.customer-sales.pdf'),
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Penjualan per Pelanggan - YokPrinting.ID</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }">
    <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
    <x-app-sidebar />
    <main class="min-w-0 flex-1" x-data='customerSalesReportPage(@json($reportConfig))' x-init="init()">
        <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 sm:px-6 lg:px-8">
            <button type="button" class="mr-3 rounded-lg p-2 text-muted hover:bg-brand-50 lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">☰</button>
            <div class="flex items-center gap-2 text-sm"><a class="text-muted hover:text-ink" href="{{ route('reports.sales.index') }}">Laporan penjualan</a><span class="text-line">/</span><span class="font-medium text-ink">Penjualan per Pelanggan</span></div>
        </header>
        <div class="mx-auto w-full max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div><h1 class="text-2xl font-semibold text-ink">Penjualan per Pelanggan</h1><p class="mt-1 text-sm text-muted">Bandingkan penjualan, HPP FIFO, laba kotor, dan margin berdasarkan pelanggan.</p></div>
                <div class="flex flex-wrap gap-2"><button type="button" class="rounded-lg border border-line bg-white px-3 py-2 text-sm font-semibold text-ink hover:bg-canvas" @click="exportFile('pdf')">Export PDF</button><button type="button" class="rounded-lg bg-brand-700 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-800" @click="exportFile('csv')">Export Excel/CSV</button></div>
            </div>
            <form class="mb-6 grid gap-3 rounded-xl border border-line bg-white p-4 sm:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_auto]" @submit.prevent="load()">
                <label class="text-sm"><span class="mb-1 block font-semibold text-muted">Dari tanggal</span><input class="form-control" type="date" x-model="filters.date_from"></label>
                <label class="text-sm"><span class="mb-1 block font-semibold text-muted">Sampai tanggal</span><input class="form-control" type="date" x-model="filters.date_to"></label>
                <label class="text-sm"><span class="mb-1 block font-semibold text-muted">Customer</span><select class="form-control" x-model="filters.customer_id"><option value="">Semua customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></label>
                <label class="text-sm"><span class="mb-1 block font-semibold text-muted">Status</span><select class="form-control" x-model="filters.status"><option value="all">Semua status</option><option value="paid">Lunas</option><option value="partial">Parsial</option><option value="unpaid">Belum bayar</option></select></label>
                <button class="self-end rounded-lg bg-ink px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800" type="submit">Terapkan</button>
            </form>
            <p x-show="error" x-cloak class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></p>
            <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-xl border border-line bg-white p-5"><p class="text-sm text-muted">Penjualan</p><p class="mt-2 text-xl font-semibold text-ink" x-text="format(report.summary.sales)"></p></article>
                <article class="rounded-xl border border-line bg-white p-5"><p class="text-sm text-muted">HPP FIFO</p><p class="mt-2 text-xl font-semibold text-ink" x-text="format(report.summary.fifo_hpp)"></p></article>
                <article class="rounded-xl border border-line bg-white p-5"><p class="text-sm text-muted">Laba kotor</p><p class="mt-2 text-xl font-semibold text-ink" x-text="format(report.summary.gross_profit)"></p></article>
                <article class="rounded-xl border border-line bg-white p-5"><p class="text-sm text-muted">Margin kotor</p><p class="mt-2 text-xl font-semibold text-ink" x-text="`${report.summary.margin_percent ?? 0}%`"></p></article>
            </section>
            <section class="overflow-x-auto rounded-xl border border-line bg-white">
                <table class="w-full min-w-[980px] text-left text-sm"><thead class="bg-surface-low text-xs font-semibold text-muted"><tr><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Invoice</th><th class="px-5 py-3 text-right">Penjualan</th><th class="px-5 py-3 text-right">HPP FIFO</th><th class="px-5 py-3 text-right">Laba Kotor</th><th class="px-5 py-3 text-right">Margin %</th></tr></thead>
                    <tbody class="divide-y divide-line"><template x-for="customer in report.customers" :key="customer.customer_id"><template x-for="row in customer.invoices" :key="row.invoice_number"><tr class="hover:bg-surface-low"><td class="px-5 py-3 font-medium text-ink" x-text="customer.customer"></td><td class="px-5 py-3 text-muted" x-text="row.issue_date"></td><td class="px-5 py-3 font-mono text-xs font-semibold text-brand-800" x-text="row.invoice_number"></td><td class="px-5 py-3 text-right" x-text="format(row.sales)"></td><td class="px-5 py-3 text-right" x-text="format(row.fifo_hpp)"></td><td class="px-5 py-3 text-right font-semibold" x-text="format(row.gross_profit)"></td><td class="px-5 py-3 text-right" x-text="`${row.margin_percent}%`"></td></tr></template></template><tr x-show="!loading && report.customers.length === 0"><td colspan="7" class="px-5 py-12 text-center text-muted">Tidak ada penjualan pada filter ini.</td></tr></tbody></table>
            </section>
        </div>
    </main>
</div>
</body>
</html>
