<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListPaymentHistoryRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class PaymentHistoryController extends Controller
{
    /**
     * List payment history with status, method, date, search, and sorting filters.
     */
    public function index(ListPaymentHistoryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $status = $filters['status'] ?? 'all';
        $method = $filters['method'] ?? 'all';
        $keyword = $filters['q'] ?? null;
        $sort = $filters['sort'] ?? 'payment_date';
        $direction = $filters['direction'] ?? 'desc';

        $query = Payment::query()
            ->with(['invoice.customer']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($method !== 'all') {
            $query->where('method', $method);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        if (is_string($keyword) && trim($keyword) !== '') {
            $keyword = trim($keyword);
            $query->where(function ($query) use ($keyword): void {
                $query
                    ->where('payment_number', 'like', "%{$keyword}%")
                    ->orWhere('reference', 'like', "%{$keyword}%")
                    ->orWhereHas('invoice', function ($query) use ($keyword): void {
                        $query->where('invoice_number', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('invoice.customer', function ($query) use ($keyword): void {
                        $query->where('name', 'like', "%{$keyword}%");
                    });
            });
        }

        $payments = $this->sortPayments(
            $query->get()->map(fn (Payment $payment): array => $this->formatPayment($payment)),
            $sort,
            $direction,
        )->values();

        return response()->json([
            'status' => 'success',
            'data' => $payments,
            'meta' => [
                'total' => $payments->count(),
                'status' => $status,
                'method' => $method,
                'q' => $keyword,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPayment(Payment $payment): array
    {
        return [
            'payment_number' => $payment->payment_number,
            'payment_date' => $payment->payment_date->toDateString(),
            'invoice_number' => $payment->invoice?->invoice_number,
            'customer' => [
                'id' => $payment->invoice?->customer?->getKey(),
                'name' => $payment->invoice?->customer?->name,
                'email' => $payment->invoice?->customer?->email,
            ],
            'method' => $payment->method,
            'method_label' => $payment->methodLabel(),
            'reference' => $payment->reference,
            'amount' => (float) $payment->amount,
            'amount_formatted' => $this->formatRupiah((float) $payment->amount),
            'status' => $payment->status,
            'status_label' => $payment->statusLabel(),
            'verified_at' => $payment->verified_at?->toISOString(),
            'created_at' => $payment->created_at->toISOString(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payments
     * @return Collection<int, array<string, mixed>>
     */
    private function sortPayments(Collection $payments, string $sort, string $direction): Collection
    {
        $sorted = $payments->sortBy(match ($sort) {
            'amount' => 'amount',
            'customer' => 'customer.name',
            'invoice_number' => 'invoice_number',
            default => 'payment_date',
        }, SORT_REGULAR, $direction === 'desc');

        return $sorted->values();
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
