<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordInvoicePayment
{
    /**
     * Record a payment against an invoice and update payment status when verified.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Invoice $invoice, array $data, ?int $recordedBy = null): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $recordedBy): Payment {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $status = $data['status'] ?? Payment::STATUS_VERIFIED;
            $amount = round((float) $data['amount'], 2);
            $paidBefore = $this->verifiedPaymentsTotal($lockedInvoice);
            $remainingBefore = max(0, (float) $lockedInvoice->total_amount - $paidBefore);

            if ($amount > $remainingBefore) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan.',
                ]);
            }

            $payment = $lockedInvoice->payments()->create([
                'recorded_by' => $recordedBy,
                'payment_number' => $this->generatePaymentNumber(),
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'currency' => $lockedInvoice->currency,
                'amount' => $amount,
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'metadata' => ['source' => 'payment-api'],
                'verified_at' => $status === Payment::STATUS_VERIFIED ? now() : null,
                'verified_by' => $status === Payment::STATUS_VERIFIED ? $recordedBy : null,
            ]);

            if ($status === Payment::STATUS_VERIFIED) {
                $this->updateInvoicePaymentStatus($lockedInvoice, $paidBefore + $amount);
            }

            return $payment->load('invoice');
        });
    }

    private function verifiedPaymentsTotal(Invoice $invoice): float
    {
        return (float) $invoice->payments()
            ->verified()
            ->sum('amount');
    }

    private function updateInvoicePaymentStatus(Invoice $invoice, float $paidAmount): void
    {
        $totalAmount = (float) $invoice->total_amount;
        $isPaid = $paidAmount >= $totalAmount;

        $invoice->forceFill([
            'payment_status' => $isPaid ? Invoice::PAYMENT_PAID : Invoice::PAYMENT_PARTIAL,
            'paid_at' => $isPaid ? ($invoice->paid_at ?? now()) : null,
        ])->save();
    }

    private function generatePaymentNumber(): string
    {
        do {
            $paymentNumber = sprintf(
                'PAY-%s-%04d%s',
                now()->format('Ymd'),
                random_int(0, 9999),
                Str::upper(Str::random(2)),
            );
        } while (Payment::withTrashed()->where('payment_number', $paymentNumber)->exists());

        return $paymentNumber;
    }
}
