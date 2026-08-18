<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class DashboardPageController extends Controller
{
    public function __invoke(): View
    {
        $monthStart = today()->startOfMonth()->toDateString();
        $monthEnd = today()->endOfMonth()->toDateString();
        $revenueThisMonth = (float) Invoice::query()
            ->finalized()
            ->whereBetween('issue_date', [$monthStart, $monthEnd])
            ->sum('total_amount');
        $revenueInvoiceCount = Invoice::query()
            ->finalized()
            ->whereBetween('issue_date', [$monthStart, $monthEnd])
            ->count();
        $paid = (float) Payment::query()
            ->verified()
            ->whereHas('invoice', fn ($query) => $query->finalized())
            ->sum('amount');
        $paidInvoiceCount = Invoice::query()
            ->finalized()
            ->where('payment_status', Invoice::PAYMENT_PAID)
            ->count();
        $receivableCount = Invoice::query()->receivable()->count();
        $overdueCount = Invoice::query()->receivable()->overdue()->count();
        $outstanding = $this->outstandingTotal();
        $overdueAmount = $this->outstandingTotal(overdueOnly: true);

        $totalCashflow = max(1, $paid + $outstanding);
        $pendingAmount = max(0, $outstanding - $overdueAmount);

        $upcoming = Invoice::query()
            ->receivable()
            ->with('customer')
            ->withSum(['payments as verified_paid_amount' => fn ($query) => $query->verified()], 'amount')
            ->orderBy('due_date')
            ->limit(3)
            ->get();

        $lowStock = Product::query()->selectable()->lowStock()->orderBy('stock')->take(5)->get();
        $queue = Invoice::query()
            ->with(['customer', 'items'])
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('production_status', '!=', Invoice::PRODUCTION_COMPLETED)
            ->latest('issue_date')
            ->limit(6)
            ->get();

        return view('dashboard', [
            'summaryCards' => [
                ['label' => 'Pendapatan bulan ini', 'value' => $this->rupiah($revenueThisMonth), 'change' => $revenueInvoiceCount.' invoice', 'changeTone' => 'success', 'caption' => 'Invoice final bulan berjalan', 'icon' => 'revenue'],
                ['label' => 'Invoice tertagih', 'value' => $this->rupiah($paid), 'change' => $paidInvoiceCount.' lunas', 'changeTone' => 'brand', 'caption' => 'Pembayaran terverifikasi', 'icon' => 'paid'],
                ['label' => 'Menunggu bayar', 'value' => $this->rupiah($outstanding), 'change' => $receivableCount.' invoice', 'changeTone' => 'warning', 'caption' => 'Sisa pembayaran', 'icon' => 'pending'],
                ['label' => 'Lewat tempo', 'value' => $this->rupiah($overdueAmount), 'change' => $overdueCount.' invoice', 'changeTone' => 'danger', 'caption' => 'Perlu tindak lanjut', 'icon' => 'overdue'],
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
                'whatsapp_endpoint' => route('api.invoices.send-whatsapp', ['invoice' => $invoice]),
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

    /**
     * Calculate receivable totals in SQL without hydrating the whole invoice ledger.
     */
    private function outstandingTotal(bool $overdueOnly = false): float
    {
        $verifiedPayments = Payment::query()
            ->verified()
            ->selectRaw('invoice_id, SUM(amount) as paid_amount')
            ->groupBy('invoice_id');

        $query = Invoice::query()
            ->receivable()
            ->leftJoinSub($verifiedPayments, 'verified_payments', function ($join): void {
                $join->on('verified_payments.invoice_id', '=', 'invoices.id');
            });

        if ($overdueOnly) {
            $query->whereDate('invoices.due_date', '<', today());
        }

        return max(0, (float) $query
            ->selectRaw('COALESCE(SUM(invoices.total_amount - COALESCE(verified_payments.paid_amount, 0)), 0) as outstanding_total')
            ->value('outstanding_total'));
    }
}
