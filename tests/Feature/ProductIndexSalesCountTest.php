<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * The product list's "Terjual" column and "Produk terlaris" card sum the
 * quantity from real transactions only. Cancelling an invoice restores its
 * FIFO stock (CancelInvoice), so its line items must stop counting as sales.
 * See Invoice::scopeBusinessTransaction().
 */
class ProductIndexSalesCountTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_sold_quantity_includes_active_drafts_and_excludes_cancelled(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Hitung Terjual']);
        $counted = $this->product('PRD-COUNTED', 'Produk Dihitung');
        $uncounted = $this->product('PRD-UNCOUNTED', 'Produk Tak Dihitung');

        // 1,000 sent + 500 draft on the counted product = 1,500 pcs.
        $this->item($this->invoice($customer, 'INV-CNT-SENT', Invoice::STATUS_SENT), $counted, 1000);
        $this->item($this->invoice($customer, 'INV-CNT-DRAFT', Invoice::STATUS_DRAFT), $counted, 500);

        // 3 cancelled line items on the other product - must count as zero,
        // otherwise it would wrongly win "Produk terlaris".
        $cancelled = $this->invoice($customer, 'INV-CNT-CANCELLED', Invoice::STATUS_CANCELLED);
        $this->item($cancelled, $uncounted);
        $this->item($cancelled, $uncounted);
        $this->item($cancelled, $uncounted);

        $response = $this->get(route('products.index'))->assertOk();

        $products = collect($response->viewData('products'));
        $this->assertSame(1500, $products->firstWhere('sku', 'PRD-COUNTED')['sales']);
        $this->assertSame(0, $products->firstWhere('sku', 'PRD-UNCOUNTED')['sales']);

        $bestSeller = collect($response->viewData('summaryCards'))
            ->firstWhere('label', 'Produk terlaris');
        $this->assertSame('Produk Dihitung', $bestSeller['value']);
        $this->assertSame('1.500 Pcs terjual', $bestSeller['caption']);
    }

    public function test_product_catalog_api_keeps_the_sales_count_used_by_the_table(): void
    {
        $customer = Customer::query()->create(['name' => 'PT API Terjual']);
        $product = $this->product('PRD-API-SALES', 'Produk API Terjual');

        $this->item($this->invoice($customer, 'INV-API-SALES', Invoice::STATUS_DRAFT), $product, 1000);

        $this->getJson(route('api.products.index', ['status' => 'all']))
            ->assertOk()
            ->assertJsonPath('data.0.sales', 1000);
    }

    public function test_paid_and_shipped_invoices_are_counted_and_cancelled_is_not(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Status Terjual']);
        $product = $this->product('PRD-STATUS-SALES', 'Produk Status Terjual');

        $paid = $this->invoice($customer, 'INV-STATUS-PAID', Invoice::STATUS_DRAFT);
        $paid->forceFill([
            'payment_status' => Invoice::PAYMENT_PAID,
            'paid_at' => now(),
            'order_process_status' => Invoice::ORDER_PROCESS_COMPLETED,
        ])->save();
        $this->item($paid, $product, 1000);

        $shipped = $this->invoice($customer, 'INV-STATUS-SHIPPED', Invoice::STATUS_SENT);
        $shipped->forceFill([
            'payment_status' => Invoice::PAYMENT_PAID,
            'paid_at' => now(),
            'order_process_status' => Invoice::ORDER_PROCESS_COMPLETED,
        ])->save();
        $this->item($shipped, $product, 500);

        $cancelled = $this->invoice($customer, 'INV-STATUS-CANCELLED', Invoice::STATUS_CANCELLED);
        $cancelled->forceFill(['payment_status' => Invoice::PAYMENT_PAID])->save();
        $this->item($cancelled, $product, 250);

        $this->getJson(route('api.products.index', [
            'status' => 'all',
            'ids' => [$product->id],
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.sales', 1500);

        // Detail/update consumers must receive the same metric as the list
        // endpoint; otherwise a later hydration can silently replace it with
        // zero.
        $this->getJson(route('api.products.show', $product))
            ->assertOk()
            ->assertJsonPath('data.sales', 1500);
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

    private function item(Invoice $invoice, Product $product, int $quantity = 1): void
    {
        $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => 1000,
            'subtotal' => $quantity * 1000,
            'total_amount' => $quantity * 1000,
        ]);
    }
}
