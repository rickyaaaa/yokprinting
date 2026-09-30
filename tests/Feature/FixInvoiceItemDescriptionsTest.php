<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixInvoiceItemDescriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_without_touching_anything_until_apply_is_passed(): void
    {
        $item = $this->itemFor('Tutup / Lid', 'Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)');

        $this->artisan('invoices:items:fix-descriptions')
            ->assertSuccessful();

        $this->assertSame(
            'Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)',
            $item->fresh()->description,
        );
    }

    public function test_it_relabels_a_lid_that_was_described_as_a_cup(): void
    {
        $item = $this->itemFor('Tutup / Lid', 'Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)');

        $this->artisan('invoices:items:fix-descriptions', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame(
            'Sablon Tutup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)',
            $item->fresh()->description,
        );
    }

    public function test_it_relabels_a_bowl_but_leaves_a_genuine_cup_alone(): void
    {
        $bowl = $this->itemFor('Paper Bowl', 'Sablon Cup 650 ml Datar (8gr)');
        $cup = $this->itemFor('Cup PP', 'Sablon Cup 12 Oz Datar (7gr) (Tinta hijau - 1 warna)');

        $this->artisan('invoices:items:fix-descriptions', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame('Sablon Bowl 650 ml Datar (8gr)', $bowl->fresh()->description);
        $this->assertSame(
            'Sablon Cup 12 Oz Datar (7gr) (Tinta hijau - 1 warna)',
            $cup->fresh()->description,
        );
    }

    public function test_it_skips_rows_whose_product_is_gone_instead_of_guessing(): void
    {
        $item = $this->itemFor('Tutup / Lid', 'Sablon Cup 12 Oz Datar (8gr)');
        $item->product->delete();

        $this->artisan('invoices:items:fix-descriptions', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame('Sablon Cup 12 Oz Datar (8gr)', $item->fresh()->description);
    }

    public function test_it_repairs_a_verified_cup_size_mismatch_only_with_apply(): void
    {
        $product = Product::query()->create([
            'sku' => 'H-014',
            'name' => 'Cup Injection 14Oz Datar (400Ml) Natural',
            'category' => 'Cup Injection',
            'cup_size' => '14 Oz',
            'cup_model' => 'Datar',
            'grammage' => '8gr',
        ]);
        $customer = Customer::query()->create(['code' => 'CUS-014', 'name' => 'Pelanggan 14 Oz']);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-014',
            'issue_date' => '2026-09-30',
            'due_date' => '2026-10-14',
            'subtotal' => 300000,
            'total_amount' => 300000,
        ]);
        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'cup_size' => '12 Oz',
            'cup_model' => 'Datar',
            'grammage' => '8gr',
            'screen_printing_color' => 'Hitam',
            'jenis_cetak' => '1 warna',
            'description' => 'Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)',
            'quantity' => 500,
            'unit_price' => 600,
            'subtotal' => 300000,
            'total_amount' => 300000,
        ]);

        $this->artisan('invoices:items:fix-descriptions')->assertSuccessful();
        $this->assertSame('Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)', $item->fresh()->description);

        $this->artisan('invoices:items:fix-descriptions', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Sablon Cup 14 Oz Datar (8gr) - 1 warna (Tinta Hitam)', $item->fresh()->description);
        $this->assertSame(300000.0, (float) $invoice->fresh()->total_amount);
    }

    public function test_equivalent_size_spacing_is_not_reported_as_a_mismatch(): void
    {
        $product = Product::query()->create([
            'sku' => 'H-012',
            'name' => 'Cup Injection 12Oz Datar Natural',
            'category' => 'Cup Injection',
            'cup_size' => '12 Oz',
        ]);
        $customer = Customer::query()->create(['code' => 'CUS-012', 'name' => 'Pelanggan 12 Oz']);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-012',
            'issue_date' => '2026-09-30',
            'due_date' => '2026-10-14',
            'subtotal' => 300000,
            'total_amount' => 300000,
        ]);
        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'description' => 'Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)',
            'quantity' => 500,
            'unit_price' => 600,
            'subtotal' => 300000,
            'total_amount' => 300000,
        ]);

        $this->artisan('invoices:items:fix-descriptions', ['--apply' => true])
            ->expectsOutputToContain('No invoice item descriptions need repairing')
            ->assertSuccessful();

        $this->assertSame('Sablon Cup 12 Oz Datar (8gr) (Tinta Hitam - 1 warna)', $item->fresh()->description);
    }

    private function itemFor(string $category, string $description): InvoiceItem
    {
        static $sequence = 0;
        $sequence++;

        $product = Product::query()->create([
            'sku' => "H-{$sequence}",
            'name' => "Produk {$sequence}",
            'category' => $category,
            'unit' => 'Pcs',
        ]);
        $customer = Customer::query()->create([
            'code' => "CUS-{$sequence}",
            'name' => "Pelanggan {$sequence}",
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => "INV/2026/{$sequence}",
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'subtotal' => 120000,
            'total_amount' => 120000,
        ]);

        return $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'description' => $description,
            'quantity' => 1000,
            'unit_price' => 120,
            'subtotal' => 120000,
            'total_amount' => 120000,
        ]);
    }
}
