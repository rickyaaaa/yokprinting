<?php

namespace App\Services\Invoices;

use App\Mail\DueInvoiceReminderMail;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Security\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendDueInvoiceReminders
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * Send due invoice reminders for unpaid invoices due within the configured window.
     */
    public function handle(int $daysAhead = 3): int
    {
        $today = today();
        $until = $today->copy()->addDays($daysAhead);
        $sentCount = 0;

        Invoice::query()
            ->with('customer')
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query
                    ->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('payment_status', '!=', Invoice::PAYMENT_PAID)
            ->whereDate('due_date', '<=', $until)
            ->orderBy('due_date')
            ->each(function (Invoice $invoice) use ($today, &$sentCount): void {
                if (! $invoice->customer?->email || $this->wasReminderSentToday($invoice, $today)) {
                    return;
                }

                $outstandingAmount = $this->outstandingAmount($invoice);
                $notificationStatus = $this->notificationStatus($invoice, $today);

                Mail::to($invoice->customer->email)
                    ->send(new DueInvoiceReminderMail($invoice, $outstandingAmount, $notificationStatus));

                $this->markReminderSent($invoice, $notificationStatus);
                $this->recordReminderActivity($invoice, $outstandingAmount, $notificationStatus);

                $sentCount++;
            });

        return $sentCount;
    }

    private function wasReminderSentToday(Invoice $invoice, Carbon $today): bool
    {
        $lastSentAt = data_get($invoice->metadata, 'due_reminder.last_sent_at');

        return is_string($lastSentAt) && str_starts_with($lastSentAt, $today->toDateString());
    }

    private function outstandingAmount(Invoice $invoice): float
    {
        return max(0, (float) $invoice->total_amount - (float) ($invoice->verified_paid_amount ?? 0));
    }

    private function notificationStatus(Invoice $invoice, Carbon $today): string
    {
        if ($invoice->due_date->lt($today) || $invoice->payment_status === Invoice::PAYMENT_OVERDUE) {
            return 'overdue';
        }

        if ($invoice->due_date->isSameDay($today)) {
            return 'due_today';
        }

        return 'due_soon';
    }

    private function markReminderSent(Invoice $invoice, string $notificationStatus): void
    {
        $metadata = $invoice->metadata ?? [];
        $previousCount = (int) data_get($metadata, 'due_reminder.sent_count', 0);

        data_set($metadata, 'due_reminder.last_sent_at', now()->toISOString());
        data_set($metadata, 'due_reminder.last_status', $notificationStatus);
        data_set($metadata, 'due_reminder.last_recipient', $invoice->customer?->email);
        data_set($metadata, 'due_reminder.sent_count', $previousCount + 1);

        $invoice->forceFill(['metadata' => $metadata])->save();
    }

    private function recordReminderActivity(Invoice $invoice, float $outstandingAmount, string $notificationStatus): void
    {
        $this->activityLogger->record(
            module: 'invoice',
            action: 'due_reminder_sent',
            event: 'Due invoice reminder email sent',
            description: "Reminder jatuh tempo invoice {$invoice->invoice_number} dikirim.",
            subject: $invoice,
            metadata: [
                'invoice_number' => $invoice->invoice_number,
                'recipient' => $invoice->customer?->email,
                'notification_status' => $notificationStatus,
                'outstanding_amount' => $outstandingAmount,
            ],
            riskLevel: $notificationStatus === 'overdue' ? ActivityLog::RISK_MEDIUM : ActivityLog::RISK_LOW,
        );
    }
}
