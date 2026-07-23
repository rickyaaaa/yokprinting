<?php

namespace App\Jobs;

use App\Services\Invoices\SendDueInvoiceReminders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDueInvoiceReminderEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $daysAhead = 3,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SendDueInvoiceReminders $sendDueInvoiceReminders): int
    {
        return $sendDueInvoiceReminders->handle($this->daysAhead);
    }
}
