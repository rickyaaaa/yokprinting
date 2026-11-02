<?php

/*
 * Concurrency fixture for EditInvoiceNumberTest: separate PHP processes race
 * to give different invoices the same number against one shared database.
 *
 * Usage:
 *   php invoice-renumber-worker.php seed <count>             prints invoice IDs as JSON
 *   php invoice-renumber-worker.php renumber <id> <number> [<start-at>]
 *     prints "renumbered", "taken" or "failed: <reason>"
 */

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\Invoices\CreateInvoiceDraft;
use App\Services\Invoices\UpdateInvoiceDraft;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') === 'sqlite') {
    // Let competing writers wait for the lock instead of failing fast.
    config(['database.connections.sqlite.busy_timeout' => 60000]);
}

$payload = fn (Invoice $invoice): array => [
    'customer_id' => $invoice->customer_id,
    'issue_date' => $invoice->issue_date->toDateString(),
    'due_date' => $invoice->due_date->toDateString(),
    'items' => $invoice->items->map(fn ($item): array => [
        'product_id' => $item->product_id,
        'quantity' => (float) $item->quantity,
        'price' => (float) $item->unit_price,
    ])->all(),
    'discount' => ['type' => 'percentage', 'value' => 0],
    'tax' => ['enabled' => false, 'rate' => 0],
];

if (($argv[1] ?? '') === 'seed') {
    $customer = Customer::query()->create(['name' => 'PT Renumber Race']);
    $product = Product::query()->create([
        'name' => 'Produk Renumber Race',
        'sku' => 'RACE-01',
        'minimum_order_qty' => 1,
        'package_conversion' => 1,
    ]);
    $ids = [];

    for ($i = 0; $i < (int) ($argv[2] ?? 2); $i++) {
        $ids[] = app(CreateInvoiceDraft::class)->handle([
            'customer_id' => $customer->id,
            'issue_date' => '2026-11-02',
            'due_date' => '2026-11-16',
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => 100000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->id;
    }

    echo json_encode($ids);
    exit(0);
}

$invoice = Invoice::query()->with('items')->findOrFail((int) $argv[2]);
$data = [
    ...$payload($invoice),
    'invoice_number' => $argv[3],
    'invoice_number_change_reason' => 'Uji perubahan nomor bersamaan',
];

// Start all workers at the same moment so their transactions overlap.
$startAt = (float) ($argv[4] ?? 0);
while (microtime(true) < $startAt) {
    usleep(1000);
}

try {
    app(UpdateInvoiceDraft::class)->handle($invoice, $data);
    echo 'renumbered'.PHP_EOL;
} catch (ValidationException) {
    echo 'taken'.PHP_EOL;
} catch (Throwable $exception) {
    echo 'failed: '.$exception->getMessage().PHP_EOL;
}
