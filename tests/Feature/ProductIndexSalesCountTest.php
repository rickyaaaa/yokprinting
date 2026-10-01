<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * The product list's "Terjual" column and "Produk terlaris" card sum the
 * quantity from invoices after the first verified DP only. Cancelling an
 * invoice restores its FIFO stock (CancelInvoice), so its line items must
 * stop counting as sales.
 */
class ProductIndexSalesCountTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_sold_quantity_requires_verified_dp_and_excludes_cancelled(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Hitung Terjual']);
        $counted = $this->product('PRD-COUNTED', 'Produk Dihitung');
        $uncounted = $this->product('PRD-UNCOUNTED', 'Produk Tak Dihitung');

        // An unpaid sent invoice and an unpaid draft must not count as sold.
        $this->item($this->invoice($customer, 'INV-CNT-SENT', Invoice::STATUS_SENT), $counted, 1000);
        $this->item($this->invoice($customer, 'INV-CNT-DRAFT', Invoice::STATUS_DRAFT), $counted, 500);
        $paid = $this->invoice($customer, 'INV-CNT-PAID', Invoice::STATUS_DRAFT);
        $this->item($paid, $counted, 700);
        $this->verifiedPayment($paid, 100);

        // 3 cancelled line items on the other product - must count as zero,
        // otherwise it would wrongly win "Produk terlaris".
        $cancelled = $this->invoice($customer, 'INV-CNT-CANCELLED', Invoice::STATUS_CANCELLED);
        $this->item($cancelled, $uncounted);
        $this->item($cancelled, $uncounted);
        $this->item($cancelled, $uncounted);

        $response = $this->get(route('products.index'))->assertOk();

        $products = collect($response->viewData('products'));
        $this->assertSame(700, $products->firstWhere('sku', 'PRD-COUNTED')['sales']);
        $this->assertSame(0, $products->firstWhere('sku', 'PRD-UNCOUNTED')['sales']);

        $bestSeller = collect($response->viewData('summaryCards'))
            ->firstWhere('label', 'Produk terlaris');
        $this->assertSame('Produk Dihitung', $bestSeller['value']);
        $this->assertSame('700 Pcs terjual', $bestSeller['caption']);
    }

    public function test_product_catalog_api_keeps_the_sales_count_used_by_the_table(): void
    {
        $customer = Customer::query()->create(['name' => 'PT API Terjual']);
        $product = $this->product('PRD-API-SALES', 'Produk API Terjual');

        $invoice = $this->invoice($customer, 'INV-API-SALES', Invoice::STATUS_DRAFT);
        $this->item($invoice, $product, 1000);

        $this->getJson(route('api.products.index', ['status' => 'all']))
            ->assertOk()
            ->assertJsonPath('data.0.sales', 0);

        $this->verifiedPayment($invoice, 100);

        $this->getJson(route('api.products.index', ['status' => 'all']))
            ->assertOk()
            ->assertJsonPath('data.0.sales', 1000);
    }

    public function test_paid_and_shipped_invoices_are_counted_and_cancelled_is_not(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Status Terjual']);
        $product = $this->product('PRD-STATUS-SALES', 'Produk Status Terjual');

        $paid = $this->invoice($customer, 'INV-STATUS-PAID', Invoice::STATUS_DRAFT);
        $this->item($paid, $product, 1000);
        $this->verifiedPayment($paid, 100);
        $paid->forceFill(['order_process_status' => Invoice::ORDER_PROCESS_COMPLETED])->save();

        $shipped = $this->invoice($customer, 'INV-STATUS-SHIPPED', Invoice::STATUS_SENT);
        $this->item($shipped, $product, 500);
        $this->verifiedPayment($shipped, 100);
        $shipped->forceFill(['order_process_status' => Invoice::ORDER_PROCESS_COMPLETED])->save();

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

    private function verifiedPayment(Invoice $invoice, float $amount): void
    {
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-'.str_replace('-', '', $invoice->invoice_number),
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }
}
