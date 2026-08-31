<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\ProfitLossPeriod;
use Carbon\CarbonImmutable;

class ProfitLossReport
{
    /**
     * Build the single accounting dataset consumed by the screen, PDF, and XLSX exports.
     *
     * Every active (non-cancelled) invoice is recognized - draft included -
     * matching Invoice::scopeBusinessTransaction() and the stock that was
     * already deducted when the invoice was created. Tax collected and
     * customer-billed shipping reconcile the invoice total but are not sales
     * revenue.
     *
     * @return array{
     *     period: array{key: string, date_from: string, date_to: string, label: string},
     *     accounting_policy: array<string, mixed>,
     *     summary: array<string, int|float>
     * }
     */
    public function build(string $period, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $range = ProfitLossPeriod::resolve($period, $dateFrom, $dateTo);
        $dateToExclusive = CarbonImmutable::parse(
            $range['date_to'],
            (string) config('app.timezone', 'UTC'),
        )->addDay()->toDateString();

        $finalInvoices = Invoice::query()
            ->businessTransaction()
            ->where('issue_date', '>=', $range['date_from'])
            ->where('issue_date', '<', $dateToExclusive);

        $invoiceSummary = (clone $finalInvoices)
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as sales_discount')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_collected')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_invoice')
            ->selectRaw('COALESCE(SUM(total_hpp), 0) as total_hpp')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN shipping_type = ? THEN shipping_cost ELSE 0 END), 0) as customer_shipping_charged',
                [Invoice::SHIPPING_PAID_BY_CUSTOMER],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN shipping_type = ? THEN shipping_cost ELSE 0 END), 0) as shipping_expenses',
                [Invoice::SHIPPING_COMPANY_FREE_SHIPPING],
            )
            ->firstOrFail();

        $salesQuantity = InvoiceItem::query()
            ->whereHas('invoice', fn ($query) => $query
                ->businessTransaction()
                ->where('issue_date', '>=', $range['date_from'])
                ->where('issue_date', '<', $dateToExclusive))
            ->sum('quantity');

        $expenseRows = Expense::query()
            ->where('expense_date', '>=', $range['date_from'])
            ->where('expense_date', '<', $dateToExclusive)
            ->whereIn('category', Expense::categories())
            ->selectRaw('category, COUNT(*) as transaction_count, COALESCE(SUM(amount), 0) as total')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $grossSales = $this->money($invoiceSummary->gross_sales);
        $salesDiscount = $this->money($invoiceSummary->sales_discount);
        $salesRevenue = $this->money($grossSales - $salesDiscount);
        $taxCollected = $this->money($invoiceSummary->tax_collected);
        $customerShippingCharged = $this->money($invoiceSummary->customer_shipping_charged);
        $totalInvoice = $this->money($invoiceSummary->total_invoice);
        $expectedInvoiceTotal = $this->money($salesRevenue + $taxCollected + $customerShippingCharged);
        $totalHpp = $this->money($invoiceSummary->total_hpp);
        $shippingExpenses = $this->money($invoiceSummary->shipping_expenses);
        $productionExpenses = $this->expenseTotal($expenseRows, Expense::CATEGORY_PRODUCTION);
        $shoppingExpenses = $this->expenseTotal($expenseRows, Expense::CATEGORY_SHOPPING);
        $employeeExpenses = $this->expenseTotal($expenseRows, Expense::CATEGORY_EMPLOYEE);
        $premisesExpenses = $this->expenseTotal($expenseRows, Expense::CATEGORY_PREMISES);
        // Expedition/shipping cost paid to a courier is a distribution
        // expense incurred after goods are already FIFO-costed - never part
        // of HPP - so unlike production/shopping it's unambiguously
        // "recognized", the same bucket as shipping_expenses/employee/premises.
        $expeditionExpenses = $this->expenseTotal($expenseRows, Expense::CATEGORY_EXPEDITION);

        // Production and shopping expenses cannot yet be reconciled to inventory/HPP.
        // Report both defensible boundaries instead of choosing one unsupported result.
        $unclassifiedExpenses = $this->money($productionExpenses + $shoppingExpenses);
        $recognizedExpenses = $this->money($shippingExpenses + $expeditionExpenses + $employeeExpenses + $premisesExpenses);
        $recordedExpenses = $this->money($recognizedExpenses + $unclassifiedExpenses);
        $grossProfit = $this->money($salesRevenue - $totalHpp);
        $netProfitMaximum = $this->money($grossProfit - $recognizedExpenses);
        $netProfitMinimum = $this->money($netProfitMaximum - $unclassifiedExpenses);

        return [
            'period' => $range,
            'accounting_policy' => [
                'final_invoice_statuses' => [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT],
                'tax_is_revenue' => false,
                'customer_shipping_is_revenue' => false,
                'profit_is_provisional' => $unclassifiedExpenses > 0,
                'unclassified_expense_categories' => [
                    Expense::CATEGORY_PRODUCTION,
                    Expense::CATEGORY_SHOPPING,
                ],
                'minimum_profit_basis' => 'Minimum: Biaya Produksi dan Belanjaan dikurangkan.',
                'maximum_profit_basis' => 'Maksimum: Biaya Produksi dan Belanjaan tidak dikurangkan karena mungkin sudah termasuk HPP.',
                'decision_required' => 'Owner perlu menentukan apakah Biaya Produksi dan Belanjaan merupakan biaya tambahan di luar HPP atau sudah tercermin dalam HPP.',
            ],
            'summary' => [
                'gross_sales' => $grossSales,
                'sales_discount' => $salesDiscount,
                'sales_revenue' => $salesRevenue,
                'tax_collected' => $taxCollected,
                'customer_shipping_charged' => $customerShippingCharged,
                'expected_invoice_total' => $expectedInvoiceTotal,
                'total_invoice' => $totalInvoice,
                'invoice_reconciliation_difference' => $this->money($totalInvoice - $expectedInvoiceTotal),
                'total_hpp' => $totalHpp,
                'gross_profit' => $grossProfit,
                'shipping_expenses' => $shippingExpenses,
                'expedition_expenses' => $expeditionExpenses,
                'production_expenses' => $productionExpenses,
                'employee_expenses' => $employeeExpenses,
                'premises_expenses' => $premisesExpenses,
                'shopping_expenses' => $shoppingExpenses,
                'unclassified_expenses' => $unclassifiedExpenses,
                'recognized_expenses' => $recognizedExpenses,
                'recorded_expenses' => $recordedExpenses,
                'net_profit_minimum' => $netProfitMinimum,
                'net_profit_maximum' => $netProfitMaximum,
                'profit_range' => $this->money($netProfitMaximum - $netProfitMinimum),
                'minimum_profit_reconciliation_difference' => $this->money(
                    $netProfitMinimum - ($salesRevenue - $totalHpp - $recordedExpenses),
                ),
                'maximum_profit_reconciliation_difference' => $this->money(
                    $netProfitMaximum - ($salesRevenue - $totalHpp - $recognizedExpenses),
                ),
                'sales_quantity' => round((float) $salesQuantity, 4),
                'invoice_count' => (int) $invoiceSummary->invoice_count,
                'expense_count' => (int) $expenseRows->sum('transaction_count'),
            ],
        ];
    }

    private function expenseTotal($rows, string $category): float
    {
        return $this->money($rows->get($category)?->total ?? 0);
    }

    private function money(int|float|string|null $amount): float
    {
        return round((float) $amount, 2, PHP_ROUND_HALF_UP);
    }
}
