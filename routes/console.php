<?php

use App\Jobs\CleanupTemporaryReportFilesJob;
use App\Jobs\MarkOverdueInvoicesJob;
use App\Jobs\PurgeExpiredExpensesJob;
use App\Jobs\RetryExpenseProofCleanupJob;
use App\Jobs\SendDueInvoiceReminderEmailsJob;
use App\Jobs\UpdateCustomerFollowUpStatusesJob;
use App\Services\Operations\OperationalHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(OperationalHealth::class)->schedulerTick())
    ->everyMinute()
    ->name('operational-heartbeats')
    ->withoutOverlapping();

Schedule::job(new MarkOverdueInvoicesJob)
    ->dailyAt('07:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->name('mark-overdue-invoices');

Schedule::job(new SendDueInvoiceReminderEmailsJob)
    ->dailyAt('08:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->name('send-due-invoice-reminders');

Schedule::job(new UpdateCustomerFollowUpStatusesJob)
    ->dailyAt('08:30')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->name('update-customer-follow-up-statuses');

Schedule::job(new RetryExpenseProofCleanupJob)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('retry-expense-proof-cleanup');

Schedule::job(new CleanupTemporaryReportFilesJob)
    ->hourly()
    ->withoutOverlapping()
    ->name('cleanup-temporary-report-files');

Schedule::job(new PurgeExpiredExpensesJob)
    ->dailyAt('02:30')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->name('purge-expired-expenses');
