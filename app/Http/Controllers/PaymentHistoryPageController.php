<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Contracts\View\View;

class PaymentHistoryPageController extends Controller
{
    public function __invoke(): View
    {
        $models = Payment::query()
            ->with('invoice.customer')
            ->latest('payment_date')
            ->latest('id')
            ->get();

        $payments = $models->map(fn (Payment $payment): array => [
            'date' => $payment->payment_date->format('d M Y'),
            'time' => $payment->created_at->format('H:i'),
            'invoice' => $payment->invoice?->invoice_number ?? '-',
            'customer' => $payment->invoice?->customer?->name ?? 'Pelanggan tidak tersedia',
            'method' => $payment->methodLabel(),
            'reference' => $payment->reference ?: '-',
            'amount' => $this->rupiah((float) $payment->amount),
            'status' => $payment->statusLabel(),
        ])->values();

        $verified = $models->where('status', Payment::STATUS_VERIFIED);
        $total = (float) $verified->sum('amount');

        return view('payments.history', [
            'payments' => $payments,
            'summaryCards' => [
                ['label' => 'Pembayaran diterima', 'value' => $this->rupiah($total), 'caption' => 'Pembayaran terverifikasi', 'tone' => 'success'],
                ['label' => 'Transaksi masuk', 'value' => (string) $models->count(), 'caption' => 'Seluruh pembayaran', 'tone' => 'brand'],
                ['label' => 'Rata-rata bayar', 'value' => $this->rupiah($verified->count() > 0 ? $total / $verified->count() : 0), 'caption' => 'Per transaksi terverifikasi', 'tone' => 'brand'],
                ['label' => 'Perlu verifikasi', 'value' => (string) $models->where('status', Payment::STATUS_PENDING)->count(), 'caption' => 'Menunggu pengecekan', 'tone' => 'warning'],
            ],
        ]);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
