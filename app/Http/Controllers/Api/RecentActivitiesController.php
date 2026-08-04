<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecentActivitiesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $invoiceActivities = Invoice::query()
            ->with('customer')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => 'invoice-'.$invoice->id,
                'type' => 'invoice',
                'tone' => 'brand',
                'title' => ($invoice->status === Invoice::STATUS_DRAFT ? 'Draft invoice ' : 'Invoice ').$invoice->invoice_number,
                'description' => ($invoice->customer?->name ?? 'Pelanggan tidak tersedia').' · '.$this->rupiah((float) $invoice->total_amount),
                'occurred_at' => $invoice->created_at->toISOString(),
            ]);
        $paymentActivities = Payment::query()
            ->with('invoice.customer')
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => 'payment-'.$payment->id,
                'type' => 'payment',
                'tone' => $payment->status === Payment::STATUS_VERIFIED ? 'success' : 'warning',
                'title' => 'Pembayaran '.$payment->statusLabel(),
                'description' => ($payment->invoice?->customer?->name ?? 'Pelanggan tidak tersedia').' membayar '.$this->rupiah((float) $payment->amount).' untuk '.($payment->invoice?->invoice_number ?? '-').'.',
                'occurred_at' => $payment->created_at->toISOString(),
            ]);

        $activities = $invoiceActivities
            ->concat($paymentActivities)
            ->when(
                in_array($request->query('type'), ['invoice', 'payment'], true),
                fn ($items) => $items->where('type', $request->query('type')),
            )
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values();

        return response()->json(['status' => 'success', 'data' => $activities]);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
