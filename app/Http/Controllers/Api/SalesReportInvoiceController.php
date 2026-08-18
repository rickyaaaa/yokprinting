<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSalesReportInvoicesRequest;
use App\Models\Invoice;
use App\Support\SalesReportPeriodPresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SalesReportInvoiceController extends Controller
{
    /**
     * List sales report invoices with date, status, category, search, and sort filters.
     */
    public function index(ListSalesReportInvoicesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $range = SalesReportPeriodPresets::resolve($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $dateFrom = $range['from'];
        $dateTo = $range['to'];
        $status = $filters['status'] ?? 'all';
        $category = $filters['category'] ?? null;
        $keyword = $filters['q'] ?? null;
        $sort = $filters['sort'] ?? 'issue_date';
        $direction = $filters['direction'] ?? 'desc';

        $query = Invoice::query()
            ->with(['customer', 'items.product'])
            ->finalized()
            ->whereBetween('issue_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        if (is_string($category) && trim($category) !== '') {
            $category = trim($category);
            $query->whereHas('items.product', function ($query) use ($category): void {
                $query->where('category', $category);
            });
        }

        if (is_string($keyword) && trim($keyword) !== '') {
            $keyword = trim($keyword);
            $query->where(function ($query) use ($keyword): void {
                $query
                    ->where('invoice_number', 'like', "%{$keyword}%")
                    ->orWhereHas('customer', function ($query) use ($keyword): void {
                        $query->where('name', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('items', function ($query) use ($keyword): void {
                        $query
                            ->where('product_name', 'like', "%{$keyword}%")
                            ->orWhere('sku', 'like', "%{$keyword}%");
                    });
            });
        }

        $rows = $query->get()
            ->map(fn (Invoice $invoice): array => $this->formatInvoice($invoice))
            ->filter(fn (array $row): bool => $status === 'all' || $row['status'] === $status);

        $rows = $this->sortRows($rows, $sort, $direction)->values();

        return response()->json([
            'status' => 'success',
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'status' => $status,
                'category' => $category,
                'q' => $keyword,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInvoice(Invoice $invoice): array
    {
        $status = $this->resolveStatus($invoice);
        $categories = $invoice->items
            ->map(fn ($item): ?string => $item->product?->category)
            ->filter()
            ->unique()
            ->values();
        $productNames = $invoice->items
            ->pluck('product_name')
            ->filter()
            ->unique()
            ->values();
        $primaryCategory = $categories->first() ?? 'Tanpa kategori';
        $primaryProduct = $productNames->first() ?? 'Tanpa item';

        return [
            'invoice_number' => $invoice->invoice_number,
            'customer' => [
                'id' => $invoice->customer?->getKey(),
                'name' => $invoice->customer?->name,
                'email' => $invoice->customer?->email,
            ],
            'product' => $productNames->count() > 1
                ? $primaryProduct.' + '.($productNames->count() - 1).' item'
                : $primaryProduct,
            'category' => $categories->count() > 1
                ? $primaryCategory.' + '.($categories->count() - 1).' kategori'
                : $primaryCategory,
            'categories' => $categories->all(),
            'issue_date' => $invoice->issue_date->toDateString(),
            'issue_date_formatted' => $invoice->issue_date->translatedFormat('d M Y'),
            'due_date' => $invoice->due_date->toDateString(),
            'total_amount' => (float) $invoice->total_amount,
            'total_amount_formatted' => $this->formatRupiah((float) $invoice->total_amount),
            'margin_percentage' => null,
            'margin_label' => 'Belum tersedia',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
        ];
    }

    private function resolveStatus(Invoice $invoice): string
    {
        if (
            $invoice->due_date->isPast()
            && $invoice->payment_status !== Invoice::PAYMENT_PAID
        ) {
            return Invoice::PAYMENT_OVERDUE;
        }

        return $invoice->payment_status;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows, string $sort, string $direction): Collection
    {
        return $rows->sortBy(match ($sort) {
            'total_amount' => 'total_amount',
            'customer' => 'customer.name',
            'invoice_number' => 'invoice_number',
            'status' => 'status',
            default => 'issue_date',
        }, SORT_REGULAR, $direction === 'desc');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Invoice::PAYMENT_PAID => 'Lunas',
            Invoice::PAYMENT_PARTIAL => 'Parsial',
            Invoice::PAYMENT_OVERDUE => 'Overdue',
            default => 'Menunggu',
        };
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
