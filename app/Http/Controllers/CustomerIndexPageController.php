<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
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
            ->with([
                'invoices' => fn ($query) => $query
                    ->where('status', '!=', Invoice::STATUS_CANCELLED)
                    ->with([
                        'payments' => fn ($paymentQuery) => $paymentQuery
                            ->where('status', Payment::STATUS_VERIFIED),
                    ]),
            ])
            ->orderBy('name')
            ->get();

        $customers = $customerModels->map(function (Customer $customer): array {
            $invoices = $customer->invoices;
            $totalSales = (float) $invoices->sum('total_amount');
            $outstanding = (float) $invoices
                ->where('status', Invoice::STATUS_SENT)
                ->where('payment_status', '!=', Invoice::PAYMENT_PAID)
                ->sum(
                fn (Invoice $invoice): float => max(
                    0,
                    (float) $invoice->total_amount - (float) $invoice->payments->sum('amount'),
                ),
            );
            $lastOrder = $invoices
                ->sortByDesc(fn (Invoice $invoice): string => $invoice->issue_date?->toDateString() ?? '')
                ->first()?->issue_date;

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
                'invoiceCount' => $invoices->count(),
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
        $invoiceCount = $customerModels->sum(fn (Customer $customer): int => $customer->invoices->count());
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
