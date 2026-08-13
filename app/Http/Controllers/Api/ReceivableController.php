<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListReceivablesRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ReceivableController extends Controller
{
    /**
     * List outstanding receivables with search, status filter, and sorting.
     */
    public function index(ListReceivablesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $status = $filters['status'] ?? 'all';
        $keyword = $filters['q'] ?? null;
        $sort = $filters['sort'] ?? 'due_date';
        $direction = $filters['direction'] ?? 'asc';

        $query = Invoice::query()
            ->with('customer')
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query
                    ->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->receivable();

        if ($status === 'overdue') {
            $query->overdue();
        } elseif ($status === 'partial') {
            $query->where('payment_status', Invoice::PAYMENT_PARTIAL);
        } elseif ($status === 'unpaid') {
            $query
                ->where('payment_status', Invoice::PAYMENT_UNPAID)
                ->whereDate('due_date', '>=', today());
        }

        if (is_string($keyword) && trim($keyword) !== '') {
            $keyword = trim($keyword);
            $query->where(function ($query) use ($keyword): void {
                $query
                    ->where('invoice_number', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', function ($query) use ($keyword): void {
                        $query->where('name', 'like', "%{$keyword}%");
                    });
            });
        }

        $receivables = $this->sortReceivables(
            $query->get()->map(fn (Invoice $invoice): array => $this->formatReceivable($invoice)),
            $sort,
            $direction,
        )->values();

        return response()->json([
            'status' => 'success',
            'data' => $receivables,
            'meta' => [
                'total' => $receivables->count(),
                'status' => $status,
                'q' => $keyword,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReceivable(Invoice $invoice): array
    {
        $paidAmount = (float) ($invoice->verified_paid_amount ?? 0);
        $totalAmount = (float) $invoice->total_amount;
        $outstandingAmount = max(0, $totalAmount - $paidAmount);
        $isOverdue = $invoice->due_date->isPast() && $invoice->payment_status !== Invoice::PAYMENT_PAID;
        $status = match (true) {
            $isOverdue => 'overdue',
            $invoice->payment_status === Invoice::PAYMENT_PARTIAL => 'partial',
            default => 'unpaid',
        };

        return [
            'invoice_number' => $invoice->invoice_number,
            'customer' => [
                'id' => $invoice->customer?->getKey(),
                'name' => $invoice->customer?->name,
                'email' => $invoice->customer?->email,
            ],
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'total_amount_formatted' => $this->formatRupiah($totalAmount),
            'paid_amount_formatted' => $this->formatRupiah($paidAmount),
            'outstanding_amount_formatted' => $this->formatRupiah($outstandingAmount),
            'status' => $status,
            'status_label' => match ($status) {
                'overdue' => 'Overdue',
                'partial' => 'Parsial',
                default => 'Menunggu',
            },
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $receivables
     * @return Collection<int, array<string, mixed>>
     */
    private function sortReceivables(Collection $receivables, string $sort, string $direction): Collection
    {
        $sorted = $receivables->sortBy(match ($sort) {
            'outstanding' => 'outstanding_amount',
            'customer' => 'customer.name',
            'invoice_number' => 'invoice_number',
            default => 'due_date',
        }, SORT_REGULAR, $direction === 'desc');

        return $sorted->values();
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
