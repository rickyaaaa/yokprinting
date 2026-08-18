<?php

namespace App\Services\Invoices;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Facades\DB;

class RecordInvoiceFollowUp
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * Record that a user followed up on an invoice's outstanding payment.
     */
    public function handle(Invoice $invoice, User $actor, ?string $note): Invoice
    {
        return DB::transaction(function () use ($invoice, $actor, $note): Invoice {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedInvoice->forceFill([
                'last_follow_up_at' => now(),
                'last_follow_up_note' => $note,
                'last_follow_up_by' => $actor->getKey(),
            ])->save();

            $this->activityLogger->record(
                module: 'invoice',
                action: 'follow_up_recorded',
                event: 'Invoice follow-up recorded',
                description: "Follow-up untuk invoice {$lockedInvoice->invoice_number} dicatat.",
                subject: $lockedInvoice,
                metadata: array_filter(['note' => $note]),
                riskLevel: ActivityLog::RISK_LOW,
                actor: $actor,
            );

            return $lockedInvoice->refresh();
        });
    }
}
