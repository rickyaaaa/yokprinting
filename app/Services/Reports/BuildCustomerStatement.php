<?php

namespace App\Services\Reports;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BuildCustomerStatement
{
    /**
     * Build a chronological customer receivable ledger.
     *
     * @return array{
     *     customer: array<string, mixed>,
     *     period: array<string, string|null>,
     *     summary: array<string, mixed>,
     *     transactions: list<array<string, mixed>>
     * }
     */
    public function build(Customer $customer, ?string $startDate = null, ?string $endDate = null): array
    {
        $periodStart = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
        $periodEnd = $endDate ? Carbon::parse($endDate)->endOfDay() : null;

        $allTransactions = $this->transactions($customer, $periodEnd);

        $openingBalance = $periodStart
            ? $this->balance(
                $allTransactions->filter(
                    fn (array $transaction): bool => $transaction['occurred_at']->lt($periodStart)
                )
            )
            : 0.0;

        $periodTransactions = $allTransactions
            ->filter(fn (array $transaction): bool => $this->isWithinPeriod(
                $transaction['occurred_at'],
                $periodStart,
                $periodEnd,
            ))
            ->values();

        $runningBalance = $openingBalance;
        $rows = $periodTransactions
            ->map(function (array $transaction) use (&$runningBalance): array {
                $runningBalance = round(
                    $runningBalance + (float) $transaction['debit'] - (float) $transaction['credit'],
                    2,
                );

                return [
                    'transaction_at' => $transaction['occurred_at']->toISOString(),
                    'transaction_date' => $transaction['occurred_at']->toDateString(),
                    'reference_number' => $transaction['reference_number'],
                    'type' => $transaction['type'],
                    'type_label' => $transaction['type_label'],
                    'description' => $transaction['description'],
                    'currency' => $transaction['currency'],
                    'debit' => round((float) $transaction['debit'], 2),
                    'credit' => round((float) $transaction['credit'], 2),
                    'running_balance' => $runningBalance,
                    'debit_formatted' => $this->formatRupiah((float) $transaction['debit']),
                    'credit_formatted' => $this->formatRupiah((float) $transaction['credit']),
                    'running_balance_formatted' => $this->formatRupiah($runningBalance),
                ];
            })
            ->values();

        $totalDebit = round((float) $rows->sum('debit'), 2);
        $totalCredit = round((float) $rows->sum('credit'), 2);
        $endingBalance = round($runningBalance, 2);

        return [
            'customer' => $this->customerProfile($customer),
            'period' => [
                'start_date' => $periodStart?->toDateString(),
                'end_date' => $periodEnd?->toDateString(),
            ],
            'summary' => [
                'opening_balance' => round($openingBalance, 2),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'outstanding_amount' => $endingBalance,
                'opening_balance_formatted' => $this->formatRupiah($openingBalance),
                'total_debit_formatted' => $this->formatRupiah($totalDebit),
                'total_credit_formatted' => $this->formatRupiah($totalCredit),
                'outstanding_amount_formatted' => $this->formatRupiah($endingBalance),
            ],
            'transactions' => $rows->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function transactions(Customer $customer, ?CarbonInterface $periodEnd): Collection
    {
        $invoices = $customer->invoices()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->when($periodEnd, fn ($query) => $query->where('created_at', '<=', $periodEnd))
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'occurred_at' => $this->invoiceOccurredAt($invoice),
                'sort_priority' => 10,
                'sort_id' => $invoice->getKey(),
                'reference_number' => $invoice->invoice_number,
                'type' => 'invoice',
                'type_label' => 'Faktur Penjualan',
                'description' => 'Tagihan invoice '.$invoice->invoice_number,
                'currency' => $invoice->currency,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0.0,
            ]);

        $payments = Payment::query()
            ->with('invoice')
            ->verified()
            ->whereHas('invoice', fn ($query) => $query
                ->where('customer_id', $customer->getKey())
                ->where('status', '!=', Invoice::STATUS_CANCELLED))
            ->when($periodEnd, fn ($query) => $query->whereDate('payment_date', '<=', $periodEnd->toDateString()))
            ->get()
            ->map(fn (Payment $payment): array => [
                'occurred_at' => $this->paymentOccurredAt($payment),
                'sort_priority' => 20,
                'sort_id' => $payment->getKey(),
                'reference_number' => $payment->payment_number,
                'type' => 'payment',
                'type_label' => 'Pembayaran',
                'description' => 'Pembayaran untuk invoice '.$payment->invoice?->invoice_number,
                'currency' => $payment->currency,
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ]);

        return $invoices
            ->concat($payments)
            ->sortBy([
                ['occurred_at', 'asc'],
                ['sort_priority', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $transactions
     */
    private function balance(Collection $transactions): float
    {
        return round(
            (float) $transactions->sum('debit') - (float) $transactions->sum('credit'),
            2,
        );
    }

    private function isWithinPeriod(CarbonInterface $occurredAt, ?CarbonInterface $periodStart, ?CarbonInterface $periodEnd): bool
    {
        if ($periodStart && $occurredAt->lt($periodStart)) {
            return false;
        }

        if ($periodEnd && $occurredAt->gt($periodEnd)) {
            return false;
        }

        return true;
    }

    private function invoiceOccurredAt(Invoice $invoice): CarbonInterface
    {
        return $invoice->created_at ?? Carbon::parse($invoice->issue_date)->startOfDay();
    }

    private function paymentOccurredAt(Payment $payment): CarbonInterface
    {
        $occurredAt = Carbon::parse($payment->payment_date)->startOfDay();

        if ($payment->created_at) {
            $occurredAt->setTimeFromTimeString($payment->created_at->format('H:i:s'));
        }

        return $occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    private function customerProfile(Customer $customer): array
    {
        return [
            'id' => $customer->getKey(),
            'code' => $customer->code,
            'name' => $customer->name,
            'activity_status' => $customer->activity_status,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'city' => $customer->city,
            'province' => $customer->province,
            'postal_code' => $customer->postal_code,
            'initials' => $customer->initials(),
        ];
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
