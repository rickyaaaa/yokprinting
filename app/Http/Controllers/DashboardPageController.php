<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class DashboardPageController extends Controller
{
    public function __invoke(): View
    {
        $invoices = Invoice::query()
            ->with(['customer', 'items'])
            ->withSum(['payments as verified_paid_amount' => fn ($query) => $query->verified()], 'amount')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get();
        $sent = $invoices->where('status', Invoice::STATUS_SENT);
        $paid = (float) $invoices->sum('verified_paid_amount');
        $outstanding = (float) $invoices->sum(fn (Invoice $invoice): float => max(0, (float) $invoice->total_amount - (float) ($invoice->verified_paid_amount ?? 0)));
        $overdue = $invoices->filter(fn (Invoice $invoice): bool => $invoice->payment_status !== Invoice::PAYMENT_PAID && $invoice->due_date->isPast()
        );
        $overdueAmount = (float) $overdue->sum(fn (Invoice $invoice): float => max(0, (float) $invoice->total_amount - (float) ($invoice->verified_paid_amount ?? 0)));
        $revenueThisMonth = (float) $sent->filter(fn (Invoice $invoice): bool => $invoice->issue_date->isCurrentMonth())->sum('total_amount');

        $totalCashflow = max(1, $paid + $outstanding);
        $pendingAmount = max(0, $outstanding - $overdueAmount);

        $upcoming = $invoices
            ->where('payment_status', '!=', Invoice::PAYMENT_PAID)
            ->sortBy('due_date')
            ->take(3);

        $lowStock = Product::query()->selectable()->lowStock()->orderBy('stock')->take(5)->get();
        $queue = $invoices
            ->where('production_status', '!=', Invoice::PRODUCTION_COMPLETED)
            ->sortByDesc('issue_date')
            ->take(6);

        return view('dashboard', [
            'summaryCards' => [
                ['label' => 'Pendapatan bulan ini', 'value' => $this->rupiah($revenueThisMonth), 'change' => $sent->filter(fn (Invoice $invoice): bool => $invoice->issue_date->isCurrentMonth())->count().' invoice', 'changeTone' => 'success', 'caption' => 'Invoice final bulan berjalan', 'icon' => 'revenue'],
                ['label' => 'Invoice tertagih', 'value' => $this->rupiah($paid), 'change' => $invoices->where('payment_status', Invoice::PAYMENT_PAID)->count().' lunas', 'changeTone' => 'brand', 'caption' => 'Pembayaran terverifikasi', 'icon' => 'paid'],
                ['label' => 'Menunggu bayar', 'value' => $this->rupiah($outstanding), 'change' => $invoices->where('payment_status', '!=', Invoice::PAYMENT_PAID)->count().' invoice', 'changeTone' => 'warning', 'caption' => 'Sisa pembayaran', 'icon' => 'pending'],
                ['label' => 'Lewat tempo', 'value' => $this->rupiah($overdueAmount), 'change' => $overdue->count().' invoice', 'changeTone' => 'danger', 'caption' => 'Perlu tindak lanjut', 'icon' => 'overdue'],
            ],
            'cashflowSegments' => [
                ['label' => 'Tertagih', 'value' => round($paid / $totalCashflow * 100).'%', 'class' => 'bg-brand-600'],
                ['label' => 'Menunggu', 'value' => round($pendingAmount / $totalCashflow * 100).'%', 'class' => 'bg-accent'],
                ['label' => 'Lewat tempo', 'value' => round($overdueAmount / $totalCashflow * 100).'%', 'class' => 'bg-red-600'],
            ],
            'upcomingInvoices' => $upcoming->map(fn (Invoice $invoice): array => [
                'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
                'invoice' => $invoice->invoice_number,
                'amount' => $this->rupiah((float) $invoice->total_amount),
                'due' => $invoice->due_date->isPast() ? 'Lewat '.$invoice->due_date->diffInDays(today()).' hari' : $invoice->due_date->format('d M'),
                'status' => $invoice->productionStatusLabel(),
            ])->values(),
            'dueNotifications' => $upcoming->map(fn (Invoice $invoice): array => [
                'tone' => $invoice->due_date->isPast() ? 'danger' : 'warning',
                'title' => $invoice->due_date->isPast() ? 'Lewat tempo' : 'Mendekati jatuh tempo',
                'invoice' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
                'amount' => $this->rupiah(max(0, (float) $invoice->total_amount - (float) ($invoice->verified_paid_amount ?? 0))),
                'action' => 'Tinjau pembayaran',
            ])->values(),
            'lowStockProducts' => $lowStock->map(fn (Product $product): array => [
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => number_format((float) ($product->stock ?? 0), 0, ',', '.').' '.$product->unit,
                'minimum' => number_format($product->minimumStockValue(), 0, ',', '.').' '.$product->unit,
                'urgency' => 'Di bawah minimum stok',
            ])->values(),
            'productionQueue' => $queue->map(function (Invoice $invoice): array {
                $item = $invoice->items->first();

                return [
                    'invoice' => $invoice->invoice_number,
                    'customer' => $invoice->customer?->name ?? 'Pelanggan tidak tersedia',
                    'spec' => $item?->description ?: ($item?->product_name ?? 'Belum ada item'),
                    'status' => $invoice->productionStatusLabel(),
                    'eta' => $invoice->due_date->format('d M Y'),
                    'tone' => $invoice->production_status === Invoice::PRODUCTION_IN_PRODUCTION ? 'success' : 'brand',
                ];
            })->values(),
        ]);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
