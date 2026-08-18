<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class CustomerActivityAlertController extends Controller
{
    /**
     * Return customers whose last paid order is older than the follow-up threshold.
     */
    public function __invoke(): JsonResponse
    {
        $customers = Customer::query()
            ->needsFollowUp()
            ->withMax([
                'invoices as last_paid_order_at' => fn ($query) => $query
                    ->finalized()
                    ->where('payment_status', Invoice::PAYMENT_PAID),
            ], 'paid_at')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'count' => $customers->count(),
                'needs_attention' => $customers->isNotEmpty(),
                'threshold_days' => 30,
                'customers' => $customers->map(fn (Customer $customer): array => [
                    'id' => $customer->getKey(),
                    'code' => $customer->code,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'activity_status' => $customer->activity_status,
                    'last_paid_order_at' => $customer->last_paid_order_at,
                ])->values(),
            ],
        ]);
    }
}
