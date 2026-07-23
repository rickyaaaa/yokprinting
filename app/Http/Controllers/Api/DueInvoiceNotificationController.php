<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListDueInvoiceNotificationsRequest;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DueInvoiceNotificationController extends Controller
{
    /**
     * List invoices that need due-date notification follow-up.
     */
    public function index(ListDueInvoiceNotificationsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $days = (int) ($filters['days'] ?? 7);
        $status = $filters['status'] ?? 'all';
        $keyword = trim($filters['q'] ?? '');
        $limit = (int) ($filters['limit'] ?? 100);
        $sort = $filters['sort'] ?? 'due_date';
        $direction = $filters['direction'] ?? 'asc';
        $today = today();
        $until = $today->copy()->addDays($days);

        $query = Invoice::query()
            ->with('customer')
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query
                    ->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('payment_status', '!=', Invoice::PAYMENT_PAID)
            ->whereDate('due_date', '<=', $until);

        if ($status === 'overdue') {
            $query->where(function (Builder $query) use ($today): void {
                $query
                    ->whereDate('due_date', '<', $today)
                    ->orWhere('payment_status', Invoice::PAYMENT_OVERDUE);
            });
        } elseif ($status === 'due_today') {
            $query->whereDate('due_date', $today);
        } elseif ($status === 'due_soon') {
            $query
                ->whereDate('due_date', '>', $today)
                ->whereDate('due_date', '<=', $until);
        }

        if ($keyword !== '') {
            $query->where(function (Builder $query) use ($keyword): void {
                $query
                    ->where('invoice_number', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', function (Builder $query) use ($keyword): void {
                        $query
                            ->where('name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%");
                    });
            });
        }

        $notifications = $this->sortNotifications(
            $query->get()
                ->map(fn (Invoice $invoice): array => $this->formatInvoice($invoice, $today)),
            $sort,
            $direction,
        )
            ->take($limit)
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $notifications,
            'summary' => $this->summarize($notifications),
            'meta' => [
                'count' => $notifications->count(),
                'limit' => $limit,
                'filters' => [
                    'q' => $keyword,
                    'status' => $status,
                    'days' => $days,
                    'until' => $until->toDateString(),
                ],
                'sort' => [
                    'key' => $sort,
                    'direction' => $direction,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInvoice(Invoice $invoice, Carbon $today): array
    {
        $paidAmount = (float) ($invoice->verified_paid_amount ?? 0);
        $totalAmount = (float) $invoice->total_amount;
        $outstandingAmount = max(0, $totalAmount - $paidAmount);
        $dueDate = $invoice->due_date;
        $status = match (true) {
            $dueDate->lt($today) || $invoice->payment_status === Invoice::PAYMENT_OVERDUE => 'overdue',
            $dueDate->isSameDay($today) => 'due_today',
            default => 'due_soon',
        };
        $daysFromDue = (int) $today->diffInDays($dueDate, false);

        return [
            'invoice_number' => $invoice->invoice_number,
            'customer' => [
                'id' => $invoice->customer?->getKey(),
                'name' => $invoice->customer?->name,
                'email' => $invoice->customer?->email,
                'phone' => $invoice->customer?->phone,
            ],
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'days_from_due' => $daysFromDue,
            'days_overdue' => $daysFromDue < 0 ? abs($daysFromDue) : 0,
            'days_until_due' => $daysFromDue > 0 ? $daysFromDue : 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'outstanding_amount_formatted' => $this->formatRupiah($outstandingAmount),
            'payment_status' => $invoice->payment_status,
            'notification_status' => $status,
            'notification_label' => match ($status) {
                'overdue' => 'Lewat jatuh tempo',
                'due_today' => 'Jatuh tempo hari ini',
                default => 'Segera jatuh tempo',
            },
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $notifications
     * @return array<string, mixed>
     */
    private function summarize(Collection $notifications): array
    {
        $outstandingAmount = (float) $notifications->sum('outstanding_amount');

        return [
            'total_count' => $notifications->count(),
            'overdue_count' => $notifications->where('notification_status', 'overdue')->count(),
            'due_today_count' => $notifications->where('notification_status', 'due_today')->count(),
            'due_soon_count' => $notifications->where('notification_status', 'due_soon')->count(),
            'outstanding_amount' => $outstandingAmount,
            'outstanding_amount_formatted' => $this->formatRupiah($outstandingAmount),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    private function sortNotifications(Collection $notifications, string $sort, string $direction): Collection
    {
        return $notifications->sortBy(match ($sort) {
            'outstanding' => 'outstanding_amount',
            'customer' => 'customer.name',
            'invoice_number' => 'invoice_number',
            default => 'due_date',
        }, SORT_REGULAR, $direction === 'desc');
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
