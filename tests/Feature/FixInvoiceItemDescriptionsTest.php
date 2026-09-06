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
