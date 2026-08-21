<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class RevisionExportApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_stock_export_contains_fifo_value_and_pdf_is_readable(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 12:00:00', 'Asia/Jakarta'));

        $product = Product::query()->create([
            'name' => 'Export Product',
            'sku' => 'EXPORT-1',
            'category' => 'Bahan',
            'track_stock' => true,
            'stock' => 10,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_PURCHASE,
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
        ]);
        InventoryBatch::query()->create([
            'product_id' => $product->id,
            'purchase_date' => '2026-08-01',
            'qty_received' => 10,
            'qty_remaining' => 10,
            'unit_cost' => 1200,
            'source_type' => 'test',
        ]);

        $query = ['start_date' => '2026-08-01', 'end_date' => '2026-08-20'];
        $csv = $this->get(route('api.reports.stock-mutations.export', $query))->assertOk()->getContent();
        $this->assertStringContainsString('FIFO Inventory Value', $csv);
        $this->assertStringContainsString('12000', $csv);
        $this->assertStringStartsWith('%PDF', $this->get(route('api.reports.stock-mutations.pdf', $query))->assertOk()->getContent());
    }

    public function test_invoice_export_respects_date_filter_for_csv_and_pdf(): void
    {
        $customer = Customer::query()->create(['name' => 'Export Customer']);
        $this->invoice($customer, 'INV-EXPORT-IN', '2026-08-10');
        $this->invoice($customer, 'INV-EXPORT-OUT', '2026-09-10');

        $query = ['date_from' => '2026-08-01', 'date_to' => '2026-08-31'];
        $csv = $this->get(route('api.invoices.export.csv', $query))->assertOk()->getContent();
        $this->assertStringContainsString('INV-EXPORT-IN', $csv);
        $this->assertStringNotContainsString('INV-EXPORT-OUT', $csv);
        $this->assertStringStartsWith('%PDF', $this->get(route('api.invoices.export.pdf', $query))->assertOk()->getContent());
    }

    public function test_daily_order_export_defaults_to_today_and_contains_invoice_details_and_verified_dp(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 12:00:00', 'Asia/Jakarta'));

        $customer = Customer::query()->create(['name' => 'Kopi Kampus']);
        $todayInvoice = $this->invoice($customer, 'INV-ORDER-TODAY', '2026-08-20');
        $todayInvoice->forceFill([
            'total_amount' => 6100000,
            'order_process_status' => Invoice::ORDER_PROCESS_IN_PRODUCTION,
        ])->save();
        $todayInvoice->items()->create([
            'product_name' => 'Cetak 1 Warna Putih',
            'description' => 'Cup 16 Oz, 1 warna',
            'quantity' => 10000,
            'unit' => 'Pcs',
            'unit_price' => 610,
            'subtotal' => 6100000,
            'total_amount' => 6100000,
        ]);
        $todayInvoice->payments()->create([
            'payment_number' => 'PAY-ORDER-TODAY',
            'payment_date' => '2026-08-20',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 3050000,
            'status' => Payment::STATUS_VERIFIED,
        ]);
        $this->invoice($customer, 'INV-ORDER-YESTERDAY', '2026-08-19');

        $csv = $this->get(route('api.invoices.export.orders.csv'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('INV-ORDER-TODAY', $csv);
        $this->assertStringContainsString('Cetak 1 Warna Putih', $csv);
        $this->assertStringContainsString('Masih produksi', $csv);
        $this->assertStringContainsString('3050000', $csv);
        $this->assertStringNotContainsString('INV-ORDER-YESTERDAY', $csv);
        $pdfResponse = $this->get(route('api.invoices.export.orders.pdf'))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="cetak-pesanan-detail-2026-08-20.pdf"');

        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());

        $filteredPdfResponse = $this->get(route('api.invoices.export.orders.pdf', [
            'date_from' => '2026-08-20',
            'date_to' => '2026-08-20',
        ]))->assertOk();

        $this->assertSame(
            'attachment; filename="cetak-pesanan-detail-2026-08-20.pdf"',
            $filteredPdfResponse->headers->get('Content-Disposition'),
        );
    }

    private function invoice(Customer $customer, string $number, string $date): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => $date,
            'due_date' => $date,
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'subtotal' => 1000,
            'total_amount' => 1000,
        ]);
    }
}
