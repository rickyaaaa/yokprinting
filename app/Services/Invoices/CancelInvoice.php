<?php

namespace App\Services\Invoices;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\CashBank\CashBankService;
use App\Services\Inventory\FifoInventoryService;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelInvoice
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly FifoInventoryService $fifoInventory,
        private readonly CashBankService $cashBank,
    ) {}

    /**
     * Close an order by cancelling its invoice, e.g. when the order falls through.
     */
    public function handle(Invoice $invoice, User $actor, ?string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status === Invoice::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'Invoice ini sudah dibatalkan sebelumnya.',
                ]);
            }

            if ($lockedInvoice->production_status === Invoice::PRODUCTION_COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => 'Invoice yang produksinya sudah selesai tidak bisa dibatalkan.',
                ]);
            }

            $payments = $lockedInvoice->payments()->get();

            // Keep the payment/audit rows, but reverse their automatic Kas &
            // Bank entries. This makes cancellation consistent across sales,
            // receivables, customer statements, and cash reports.
            $payments->each(fn (Payment $payment) => $this->cashBank->cancelPaymentTransaction($payment, $actor->getKey()));

            $this->fifoInventory->restoreInvoice($lockedInvoice, $actor->getKey());

            $previousStatus = $lockedInvoice->status;

            $lockedInvoice->forceFill([
                'status' => Invoice::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by' => $actor->getKey(),
            ])->save();

            $this->activityLogger->record(
                module: 'invoice',
                action: 'cancelled',
                event: 'Invoice cancelled (order closed)',
                description: "Order {$lockedInvoice->invoice_number} ditutup/dibatalkan.",
                subject: $lockedInvoice,
                metadata: array_filter([
                    'before' => $previousStatus,
                    'reason' => $reason,
                    'payment_count' => $payments->count(),
                    'verified_payment_amount' => $payments
                        ->where('status', Payment::STATUS_VERIFIED)
                        ->sum(fn (Payment $payment): float => (float) $payment->amount),
                ]),
                riskLevel: ActivityLog::RISK_HIGH,
                actor: $actor,
            );

            return $lockedInvoice->refresh();
        });
    }
}
