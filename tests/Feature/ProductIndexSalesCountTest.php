<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * The product list's "Terjual" column and "Produk terlaris" card count line
 * items from real transactions only. Cancelling an invoice restores its FIFO
 * stock (CancelInvoice), so its line items must stop counting as sales too.
 * See Invoice::scopeBusinessTransaction().
 */
class ProductIndexSalesCountTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_sold_count_includes_active_drafts_and_excludes_cancelled(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Hitung Terjual']);
        $counted = $this->product('PRD-COUNTED', 'Produk Dihitung');
        $uncounted = $this->product('PRD-UNCOUNTED', 'Produk Tak Dihitung');

        // 1 sent + 1 draft on the counted product = 2 sales.
        $this->item($this->invoice($customer, 'INV-CNT-SENT', Invoice::STATUS_SENT), $counted);
        $this->item($this->invoice($customer, 'INV-CNT-DRAFT', Invoice::STATUS_DRAFT), $counted);

        // 3 cancelled line items on the other product - must count as zero,
        // otherwise it would wrongly win "Produk terlaris".
        $cancelled = $this->invoice($customer, 'INV-CNT-CANCELLED', Invoice::STATUS_CANCELLED);
        $this->item($cancelled, $uncounted);
        $this->item($cancelled, $uncounted);
        $this->item($cancelled, $uncounted);

        $response = $this->get(route('products.index'))->assertOk();

        $products = collect($response->viewData('products'));
        $this->assertSame(2, $products->firstWhere('sku', 'PRD-COUNTED')['sales']);
        $this->assertSame(0, $products->firstWhere('sku', 'PRD-UNCOUNTED')['sales']);

        $bestSeller = collect($response->viewData('summaryCards'))
            ->firstWhere('label', 'Produk terlaris');
        $this->assertSame('Produk Dihitung', $bestSeller['value']);
        $this->assertSame('2 transaksi', $bestSeller['caption']);
    }

    private function product(string $sku, string $name): Product
    {
        return Product::query()->create([
            'sku' => $sku,
            'name' => $name,
            'category' => 'Cup Injection',
            'price' => 1000,
            'status' => Product::STATUS_ACTIVE,
        ]);
    }

    private function invoice(Customer $customer, string $number, string $status): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => $status,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => 1000,
        ]);
    }

    private function item(Invoice $invoice, Product $product): void
    {
        $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'total_amount' => 1000,
        ]);
    }
}
