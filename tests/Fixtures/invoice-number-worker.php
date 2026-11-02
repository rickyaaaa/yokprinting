<?php

/*
 * Concurrency fixture for GenerateInvoiceNumberTest: run as a separate PHP
 * process so several workers race for invoice numbers against one shared
 * database.
 *
 * Usage: php invoice-number-worker.php <count> <issue-date>
 */

use App\Models\Invoice;
use App\Services\Invoices\GenerateInvoiceNumber;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') === 'sqlite') {
    // Let competing writers wait for the lock instead of failing fast.
    config(['database.connections.sqlite.busy_timeout' => 60000]);
}

$count = (int) ($argv[1] ?? 1);
$issueDate = $argv[2] ?? now()->toDateString();
$generator = app(GenerateInvoiceNumber::class);

for ($i = 0; $i < $count; $i++) {
    // Mirrors CreateInvoiceDraft: number generation and the invoice insert
    // share one transaction.
    DB::transaction(function () use ($generator, $issueDate): void {
        Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => $generator->generate(CarbonImmutable::parse($issueDate)),
            'issue_date' => $issueDate,
            'due_date' => $issueDate,
        ]);
    });
}
