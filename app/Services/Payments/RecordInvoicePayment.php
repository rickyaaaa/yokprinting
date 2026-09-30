<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CashBank\CashBankService;
use App\Services\Inventory\FifoInventoryService;
use App\Services\Invoices\RecordInvoiceSaleMovements;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordInvoicePayment
{
    public function __construct(
        private readonly CashBankService $cashBank,
        private readonly RecordInvoiceSaleMovements $recordInvoiceSaleMovements,
        private readonly FifoInventoryService $fifoInventory,
    ) {}

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

            if ($lockedInvoice->status === Invoice::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoice ini sudah dibatalkan. Pembayaran tidak dapat dicatat.',
                ]);
            }

            $status = $data['status'] ?? Payment::STATUS_VERIFIED;
            $amount = round((float) $data['amount'], 2);
            $paidBefore = $this->verifiedPaymentsTotal($lockedInvoice);
            $remainingBefore = round(
                max(0, (float) $lockedInvoice->total_amount - $paidBefore),
                2,
                PHP_ROUND_HALF_UP,
            );

            if ($remainingBefore <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Invoice sudah lunas. Pembayaran tambahan tidak dapat dicatat.',
                ]);
            }

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
                if ($paidBefore <= 0 && $amount > 0 && ! $this->fifoInventory->hasConsumedInventory($lockedInvoice)) {
                    $this->consumeInvoiceInventory($lockedInvoice, $recordedBy);
                }

                $this->updateInvoicePaymentStatus($lockedInvoice, $paidBefore + $amount);
                $this->cashBank->recordPayment($payment);
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

    private function consumeInvoiceInventory(Invoice $invoice, ?int $actorId): void
    {
        $items = $invoice->items()->with('product')->get();
        $alerts = $this->recordInvoiceSaleMovements->handle($invoice, $items, $actorId);
        $totalHpp = (float) $invoice->items()->sum('hpp_total');
        $companyShipping = $invoice->shipping_type === Invoice::SHIPPING_COMPANY_FREE_SHIPPING
            ? (float) $invoice->shipping_cost
            : 0;

        $invoice->forceFill([
            'metadata' => array_merge($invoice->metadata ?? [], [
                'inventory_alerts' => $alerts,
                'inventory_reserved_at' => now()->toISOString(),
            ]),
            'total_hpp' => round($totalHpp, 2),
            'gross_profit' => round((float) $invoice->total_amount - $totalHpp - $companyShipping, 2),
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
