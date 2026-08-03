<?php

namespace App\Services\Invoices;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoiceProductionStatus
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Update production status under a row lock and record the committed change.
     */
    public function handle(Invoice $invoice, string $productionStatus): Invoice
    {
        return DB::transaction(function () use ($invoice, $productionStatus): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->status === Invoice::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'production_status' => 'Status produksi invoice yang dibatalkan tidak dapat diubah.',
                ]);
            }

            if (
                $productionStatus === Invoice::PRODUCTION_COMPLETED
                && $lockedInvoice->payment_status !== Invoice::PAYMENT_PAID
            ) {
                throw ValidationException::withMessages([
                    'production_status' => 'Invoice harus lunas sebelum ditandai Lunas & Selesai.',
                ]);
            }

            $previousStatus = $lockedInvoice->production_status;

            if ($previousStatus === $productionStatus) {
                return $lockedInvoice;
            }

            $lockedInvoice->forceFill(['production_status' => $productionStatus])->save();

            $this->activityLogger->record(
                module: 'invoice',
                action: 'production_status_updated',
                event: 'Invoice production status updated',
                description: "Status produksi {$lockedInvoice->invoice_number} diperbarui.",
                subject: $lockedInvoice,
                metadata: [
                    'before' => $previousStatus,
                    'after' => $productionStatus,
                ],
                riskLevel: ActivityLog::RISK_MEDIUM,
            );

            return $lockedInvoice->refresh();
        });
    }
}
