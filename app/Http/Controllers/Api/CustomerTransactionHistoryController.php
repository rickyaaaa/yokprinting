<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class CustomerTransactionHistoryController extends Controller
{
    /**
     * Display invoice and payment history for a customer.
     */
    public function index(Customer $customer): JsonResponse
    {
        $invoices = $customer->invoices()
            ->with(['payments' => fn ($query) => $query->latest('payment_date')->latest('id')])
            ->latest('issue_date')
            ->latest('id')
            ->get();

        $invoiceRows = $invoices->map(function (Invoice $invoice): array {
            $verifiedPaidAmount = (int) $invoice->payments
                ->where('status', Payment::STATUS_VERIFIED)
                ->sum(fn (Payment $payment): float => (float) $payment->amount);
            $totalAmount = (int) $invoice->total_amount;
            $remainingAmount = max(0, $totalAmount - $verifiedPaidAmount);
            $effectivePaymentStatus = $this->effectivePaymentStatus($invoice);

            return [
                'id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'status' => $invoice->status,
                'payment_status' => $effectivePaymentStatus,
                'payment_status_label' => $this->paymentStatusLabel($effectivePaymentStatus),
                'currency' => $invoice->currency,
                'total_amount' => $totalAmount,
                'paid_amount' => $verifiedPaidAmount,
                'outstanding_amount' => $remainingAmount,
                'is_overdue' => $effectivePaymentStatus === Invoice::PAYMENT_OVERDUE,
                'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->getKey(),
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'method' => $payment->method,
                    'method_label' => $payment->methodLabel(),
                    'reference' => $payment->reference,
                    'amount' => (int) $payment->amount,
                    'status' => $payment->status,
                    'status_label' => $payment->statusLabel(),
                    'verified_at' => $payment->verified_at?->toISOString(),
                ])->values(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'customer' => [
                    'id' => $customer->getKey(),
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'segment' => $customer->segment,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'initials' => $customer->initials(),
                ],
                'summary' => [
                    'invoice_count' => $invoiceRows->count(),
                    'total_amount' => $invoiceRows->sum('total_amount'),
                    'paid_amount' => $invoiceRows->sum('paid_amount'),
                    'outstanding_amount' => $invoiceRows->sum('outstanding_amount'),
                    'overdue_count' => $invoiceRows->where('is_overdue', true)->count(),
                ],
                'invoices' => $invoiceRows,
            ],
        ]);
    }

    /**
     * Resolve payment status with overdue state.
     */
    private function effectivePaymentStatus(Invoice $invoice): string
    {
        if (
            $invoice->payment_status !== Invoice::PAYMENT_PAID &&
            $invoice->due_date !== null &&
            $invoice->due_date->isPast()
        ) {
            return Invoice::PAYMENT_OVERDUE;
        }

        return $invoice->payment_status;
    }

    /**
     * Get the human-readable payment status label.
     */
    private function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            Invoice::PAYMENT_PAID => 'Lunas',
            Invoice::PAYMENT_PARTIAL => 'Parsial',
            Invoice::PAYMENT_OVERDUE => 'Overdue',
            default => 'Menunggu',
        };
    }
}
