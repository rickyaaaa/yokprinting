@php
    $pageConfig = [
        'periodPresets' => $periodPresets,
        'canExport' => $canExport,
        'dataUrl' => route('api.reports.profit-loss.show'),
        'pdfUrl' => route('api.reports.profit-loss.pdf'),
        'excelUrl' => route('api.reports.profit-loss.excel'),
    ];
@endphp

<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Laporan laba rugi YokPrinting.ID">
        <title>Laporan Laba Rugi - YokPrinting.ID</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-h-screen lg:flex" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
            <div class="fixed inset-0 z-30 bg-ink/45 lg:hidden" x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"></div>
            <x-app-sidebar />

            <div class="min-w-0 flex-1">
                <header class="sticky top-0 z-20 flex h-16 items-center border-b border-line bg-white/95 px-4 backdrop-blur-sm sm:px-6 lg:px-8">
                    <button type="button" class="btn-icon mr-3 border-transparent lg:hidden" @click="sidebarOpen = true" aria-label="Buka navigasi">
                        <i class="iconify tabler--align-left text-xl"></i>
                    </button>
                    <div class="flex min-w-0 items-center gap-2 text-sm">
                        <a href="{{ route('reports.sales.index') }}" class="hidden text-muted hover:text-ink sm:inline">Laporan penjualan</a>
                        <span class="hidden text-line sm:inline">/</span>
                        <span class="truncate font-medium text-ink">Laba rugi</span>
                    </div>
                </header>

                <main
                    class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                    x-data='profitLossReportPage(@json($pageConfig))'
                    x-init="init()"
                >
                    <div class="flex flex-col gap-5 border-b border-line pb-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <span class="badge badge-brand">Keuangan</span>
                            <h1 class="mt-3 text-2xl font-semibold tracking-[-0.025em] text-ink sm:text-[1.75rem]">Laporan laba rugi</h1>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-muted">Ringkasan invoice final dengan pajak dan ongkir pelanggan dipisahkan dari omzet penjualan.</p>
                        </div>
                        <div class="flex flex-wrap gap-2" x-show="config.canExport" x-cloak>
                            <button type="button" class="btn btn-outline disabled:opacity-50" :disabled="Boolean(exporting)" @click="download('pdf')">
                                <span x-text="exporting === 'pdf' ? 'Menyiapkan PDF...' : 'Export PDF'"></span>
                            </button>
                            <button type="button" class="btn btn-primary disabled:opacity-50" :disabled="Boolean(exporting)" @click="download('excel')">
                                <span x-text="exporting === 'excel' ? 'Menyiapkan Excel...' : 'Export Excel'"></span>
                            </button>
                        </div>
                    </div>

                    <section class="mt-6 card p-4 sm:p-5" aria-label="Filter periode laporan">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[12rem_1fr_1fr_auto]">
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Periode</span>
                                <select class="form-control" x-model="selectedPeriod" @change="selectPeriod()">
                                    <option value="daily">Harian</option>
                                    <option value="weekly">Mingguan</option>
                                    <option value="monthly">Bulanan</option>
                                    <option value="yearly">Tahunan</option>
                                    <option value="custom">Rentang kustom</option>
                                </select>
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Dari tanggal</span>
                                <input type="date" class="form-control" x-model="dateFrom" :disabled="selectedPeriod !== 'custom'">
                            </label>
                            <label>
                                <span class="mb-1.5 block text-xs font-semibold text-muted">Sampai tanggal</span>
                                <input type="date" class="form-control" x-model="dateTo" :disabled="selectedPeriod !== 'custom'">
                            </label>
                            <button type="button" class="btn bg-ink text-white hover:bg-slate-800 self-end disabled:cursor-not-allowed" :disabled="selectedPeriod !== 'custom' || loading" @click="applyCustomRange()">Terapkan</button>
                        </div>
                    </section>

                    <div x-show="error" x-cloak class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert" x-text="error"></div>

                    <div x-show="loading" class="mt-6 space-y-3" aria-live="polite">
                        <div class="h-24 animate-pulse rounded-xl bg-surface-high"></div>
                        <div class="h-96 animate-pulse rounded-xl bg-surface-high"></div>
                    </div>

                    <div x-show="!loading" x-cloak class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <section class="overflow-hidden card" aria-labelledby="statement-heading">
                            <div class="flex flex-col gap-1 border-b border-line bg-canvas px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <h2 id="statement-heading" class="font-semibold text-ink">Rincian laba rugi</h2>
                                <p class="text-sm text-muted" x-text="report.period.label"></p>
                            </div>
                            <dl class="divide-y divide-line text-sm">
                                <div class="flex items-center justify-between gap-6 px-5 py-4">
                                    <dt class="text-muted">Penjualan barang/jasa</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.gross_sales)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Diskon penjualan</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="`(${formatMoney(report.summary.sales_discount)})`"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 bg-canvas px-5 py-4">
                                    <dt class="font-semibold text-ink">Omzet Penjualan</dt>
                                    <dd class="text-right font-semibold tabular-nums text-ink" x-text="formatMoney(report.summary.sales_revenue)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Pajak dipungut (bukan omzet)</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.tax_collected)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Ongkir ditagihkan kepada pelanggan (bukan omzet)</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.customer_shipping_charged)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-4">
                                    <dt class="font-semibold text-ink">Total Invoice</dt>
                                    <dd class="text-right font-semibold tabular-nums text-ink" x-text="formatMoney(report.summary.total_invoice)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 bg-red-50 px-5 py-3 text-red-800" x-show="Number(report.summary.invoice_reconciliation_difference) !== 0">
                                    <dt>Selisih rekonsiliasi invoice</dt>
                                    <dd class="text-right font-semibold tabular-nums" x-text="formatMoney(report.summary.invoice_reconciliation_difference)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Total Harga Modal / HPP</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.total_hpp)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 bg-brand-50 px-5 py-4">
                                    <dt class="font-semibold text-brand-900">Laba Kotor</dt>
                                    <dd class="text-right text-base font-semibold tabular-nums text-brand-900" x-text="formatMoney(report.summary.gross_profit)"></dd>
                                </div>
                                <div class="px-5 pb-2 pt-5 text-xs font-semibold text-muted">Pengeluaran operasional</div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Biaya Ongkir Ditanggung Perusahaan</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.shipping_expenses)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Total Biaya Produksi</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.production_expenses)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Total Biaya Karyawan</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.employee_expenses)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Total Biaya Tempat</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.premises_expenses)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-3">
                                    <dt class="text-muted">Total Belanjaan</dt>
                                    <dd class="text-right tabular-nums text-ink" x-text="formatMoney(report.summary.shopping_expenses)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-4">
                                    <dt class="font-semibold text-ink">Total Pengeluaran Tercatat</dt>
                                    <dd class="text-right font-semibold tabular-nums text-ink" x-text="formatMoney(report.summary.recorded_expenses)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-4">
                                    <dt class="font-semibold text-ink">Pengeluaran Diakui di Laba Rugi</dt>
                                    <dd class="text-right font-semibold tabular-nums text-ink" x-text="formatMoney(report.summary.recognized_expenses)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 border-t-2 border-ink px-5 py-4">
                                    <dt class="text-base font-semibold text-ink">Laba Bersih Minimum</dt>
                                    <dd class="rounded-lg border px-3 py-2 text-right text-lg font-semibold tabular-nums" :class="profitTone(report.summary.net_profit_minimum)" x-text="formatMoney(report.summary.net_profit_minimum)"></dd>
                                </div>
                                <div class="flex items-center justify-between gap-6 px-5 py-4">
                                    <dt class="text-base font-semibold text-ink">Laba Bersih Maksimum</dt>
                                    <dd class="rounded-lg border px-3 py-2 text-right text-lg font-semibold tabular-nums" :class="profitTone(report.summary.net_profit_maximum)" x-text="formatMoney(report.summary.net_profit_maximum)"></dd>
                                </div>
                            </dl>
                        </section>

                        <aside class="space-y-4">
                            <section class="card p-5">
                                <h2 class="font-semibold text-ink">Volume penjualan</h2>
                                <dl class="mt-4 space-y-4 text-sm">
                                    <div>
                                        <dt class="text-muted">Qty Penjualan</dt>
                                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-ink" x-text="formatQuantity(report.summary.sales_quantity)"></dd>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-line pt-4">
                                        <dt class="text-muted">Invoice</dt>
                                        <dd class="font-semibold tabular-nums text-ink" x-text="report.summary.invoice_count"></dd>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <dt class="text-muted">Transaksi pengeluaran</dt>
                                        <dd class="font-semibold tabular-nums text-ink" x-text="report.summary.expense_count"></dd>
                                    </div>
                                </dl>
                            </section>
                            <section class="rounded-xl border border-line bg-canvas p-5 text-sm leading-6 text-muted">
                                <h2 class="font-semibold text-ink">Dasar perhitungan</h2>
                                <p class="mt-2">Hanya invoice final berstatus terkirim yang dihitung. Draft dan invoice dibatalkan dikecualikan. Pajak serta ongkir pelanggan hanya merekonsiliasi total invoice dan bukan omzet.</p>
                                <p class="mt-3" x-text="report.accounting_policy.minimum_profit_basis"></p>
                                <p class="mt-1" x-text="report.accounting_policy.maximum_profit_basis"></p>
                                <p class="mt-3 font-medium text-amber-800" x-show="report.accounting_policy.profit_is_provisional" x-text="report.accounting_policy.decision_required"></p>
                            </section>
                        </aside>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
