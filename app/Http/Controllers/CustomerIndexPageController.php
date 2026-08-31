<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class CustomerIndexPageController extends Controller
{
    /**
     * Show customers using the persisted customer and invoice data.
     */
    public function __invoke(): View
    {
        $customerModels = Customer::query()
            ->withCount([
                'invoices as active_invoice_count' => fn ($query) => $query->businessTransaction(),
            ])
            ->withSum([
                'invoices as active_sales_total' => fn ($query) => $query->businessTransaction(),
            ], 'total_amount')
            ->withSum([
                'payments as verified_paid_total' => fn ($query) => $query
                    ->where('invoices.status', '!=', Invoice::STATUS_CANCELLED)
                    ->where('payments.status', Payment::STATUS_VERIFIED),
            ], 'amount')
            ->withMax([
                'invoices as active_last_order_at' => fn ($query) => $query->businessTransaction(),
            ], 'issue_date')
            ->orderBy('name')
            ->get();

        $customers = $customerModels->map(function (Customer $customer): array {
            $totalSales = (float) ($customer->active_sales_total ?? 0);
            $outstanding = max(0, $totalSales - (float) ($customer->verified_paid_total ?? 0));
            $lastOrder = $customer->active_last_order_at
                ? CarbonImmutable::parse($customer->active_last_order_at)
                : null;

            return [
                'id' => $customer->getKey(),
                'code' => $customer->code,
                'name' => $customer->name,
                'initials' => $customer->initials(),
                'segment' => '-',
                'email' => $customer->email,
                'phone' => $customer->phone ?: '-',
                'city' => $customer->city ?: '-',
                'lastOrder' => $lastOrder?->locale('id')->translatedFormat('j M Y') ?? 'Belum ada transaksi',
                'lastOrderSort' => $lastOrder ? (int) $lastOrder->format('Ymd') : 0,
                'totalSales' => $this->formatRupiah($totalSales),
                'totalSalesValue' => $totalSales,
                'outstanding' => $this->formatRupiah($outstanding),
                'outstandingValue' => $outstanding,
                'status' => $this->statusLabel($customer),
                'invoiceCount' => (int) ($customer->active_invoice_count ?? 0),
            ];
        })->values();

        return view('customers.index', [
            'customers' => $customers,
            'summaryCards' => $this->summaryCards($customerModels, $customers),
            'canDeleteCustomer' => request()->user()?->hasPermission('customer.delete') ?? false,
        ]);
    }

    /**
     * @param  Collection<int, Customer>  $customerModels
     * @param  Collection<int, array<string, mixed>>  $customers
     * @return list<array{label: string, value: string, caption: string, tone: string}>
     */
    private function summaryCards(Collection $customerModels, Collection $customers): array
    {
        $totalCustomers = $customerModels->count();
        $activeCustomers = $customerModels
            ->where('status', Customer::STATUS_ACTIVE)
            ->count();
        $totalSales = (float) $customers->sum('totalSalesValue');
        $outstanding = (float) $customers->sum('outstandingValue');
        $invoiceCount = (int) $customers->sum('invoiceCount');
        $averageTransaction = $invoiceCount > 0 ? $totalSales / $invoiceCount : 0;

        return [
            [
                'label' => 'Total pelanggan',
                'value' => number_format($totalCustomers, 0, ',', '.'),
                'caption' => 'Tersimpan di database',
                'tone' => 'brand',
            ],
            [
                'label' => 'Pelanggan aktif',
                'value' => number_format($activeCustomers, 0, ',', '.'),
                'caption' => "{$activeCustomers} dari {$totalCustomers} pelanggan",
                'tone' => 'success',
            ],
            [
                'label' => 'Piutang terbuka',
                'value' => $this->formatRupiah($outstanding),
                'caption' => 'Berdasarkan invoice tersimpan',
                'tone' => 'warning',
            ],
            [
                'label' => 'Rata-rata transaksi',
                'value' => $this->formatRupiah($averageTransaction),
                'caption' => 'Per invoice pelanggan',
                'tone' => 'brand',
            ],
        ];
    }

    private function statusLabel(Customer $customer): string
    {
        return match ($customer->status) {
            Customer::STATUS_ACTIVE => 'Aktif',
            Customer::STATUS_INACTIVE => 'Nonaktif',
            default => 'Perlu follow-up',
        };
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
