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

    private function invoice(Customer $customer, string $number, float $sales, float $hpp, string $date): Invoice
    {
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => $date,
            'due_date' => $date,
            'status' => Invoice::STATUS_SENT,
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
