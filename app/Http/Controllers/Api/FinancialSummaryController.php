<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class FinancialSummaryController extends Controller
{
    /**
     * Return financial summary metrics for the business dashboard.
     */
    public function __invoke(): JsonResponse
    {
        $activeInvoices = Invoice::query()
            ->businessTransaction();

        $totalSales = (float) (clone $activeInvoices)
            ->sum('total_amount');

        $paidAmount = (float) (clone $activeInvoices)
            ->where('payment_status', Invoice::PAYMENT_PAID)
            ->sum('total_amount');

        $paidCount = (clone $activeInvoices)
            ->where('payment_status', Invoice::PAYMENT_PAID)
            ->count();

        $unpaidAmount = (float) (clone $activeInvoices)
            ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIAL])
            ->sum('total_amount');

        $unpaidCount = (clone $activeInvoices)
            ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIAL])
            ->count();

        $overdueAmount = (float) (clone $activeInvoices)
            ->overdue()
            ->sum('total_amount');

        $overdueCount = (clone $activeInvoices)
            ->overdue()
            ->count();

        $totalInvoicesCount = (clone $activeInvoices)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_sales' => $totalSales,
                'total_sales_formatted' => $this->formatRupiah($totalSales),
                'paid_amount' => $paidAmount,
                'paid_amount_formatted' => $this->formatRupiah($paidAmount),
                'paid_count' => $paidCount,
                'unpaid_amount' => $unpaidAmount,
                'unpaid_amount_formatted' => $this->formatRupiah($unpaidAmount),
                'unpaid_count' => $unpaidCount,
                'overdue_amount' => $overdueAmount,
                'overdue_amount_formatted' => $this->formatRupiah($overdueAmount),
                'overdue_count' => $overdueCount,
                'total_invoices_count' => $totalInvoicesCount,
            ],
        ]);
    }

    /**
     * Format currency to IDR Rupiah string.
     */
    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
