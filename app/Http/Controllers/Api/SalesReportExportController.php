<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSalesReportInvoicesRequest;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class SalesReportExportController extends Controller
{
    /**
     * Export filtered sales report invoice rows as an Excel-compatible CSV file.
     */
    public function __invoke(ListSalesReportInvoicesRequest $request): Response
    {
        $filters = $request->validated();
        $rows = $this->rows($filters);
        $csv = $this->csv($rows);
        $filename = 'laporan-penjualan-'.CarbonImmutable::now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|float|null>>
     */
    private function rows(array $filters): array
    {
        $dateFrom = $this->resolveDateFrom($filters['date_from'] ?? null);
        $dateTo = $this->resolveDateTo($filters['date_to'] ?? null);
        $status = $filters['status'] ?? 'all';
        $category = $filters['category'] ?? null;
        $keyword = $filters['q'] ?? null;
        $sort = $filters['sort'] ?? 'issue_date';
        $direction = $filters['direction'] ?? 'desc';

        $query = Invoice::query()
            ->with(['customer', 'items.product'])
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
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

        return $query->get()
            ->map(fn (Invoice $invoice): array => $this->formatInvoice($invoice))
            ->filter(fn (array $row): bool => $status === 'all' || $row['status'] === $status)
            ->sortBy(match ($sort) {
                'total_amount' => 'total_amount',
                'customer' => 'customer',
                'invoice_number' => 'invoice_number',
                'status' => 'status',
                default => 'issue_date',
            }, SORT_REGULAR, $direction === 'desc')
            ->values()
            ->map(fn (array $row): array => [
                $row['customer'],
                $row['customer_email'],
                $row['product'],
                $row['category'],
                $row['invoice_number'],
                $row['issue_date'],
                $row['due_date'],
                $row['total_amount'],
                $row['margin_label'],
                $row['status_label'],
            ])
            ->all();
    }

    private function resolveDateFrom(?string $date): CarbonImmutable
    {
        return $date !== null
            ? CarbonImmutable::parse($date)->startOfDay()
            : CarbonImmutable::now()->startOfMonth();
    }

    private function resolveDateTo(?string $date): CarbonImmutable
    {
        return $date !== null
            ? CarbonImmutable::parse($date)->endOfDay()
            : CarbonImmutable::now()->endOfDay();
    }

    /**
     * @return array<string, string|float|null>
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
            'customer' => $invoice->customer?->name,
            'customer_email' => $invoice->customer?->email,
            'product' => $productNames->count() > 1
                ? $primaryProduct.' + '.($productNames->count() - 1).' item'
                : $primaryProduct,
            'category' => $categories->count() > 1
                ? $primaryCategory.' + '.($categories->count() - 1).' kategori'
                : $primaryCategory,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'total_amount' => (float) $invoice->total_amount,
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
     * @param  array<int, array<int, string|float|null>>  $rows
     */
    private function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Pelanggan',
            'Email',
            'Produk',
            'Kategori',
            'Invoice',
            'Tanggal Invoice',
            'Jatuh Tempo',
            'Penjualan',
            'Margin',
            'Status',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\u{FEFF}".$csv;
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
}
