<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class CustomerSalesProfitReportTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_customer_report_groups_sales_and_uses_historical_hpp(): void
    {
        $customerA = Customer::query()->create(['name' => '314 Coffee']);
        $customerB = Customer::query()->create(['name' => 'Kopi Lain']);
        $this->invoice($customerA, 'INV-A-001', 3650000, 2555000, '2026-08-13');
        $this->invoice($customerA, 'INV-A-002', 1000000, 400000, '2026-08-14');
        $this->invoice($customerB, 'INV-B-001', 500000, 250000, '2026-08-15');

        $this->getJson(route('api.reports.customer-sales.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-20',
            'customer_id' => $customerA->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.summary.customer_count', 1)
            ->assertJsonPath('data.summary.invoice_count', 2)
            ->assertJsonPath('data.summary.sales', 4650000)
            ->assertJsonPath('data.summary.fifo_hpp', 2955000)
            ->assertJsonPath('data.summary.gross_profit', 1695000)
            ->assertJsonPath('data.customers.0.customer', '314 Coffee')
            ->assertJsonPath('data.customers.0.margin_percent', 36.45);
    }

    public function test_active_draft_invoices_are_included_and_cancelled_are_excluded(): void
    {
        // See Invoice::scopeBusinessTransaction() - an invoice is a real
        // customer transaction the moment it exists, cancellation aside.
        $customer = Customer::query()->create(['name' => 'PT Draft Pelanggan']);
        $this->invoice($customer, 'INV-SENT', 1000000, 400000, '2026-08-10');
        $this->invoice($customer, 'INV-DRAFT', 500000, 200000, '2026-08-11', Invoice::STATUS_DRAFT);
        $this->invoice($customer, 'INV-CANCELLED', 9000000, 100000, '2026-08-12', Invoice::STATUS_CANCELLED);

        $this->getJson(route('api.reports.customer-sales.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'customer_id' => $customer->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 2)
            ->assertJsonPath('data.summary.sales', 1500000)
            ->assertJsonPath('data.summary.fifo_hpp', 600000)
            ->assertJsonPath('data.summary.gross_profit', 900000);
    }

    public function test_invoices_within_a_customer_default_to_newest_first_with_a_deterministic_tiebreak(): void
    {
        $customer = Customer::query()->create(['name' => '314 Coffee']);
        // Same issue_date for A and B - invoice_number must break the tie.
        $this->invoice($customer, 'INV-SORT-A', 100000, 40000, '2026-08-22');
        $this->invoice($customer, 'INV-SORT-B', 200000, 80000, '2026-08-22');
        $this->invoice($customer, 'INV-SORT-C', 300000, 120000, '2026-08-21');

        $this->getJson(route('api.reports.customer-sales.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'customer_id' => $customer->id,
        ]))
            ->assertOk()
            ->assertJsonPath('data.customers.0.invoices.0.invoice_number', 'INV-SORT-B')
            ->assertJsonPath('data.customers.0.invoices.1.invoice_number', 'INV-SORT-A')
            ->assertJsonPath('data.customers.0.invoices.2.invoice_number', 'INV-SORT-C');
    }

    public function test_customer_cards_are_ordered_by_total_sales_not_creation_order(): void
    {
        // Created smallest-first on purpose: customer_id order would put
        // "Kecil" at the top, which is how the cards used to come out.
        $kecil = Customer::query()->create(['name' => 'PT Kecil']);
        $besar = Customer::query()->create(['name' => 'PT Besar']);
        $sedang = Customer::query()->create(['name' => 'PT Sedang']);

        $this->invoice($kecil, 'INV-URUT-KECIL', 100000, 40000, '2026-08-10');
        $this->invoice($besar, 'INV-URUT-BESAR-1', 500000, 200000, '2026-08-11');
        $this->invoice($besar, 'INV-URUT-BESAR-2', 400000, 150000, '2026-08-12');
        $this->invoice($sedang, 'INV-URUT-SEDANG', 300000, 100000, '2026-08-13');

        $this->getJson(route('api.reports.customer-sales.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.customers.0.customer', 'PT Besar')
            ->assertJsonPath('data.customers.0.total_sales', 900000)
            ->assertJsonPath('data.customers.1.customer', 'PT Sedang')
            ->assertJsonPath('data.customers.2.customer', 'PT Kecil')
            // Newest invoice first inside a customer card.
            ->assertJsonPath('data.customers.0.invoices.0.invoice_number', 'INV-URUT-BESAR-2');
    }

    public function test_equal_totals_fall_back_to_customer_name_for_a_stable_order(): void
    {
        $zulu = Customer::query()->create(['name' => 'Zulu Printing']);
        $alfa = Customer::query()->create(['name' => 'Alfa Printing']);
        $this->invoice($zulu, 'INV-SAMA-Z', 250000, 100000, '2026-08-10');
        $this->invoice($alfa, 'INV-SAMA-A', 250000, 100000, '2026-08-10');

        $this->getJson(route('api.reports.customer-sales.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.customers.0.customer', 'Alfa Printing')
            ->assertJsonPath('data.customers.1.customer', 'Zulu Printing');
    }

    public function test_grand_total_equals_the_sum_of_the_customer_subtotals(): void
    {
        $a = Customer::query()->create(['name' => 'PT Jumlah A']);
        $b = Customer::query()->create(['name' => 'PT Jumlah B']);
        $this->invoice($a, 'INV-JML-A1', 350000, 270000, '2026-08-22');
        $this->invoice($a, 'INV-JML-A2', 305000, 275000, '2026-08-21');
        $this->invoice($b, 'INV-JML-B1', 700000, 540000, '2026-08-22');

        $data = $this->getJson(route('api.reports.customer-sales.index', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))->assertOk()->json('data');

        $subtotalSales = array_sum(array_column($data['customers'], 'total_sales'));
        $subtotalHpp = array_sum(array_column($data['customers'], 'total_hpp'));
        $subtotalProfit = array_sum(array_column($data['customers'], 'gross_profit'));

        $this->assertEqualsWithDelta($subtotalSales, $data['summary']['sales'], 0.001);
        $this->assertEqualsWithDelta($subtotalHpp, $data['summary']['fifo_hpp'], 0.001);
        $this->assertEqualsWithDelta($subtotalProfit, $data['summary']['gross_profit'], 0.001);
        // And gross profit really is sales - HPP, not an independent sum.
        $this->assertEqualsWithDelta(
            round($data['summary']['sales'] - $data['summary']['fifo_hpp'], 2),
            $data['summary']['gross_profit'],
            0.001,
        );
    }

    public function test_report_can_be_searched_by_invoice_number_or_customer_name(): void
    {
        $bahagia = Customer::query()->create(['name' => 'pt bahagia']);
        $lain = Customer::query()->create(['name' => 'CV Lainnya']);
        $this->invoice($bahagia, 'INV-2026-0042', 1500000, 900000, '2026-08-27');
        $this->invoice($bahagia, 'INV-2026-0016', 350000, 270000, '2026-08-22');
        $this->invoice($lain, 'INV-2026-0099', 700000, 500000, '2026-08-22');

        $period = ['date_from' => '2026-08-01', 'date_to' => '2026-08-31'];

        // Exact invoice number - finds it without knowing the customer.
        $this->getJson(route('api.reports.customer-sales.index', $period + ['q' => 'INV-2026-0042']))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 1)
            ->assertJsonPath('data.customers.0.invoices.0.invoice_number', 'INV-2026-0042');

        // Partial number matches several.
        $this->getJson(route('api.reports.customer-sales.index', $period + ['q' => '2026-00']))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 3);

        // Customer name still works in the same box.
        $this->getJson(route('api.reports.customer-sales.index', $period + ['q' => 'bahagia']))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 2)
            ->assertJsonPath('data.summary.customer_count', 1);

        // No match is an empty report, not an error.
        $this->getJson(route('api.reports.customer-sales.index', $period + ['q' => 'INV-TIDAK-ADA']))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 0)
            ->assertJsonPath('data.customers', []);
    }

    public function test_search_keyword_also_narrows_the_export(): void
    {
        $customer = Customer::query()->create(['name' => 'pt bahagia']);
        $this->invoice($customer, 'INV-CARI-IN', 1000, 400, '2026-08-13');
        $this->invoice($customer, 'INV-LAIN-OUT', 2000, 500, '2026-08-14');

        $csv = $this->get(route('api.reports.customer-sales.export', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'q' => 'CARI',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('INV-CARI-IN', $csv);
        $this->assertStringNotContainsString('INV-LAIN-OUT', $csv);
    }

    public function test_customer_sales_report_page_loads_for_report_viewers(): void
    {
        $this->get(route('reports.customer-sales.index'))
            ->assertOk()
            ->assertSee('Penjualan per Pelanggan')
            ->assertSee('customerSalesReportPage');
    }

    public function test_customer_report_export_respects_period_and_customer_filter(): void
    {
        $customer = Customer::query()->create(['name' => '314 Coffee']);
        $this->invoice($customer, 'INV-IN', 1000, 400, '2026-08-13');
        $this->invoice($customer, 'INV-OUT', 2000, 500, '2026-09-13');

        $response = $this->get(route('api.reports.customer-sales.export', [
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-20',
            'customer_id' => $customer->id,
        ]));

        $response->assertOk();
        $csv = $response->getContent();
        $this->assertStringContainsString('INV-IN', $csv);
        $this->assertStringNotContainsString('INV-OUT', $csv);
    }

    private function invoice(
        Customer $customer,
        string $number,
        float $sales,
        float $hpp,
        string $date,
        string $status = Invoice::STATUS_SENT,
    ): Invoice {
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => $date,
            'due_date' => $date,
            'status' => $status,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'subtotal' => $sales,
            'total_amount' => $sales,
            'total_hpp' => $hpp,
            'gross_profit' => $sales - $hpp,
        ]);
        $invoice->items()->create([
            'product_name' => 'Produk report',
            'sku' => 'REPORT-1',
            'quantity' => 1,
            'unit_price' => $sales,
            'purchase_cost_snapshot' => $hpp,
            'hpp_total' => $hpp,
            'unit_hpp' => $hpp,
            'subtotal' => $sales,
            'total_amount' => $sales,
        ]);

        return $invoice;
    }
}
