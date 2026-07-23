<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Services\Security\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkOverdueInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(ActivityLogger $activityLogger): int
    {
        $invoiceIds = Invoice::query()
            ->whereDate('due_date', '<', today())
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->whereIn('payment_status', [Invoice::PAYMENT_UNPAID, Invoice::PAYMENT_PARTIAL])
            ->pluck('id')
            ->all();

        $markedCount = count($invoiceIds);

        if ($markedCount > 0) {
            Invoice::query()
                ->whereIn('id', $invoiceIds)
                ->update([
                    'payment_status' => Invoice::PAYMENT_OVERDUE,
                    'updated_at' => now(),
                ]);
        }

        $activityLogger->record(
            module: 'invoice',
            action: 'overdue_check',
            event: 'Scheduled overdue invoice check completed',
            description: 'Pengecekan invoice jatuh tempo terjadwal selesai.',
            metadata: [
                'marked_count' => $markedCount,
                'invoice_ids' => $invoiceIds,
                'checked_for_date' => today()->toDateString(),
            ],
            riskLevel: $markedCount > 0 ? ActivityLog::RISK_MEDIUM : ActivityLog::RISK_LOW,
        );

        return $markedCount;
    }
}
