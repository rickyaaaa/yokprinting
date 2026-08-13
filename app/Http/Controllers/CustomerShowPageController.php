<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

class CustomerShowPageController extends Controller
{
    public function __invoke(string $customer): View
    {
        $model = Customer::query()
            ->where(fn ($query) => $query->where('code', $customer)->orWhere('id', $customer))
            ->with(['invoices.payments'])
            ->firstOrFail();

        $invoices = $model->invoices->sortByDesc('issue_date');
        $sentInvoices = $invoices->where('status', Invoice::STATUS_SENT);
        $totalSales = (float) $sentInvoices->sum('total_amount');
        $verifiedPaid = (float) $sentInvoices->flatMap->payments->where('status', Payment::STATUS_VERIFIED)->sum('amount');
        $outstanding = (float) $sentInvoices
            ->where('payment_status', '!=', Invoice::PAYMENT_PAID)
            ->sum(fn (Invoice $invoice): float => $invoice->remainingAmount());

        $customerData = [
            'code' => $model->code,
            'name' => $model->name,
            'initials' => $model->initials(),
            'segment' => '-',
            'status' => $model->status === Customer::STATUS_ACTIVE ? 'Aktif' : 'Nonaktif',
            'email' => $model->email ?: '-',
            'phone' => $model->phone ?: '-',
            'address' => collect([$model->address, $model->city, $model->province, $model->postal_code])->filter()->implode(', ') ?: '-',
            'totalSales' => $this->rupiah($totalSales),
            'outstanding' => $this->rupiah($outstanding),
            'paid' => $this->rupiah($verifiedPaid),
            'invoiceCount' => $invoices->count().' invoice',
            'averageInvoice' => $this->rupiah($invoices->count() > 0 ? $totalSales / $invoices->count() : 0),
        ];

        $invoiceRows = $invoices->map(function (Invoice $invoice): array {
            $paid = (float) $invoice->payments->where('status', Payment::STATUS_VERIFIED)->sum('amount');
            $status = match (true) {
                $invoice->status === Invoice::STATUS_DRAFT => 'Draft',
                $invoice->payment_status === Invoice::PAYMENT_PAID => 'Lunas',
                $invoice->due_date->isPast() => 'Overdue',
                $invoice->payment_status === Invoice::PAYMENT_PARTIAL => 'Parsial',
                default => 'Menunggu',
            };

            return [
                'invoice' => $invoice->invoice_number,
                'date' => $invoice->issue_date->format('d M Y'),
                'due' => $invoice->due_date->format('d M Y'),
                'amount' => $this->rupiah((float) $invoice->total_amount),
                'paid' => $this->rupiah($paid),
                'status' => $status,
            ];
        })->values();

        $payments = $invoices->flatMap->payments
            ->sortByDesc('payment_date')
            ->map(fn (Payment $payment): array => [
                'date' => $payment->payment_date->format('d M Y'),
                'method' => $payment->methodLabel(),
                'reference' => $payment->reference ?: '-',
                'amount' => $this->rupiah((float) $payment->amount),
                'status' => $payment->statusLabel(),
            ])->values();

        return view('customers.show', [
            'customer' => $customerData,
            'summaryCards' => [
                ['label' => 'Total transaksi', 'value' => $customerData['totalSales'], 'caption' => $customerData['invoiceCount'], 'tone' => 'brand'],
                ['label' => 'Sudah tertagih', 'value' => $customerData['paid'], 'caption' => 'Pembayaran terverifikasi', 'tone' => 'success'],
                ['label' => 'Outstanding', 'value' => $customerData['outstanding'], 'caption' => 'Butuh monitoring', 'tone' => 'warning'],
                ['label' => 'Rata-rata invoice', 'value' => $customerData['averageInvoice'], 'caption' => 'Per transaksi', 'tone' => 'brand'],
            ],
            'invoices' => $invoiceRows,
            'payments' => $payments,
            'activities' => collect(),
        ]);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
