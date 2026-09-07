<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoices saved while the preview still labelled a line by its description
 * stored that description in product_name, so the stored invoice PDF prints
 * "Sablon Cup 12 Oz Datar ..." where the product belongs. The real name is
 * recoverable from the product the row still points at.
 */
class FixInvoiceItemProductNamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_without_writing_until_apply_is_passed(): void
    {
        $item = $this->item('Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)', 'Cup PET 12Oz Datar SJP');

        $this->artisan('invoices:items:fix-product-names')->assertSuccessful();

        $this->assertSame('Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)', $item->fresh()->product_name);
    }

    public function test_it_recovers_the_product_name_from_the_linked_product(): void
    {
        $item = $this->item('Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)', 'Cup PET 12Oz Datar SJP');

        $this->artisan('invoices:items:fix-product-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Cup PET 12Oz Datar SJP', $item->fresh()->product_name);
    }

    public function test_it_leaves_a_correctly_named_item_alone(): void
    {
        $item = $this->item('Tutup Strawless SJP D93', 'Tutup Strawless SJP D93');

        $this->artisan('invoices:items:fix-product-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Tutup Strawless SJP D93', $item->fresh()->product_name);
    }

    public function test_it_skips_a_row_whose_product_is_gone_instead_of_guessing(): void
    {
        $item = $this->item('Sablon Cup 12 Oz Datar (8gr)', 'Cup PET 12Oz Datar SJP');
        $item->product->delete();

        $this->artisan('invoices:items:fix-product-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Sablon Cup 12 Oz Datar (8gr)', $item->fresh()->product_name);
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $item = $this->item('Sablon Cup 12 Oz Datar (8gr)', 'Cup PET 12Oz Datar SJP');

        $this->artisan('invoices:items:fix-product-names', ['--apply' => true])->assertSuccessful();
        $this->artisan('invoices:items:fix-product-names', ['--apply' => true])
            ->expectsOutputToContain('No invoice item product names need repairing')
            ->assertSuccessful();

        $this->assertSame('Cup PET 12Oz Datar SJP', $item->fresh()->product_name);
    }

    private function item(string $storedName, string $productName): InvoiceItem
    {
        static $sequence = 0;
        $sequence++;

        $product = Product::query()->create([
            'sku' => "PN-{$sequence}",
            'name' => $productName,
            'unit' => 'Pcs',
        ]);
        $customer = Customer::query()->create(['code' => "CUS-PN-{$sequence}", 'name' => "Pelanggan {$sequence}"]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => "INV-PN-{$sequence}",
            'issue_date' => '2026-09-07',
            'due_date' => '2026-09-21',
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);

        return $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $storedName,
            'sku' => $product->sku,
            'quantity' => 500,
            'unit_price' => 100,
            'subtotal' => 50000,
            'total_amount' => 50000,
        ]);
    }
}
