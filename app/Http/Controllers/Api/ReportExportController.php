<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReportCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListReceivablesRequest;
use App\Http\Requests\ListStockMovementReportRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

class ReportExportController extends Controller
{
    public function outstandingPayments(ListReceivablesRequest $request, ReportCsvExport $export): Response
    {
        $rows = Invoice::query()
            ->with('customer')
            ->withSum([
                'payments as verified_paid_amount' => fn ($query) => $query
                    ->where('status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('payment_status', '!=', Invoice::PAYMENT_PAID)
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $invoice): array => [
                $invoice->invoice_number,
                $invoice->customer?->name,
                $invoice->issue_date->toDateString(),
                $invoice->due_date->toDateString(),
                (float) $invoice->total_amount,
                (float) ($invoice->verified_paid_amount ?? 0),
                max(0, (float) $invoice->total_amount - (float) ($invoice->verified_paid_amount ?? 0)),
                $invoice->due_date->isPast() ? 'Overdue' : $invoice->payment_status,
            ]);

        return $export->download(
            'laporan-piutang-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['Invoice', 'Customer', 'Tanggal Invoice', 'Jatuh Tempo', 'Total', 'Terbayar', 'Sisa Piutang', 'Status'],
            $rows,
        );
    }

    public function inactiveCustomers(ReportCsvExport $export): Response
    {
        $rows = Customer::query()
            ->needsFollowUp()
            ->withMax([
                'invoices as last_paid_order_at' => fn ($query) => $query
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->where('payment_status', Invoice::PAYMENT_PAID),
            ], 'paid_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer): array => [
                $customer->code,
                $customer->name,
                $customer->email,
                $customer->phone,
                $customer->activity_status,
                $customer->last_paid_order_at,
            ]);

        return $export->download(
            'laporan-customer-inaktif-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['Kode', 'Customer', 'Email', 'Telepon', 'Status Aktivitas', 'Order Lunas Terakhir'],
            $rows,
        );
    }

    public function lowStock(ReportCsvExport $export): Response
    {
        $rows = Product::query()
            ->lowStock()
            ->orderByRaw('(COALESCE(minimum_stock, ?) - stock) desc', [Product::DEFAULT_MINIMUM_STOCK])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product): array => [
                $product->sku,
                $product->name,
                $product->category,
                $product->unit,
                (float) $product->stock,
                $product->minimumStockValue(),
                max(0, $product->minimumStockValue() - (float) $product->stock),
            ]);

        return $export->download(
            'laporan-stok-menipis-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['SKU', 'Produk', 'Kategori', 'Unit', 'Stok', 'Minimum Stok', 'Kekurangan'],
            $rows,
        );
    }

    public function stockMutations(ListStockMovementReportRequest $request, ReportCsvExport $export): Response
    {
        $filters = $request->validated();
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->startOfMonth())->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'] ?? now())->endOfDay();
        $adjustmentTypes = [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_STOCK_OPNAME];

        $rows = Product::query()
            ->when($filters['product_id'] ?? null, fn ($query, int $productId) => $query->whereKey($productId))
            ->whereHas('stockMovements', fn ($query) => $query->where('created_at', '<=', $end))
            ->with(['stockMovements' => fn ($query) => $query->where('created_at', '<=', $end)->orderBy('created_at')->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($start, $end, $adjustmentTypes): array {
                $movements = $product->stockMovements;
                $opening = (float) $movements->where('created_at', '<', $start)->sum('quantity');
                $period = $movements->where('created_at', '>=', $start)->where('created_at', '<=', $end);
                $regular = $period->whereNotIn('type', $adjustmentTypes);
                $incoming = (float) $regular->where('quantity', '>', 0)->sum('quantity');
                $outgoing = abs((float) $regular->where('quantity', '<', 0)->sum('quantity'));
                $adjustments = (float) $period->whereIn('type', $adjustmentTypes)->sum('quantity');

                return [
                    $product->sku,
                    $product->name,
                    $product->unit,
                    round($opening, 4),
                    round($incoming, 4),
                    round($outgoing, 4),
                    round($adjustments, 4),
                    round($opening + (float) $period->sum('quantity'), 4),
                ];
            });

        return $export->download(
            'laporan-mutasi-stok-'.CarbonImmutable::now()->format('Y-m-d').'.csv',
            ['SKU', 'Produk', 'Unit', 'Saldo Awal', 'Masuk', 'Keluar', 'Adjustment', 'Saldo Akhir'],
            $rows,
        );
    }
}
