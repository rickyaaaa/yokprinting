<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateCustomerFollowUpStatusesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Customer::query()
            ->whereNull('deleted_at')
            ->withMax([
                'invoices as latest_paid_order_at' => fn ($query) => $query
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->where('payment_status', Invoice::PAYMENT_PAID),
            ], 'paid_at')
            ->chunkById(100, function ($customers): void {
                foreach ($customers as $customer) {
                    $lastOrderAt = $customer->latest_paid_order_at
                        ?? $customer->paidInvoices()
                            ->selectRaw('COALESCE(paid_at, issue_date) as latest_paid_at')
                            ->orderByRaw('COALESCE(paid_at, issue_date) desc')
                            ->value('latest_paid_at');

                    if (! $lastOrderAt) {
                        continue;
                    }

                    $daysWithoutOrder = now()->parse($lastOrderAt)->diffInDays(now());
                    $status = match (true) {
                        $daysWithoutOrder >= 60 => Customer::STATUS_AUTO_FOLLOWUP,
                        $daysWithoutOrder >= 30 => Customer::STATUS_INACTIVE_1M,
                        default => Customer::STATUS_ACTIVE,
                    };

                    $customer->forceFill([
                        'last_order_at' => $lastOrderAt,
                        'status' => $status,
                    ])->save();

                    if ($daysWithoutOrder >= 30) {
                        Log::info('Pelanggan belum order 1 bulan', [
                            'customer_id' => $customer->getKey(),
                            'code' => $customer->code,
                            'name' => $customer->name,
                            'days_without_order' => $daysWithoutOrder,
                            'status' => $status,
                        ]);
                    }
                }
            });
    }
}
