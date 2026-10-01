<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSalesReportInvoicesRequest;
use App\Models\Invoice;
use App\Services\Reports\GenerateSalesReportSpreadsheet;
use App\Services\Reports\GeneratedReportFile;
use App\Support\SalesReportPeriodPresets;
use Illuminate\Http\Response;

class SalesReportExportController extends Controller
{
    /**
     * Export filtered sales report invoice rows as a formatted XLSX workbook.
     */
    public function __invoke(
        ListSalesReportInvoicesRequest $request,
        GenerateSalesReportSpreadsheet $spreadsheet,
    ): Response
    {
        $filters = $request->validated();
        $rows = $this->rows($filters);
        $range = SalesReportPeriodPresets::resolve($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        return $this->download($spreadsheet->generate(
            $rows,
            $range['from'],
            $range['to'],
            $this->filterSummary($filters),
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $filters): array
    {
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
            ->businessTransaction()
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
            ->all();
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
            'margin_label' => $invoice->grossMarginLabel(),
            'margin_percent' => $invoice->grossMarginPercentage(),
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Invoice::PAYMENT_PAID => 'Lunas',
            Invoice::PAYMENT_PARTIAL => 'Parsial',
            Invoice::PAYMENT_OVERDUE => 'Overdue',
            default => 'Menunggu',
        };
    }

    /** @param array<string, mixed> $filters */
    private function filterSummary(array $filters): ?string
    {
        $parts = [];
        $status = $filters['status'] ?? 'all';

        if ($status !== 'all') {
            $parts[] = 'Status: '.match ($status) {
                Invoice::PAYMENT_PAID => 'Lunas',
                Invoice::PAYMENT_PARTIAL => 'Parsial',
                Invoice::PAYMENT_UNPAID => 'Belum bayar',
                Invoice::PAYMENT_OVERDUE => 'Jatuh tempo',
                default => $status,
            };
        }

        if (($filters['category'] ?? null) !== null && trim((string) $filters['category']) !== '') {
            $parts[] = 'Kategori: '.trim((string) $filters['category']);
        }

        if (($filters['q'] ?? null) !== null && trim((string) $filters['q']) !== '') {
            $parts[] = 'Pencarian: "'.trim((string) $filters['q']).'"';
        }

        return $parts === [] ? null : 'Filter - '.implode(' | ', $parts);
    }

    private function download(GeneratedReportFile $file): Response
    {
        return response($file->contents, Response::HTTP_OK, [
            'Content-Type' => $file->contentType,
            'Content-Disposition' => 'attachment; filename="'.$file->filename.'"',
            'Content-Length' => (string) strlen($file->contents),
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
