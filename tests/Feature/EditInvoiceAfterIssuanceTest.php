<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemCostLayer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Client requirement: an invoice must stay editable through its whole
 * lifecycle (sent, awaiting DP, design ACC, in production, ready for
 * pickup) - only `cancelled`, or an edit that would make the invoice owe
 * less than what's already verified as paid, may block it. See
 * Invoice::isEditable() and UpdateInvoiceDraft.
 */
class EditInvoiceAfterIssuanceTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_sent_invoice_can_be_edited_and_status_sent_at_stay_unchanged(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Sent Edit']);
        $product = $this->product('SENT-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $sentAt = now()->subDay();
        $invoice->forceFill([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => $sentAt,
            'production_status' => Invoice::PRODUCTION_AWAITING_DP,
        ])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 10, price: 150000),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.subtotal', '1500000.00');

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_SENT, $invoice->status, 'editing must never revert status to draft');
        $this->assertSame(Invoice::PRODUCTION_AWAITING_DP, $invoice->production_status, 'production status must not reset');
        $this->assertSame($sentAt->timestamp, $invoice->sent_at->timestamp, 'sent_at must not be cleared/changed by an edit');
    }

    public function test_in_production_invoice_is_editable(): void
    {
        $customer = Customer::query()->create(['name' => 'PT In Production Edit']);
        $product = $this->product('INPROD-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $invoice->forceFill([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
        ])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 12, price: 100000),
        )->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::PRODUCTION_IN_PRODUCTION, $invoice->production_status);
        $this->assertSame('1200000.00', $invoice->subtotal);
    }

    public function test_ready_for_pickup_invoice_is_editable(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Ready Pickup Edit']);
        $product = $this->product('READY-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $invoice->forceFill([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'production_status' => Invoice::PRODUCTION_READY_FOR_PICKUP,
        ])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 10, price: 110000),
        )->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::PRODUCTION_READY_FOR_PICKUP, $invoice->production_status);
    }

    public function test_completed_production_invoice_is_editable_when_financially_safe(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Completed Edit']);
        $product = $this->product('DONE-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $invoice->forceFill([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'production_status' => Invoice::PRODUCTION_COMPLETED,
            'payment_status' => Invoice::PAYMENT_PAID,
        ])->save();

        // Raising the total keeps it financially valid (0 verified paid here).
        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 10, price: 120000),
        )->assertOk();

        $this->assertSame(Invoice::PRODUCTION_COMPLETED, $invoice->refresh()->production_status);
    }

    public function test_the_1000_at_540_plus_1000_at_550_scenario_survives_editing_invoice_one_while_in_production(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Klien FIFO Edit']);
        $product = Product::query()->create([
            'name' => 'Cup Injection 14Oz Datar Black Frosted',
            'sku' => 'CUP-14OZ-EDIT',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        $this->receiveGoods($product, quantity: 1000, unitCost: 540);
        $this->receiveGoods($product, quantity: 1000, unitCost: 550);

        $invoice1 = $this->createInvoiceDraft($customer, $product, quantity: 1000, price: 1000);
        $invoice2 = $this->createInvoiceDraft($customer, $product, quantity: 1000, price: 1000);

        $this->assertSame(540000.0, (float) $invoice1->refresh()->total_hpp);
        $this->assertSame(550000.0, (float) $invoice2->refresh()->total_hpp);
        $this->assertSame('0.0000', $product->refresh()->stock);

        $invoice1->forceFill([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
        ])->save();

        // Edit invoice #1 down from 1000 to 800 pcs while sent + in_production.
        $this->patchJson(
            route('api.invoices.update', $invoice1),
            $this->payload($customer, $product, quantity: 800, price: 1000),
        )->assertOk();

        $item1 = $invoice1->refresh()->items()->firstOrFail();
        // Editing an issued invoice soft-deletes the superseded item (see
        // InvoiceItem::SoftDeletes) instead of hard-deleting it, precisely so
        // its now-reversed cost layer survives for audit - find it explicitly.
        $oldItem1 = InvoiceItem::withTrashed()
            ->where('invoice_id', $invoice1->id)
            ->where('id', '!=', $item1->id)
            ->firstOrFail();

        // Old 1000 restored to batch #1 (540), then 800 re-consumed from it -
        // batch #1 must end with 200 remaining, never touching batch #2.
        $this->assertSame(432000.0, (float) $item1->hpp_total, '800 x 540 = 432.000');
        $this->assertSame(432000.0, (float) $invoice1->refresh()->total_hpp);
        $this->assertSame(1, $item1->costLayers()->whereNull('reversed_at')->count(), 'no duplicate active cost layer');
        $this->assertSame(
            1,
            InvoiceItemCostLayer::query()->where('invoice_item_id', $oldItem1->id)->whereNotNull('reversed_at')->count(),
            'the old layer must be reversed, not deleted',
        );
        $this->assertDatabaseHas('invoice_item_cost_layers', [
            'invoice_item_id' => $item1->id,
            'qty_consumed' => 800,
            'unit_cost' => 540,
            'total_cost' => 432000,
            'reversed_at' => null,
        ]);

        // Invoice #2's own HPP/batch must be completely untouched by editing invoice #1.
        $this->assertSame(550000.0, (float) $invoice2->refresh()->total_hpp);

        $batch1 = InventoryBatch::query()->where('unit_cost', 540)->firstOrFail();
        $batch2 = InventoryBatch::query()->where('unit_cost', 550)->firstOrFail();
        $this->assertSame('200.0000', $batch1->qty_remaining);
        $this->assertSame('0.0000', $batch2->qty_remaining);

        // Stock reconciles exactly: 200 (batch #1) + 0 (batch #2) = 200.
        $this->assertSame('200.0000', $product->refresh()->stock);
        $this->assertSame(
            round((float) InventoryBatch::query()->where('product_id', $product->id)->sum('qty_remaining'), 4),
            round((float) $product->stock, 4),
        );

        $invoice1->refresh();
        $this->assertSame(Invoice::STATUS_SENT, $invoice1->status);
        $this->assertSame(Invoice::PRODUCTION_IN_PRODUCTION, $invoice1->production_status);
    }

    public function test_edit_quantity_from_1000_to_500_restores_then_reconsumes_correctly(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Edit Qty']);
        $product = Product::query()->create([
            'name' => 'Produk Edit Qty',
            'sku' => 'EDIT-QTY-01',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $this->receiveGoods($product, quantity: 1000, unitCost: 540);

        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1000, price: 1000);
        $this->assertSame(540000.0, (float) $invoice->refresh()->total_hpp);
        $this->assertSame('0.0000', $product->refresh()->stock);

        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 500, price: 1000),
        )->assertOk();

        $this->assertSame(270000.0, (float) $invoice->refresh()->total_hpp, '500 x 540 = 270.000');
        $this->assertSame('500.0000', $product->refresh()->stock, 'stock must grow by 500 versus the pre-edit state');

        $batch = InventoryBatch::query()->where('unit_cost', 540)->firstOrFail();
        $this->assertSame('500.0000', $batch->qty_remaining);
    }

    public function test_edit_swapping_product_restores_old_product_stock_and_consumes_the_new_one(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Edit Product Swap']);
        $productA = $this->product('SWAP-A', trackStock: true, stock: 1000);
        $productB = $this->product('SWAP-B', trackStock: true, stock: 1000);

        $invoice = $this->createInvoiceDraft($customer, $productA, quantity: 100, price: 1000);
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();
        $this->assertSame('900.0000', $productA->refresh()->stock);

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $productB, quantity: 50, price: 1000),
        )->assertOk();

        $this->assertSame('1000.0000', $productA->refresh()->stock, 'old product stock must be restored');
        $this->assertSame('950.0000', $productB->refresh()->stock, 'new product stock must be deducted');
        // The superseded product-A item is soft-deleted (kept for audit),
        // not gone - only one item is ACTIVE on the invoice going forward.
        $this->assertSame(1, $invoice->items()->count());
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'product_id' => $productB->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'product_id' => $productA->id]);
    }

    public function test_invoice_with_verified_payment_can_be_edited_when_new_total_covers_the_paid_amount(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Sudah Bayar']);
        $product = $this->product('PAID-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();
        $this->verifiedPayment($invoice, 500000);

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 12, price: 100000), // new total 1.200.000
        )
            ->assertOk()
            ->assertJsonPath('data.subtotal', '1200000.00');

        $invoice->refresh();
        $this->assertSame(500000.0, $invoice->verifiedPaidAmount());
        $this->assertSame(700000.0, $invoice->remainingAmount());
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->payment_status);
    }

    public function test_invoice_is_rejected_when_new_total_would_be_less_than_verified_payment(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Tolak Edit']);
        $product = $this->product('REJECT-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000); // total 1.000.000
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();
        $this->verifiedPayment($invoice, 500000);

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 4, price: 100000), // new total 400.000 < paid 500.000
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('total_amount')
            ->assertJsonPath(
                'errors.total_amount.0',
                'Total invoice baru tidak boleh lebih kecil dari pembayaran yang sudah diterima.',
            );

        $invoice->refresh();
        $this->assertSame('1000000.00', $invoice->subtotal, 'a rejected edit must not partially apply');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_status_becomes_paid_when_new_total_equals_verified_payment(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Lunas Edit']);
        $product = $this->product('LUNAS-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000); // 1.000.000
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();
        $this->verifiedPayment($invoice, 1000000);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->refresh()->payment_status);

        // Editing without changing the total must keep it paid, not regress to unpaid.
        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 10, price: 100000),
        )->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_gross_profit_is_recalculated_after_an_edit(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Gross Profit Edit']);
        $product = Product::query()->create([
            'name' => 'Produk Gross Profit',
            'sku' => 'GP-EDIT-01',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $this->receiveGoods($product, quantity: 1000, unitCost: 540);

        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1000, price: 1000);
        $this->assertSame(460000.0, (float) $invoice->refresh()->gross_profit, '1.000.000 - 540.000');
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 500, price: 1000),
        )->assertOk();

        // 500.000 revenue - 270.000 HPP (500 x 540) = 230.000
        $this->assertSame(230000.0, (float) $invoice->refresh()->gross_profit);
    }

    public function test_edited_sent_invoice_still_appears_in_the_finalized_report_scope(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Report Scope']);
        $product = $this->product('REPORT-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 5, price: 100000),
        )->assertOk();

        $this->assertTrue(
            Invoice::query()->finalized()->whereKey($invoice->getKey())->exists(),
            'an edited sent invoice must not disappear from sales report / receivables / gross profit scope',
        );
    }

    public function test_cancelled_invoice_still_cannot_be_edited(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Cancelled Edit']);
        $product = $this->product('CANCEL-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $invoice->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 10, price: 100000),
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_editing_an_issued_invoice_writes_an_activity_log_entry(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Activity Log Edit']);
        $product = $this->product('AUDIT-EDIT-01');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 10, price: 100000);
        $invoice->forceFill([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
        ])->save();

        $this->patchJson(
            route('api.invoices.update', $invoice),
            $this->payload($customer, $product, quantity: 10, price: 150000),
        )->assertOk();

        $log = ActivityLog::query()
            ->where('module', 'invoice')
            ->where('action', 'updated_after_issuance')
            ->where('subject_id', $invoice->getKey())
            ->firstOrFail();

        $this->assertSame('Invoice updated after issuance', $log->event);
        $this->assertSame($invoice->invoice_number, $log->metadata['invoice_number']);
        $this->assertSame(Invoice::STATUS_SENT, $log->metadata['status']);
        $this->assertSame(Invoice::PRODUCTION_IN_PRODUCTION, $log->metadata['production_status']);
        $this->assertEqualsWithDelta(1000000.0, (float) $log->metadata['total_before'], 0.001);
        $this->assertEqualsWithDelta(1500000.0, (float) $log->metadata['total_after'], 0.001);
    }

    private function verifiedPayment(Invoice $invoice, float $amount): Payment
    {
        $payment = $invoice->payments()->create([
            'payment_number' => 'PAY-EDIT-'.random_int(1000, 9999),
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        // Mirror what RecordInvoicePayment does so payment_status reflects
        // the payment before the edit under test even runs.
        $invoice->forceFill([
            'payment_status' => $amount >= (float) $invoice->total_amount ? Invoice::PAYMENT_PAID : Invoice::PAYMENT_PARTIAL,
            'paid_at' => $amount >= (float) $invoice->total_amount ? now() : null,
        ])->save();

        return $payment;
    }

    private function product(string $sku, bool $trackStock = false, float $stock = 0): Product
    {
        return Product::query()->create([
            'name' => "Produk {$sku}",
            'sku' => $sku,
            'category' => 'Cetak premium',
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
            'track_stock' => $trackStock,
            'stock' => $trackStock ? $stock : null,
        ]);
    }

    private function receiveGoods(Product $product, float $quantity, float $unitCost): GoodsReceipt
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUP-'.random_int(10000, 99999),
            'name' => 'Supplier Edit '.random_int(1000, 9999),
        ]);

        $poResponse = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-18',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitCost]],
        ])->assertCreated();

        $po = PurchaseOrder::query()->with('items')->findOrFail($poResponse->json('data.id'));
        $this->postJson(route('api.purchase-orders.submit', $po))->assertOk();
        $this->postJson(route('api.purchase-orders.approve', $po))->assertOk();
        $po->refresh()->load('items');

        /** @var PurchaseOrderItem $poItem */
        $poItem = $po->items->first();

        $grResponse = $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => '2026-08-18',
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => $quantity]],
        ])->assertCreated();

        $receipt = GoodsReceipt::query()->findOrFail($grResponse->json('data.id'));
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        return $receipt->refresh();
    }

    private function createInvoiceDraft(Customer $customer, Product $product, float $quantity, float $price): Invoice
    {
        $response = $this->postJson(route('api.invoices.drafts.store'), $this->payload($customer, $product, $quantity, $price))
            ->assertCreated();

        return Invoice::query()->with('items')->findOrFail($response->json('data.id'));
    }

    /** @return array<string, mixed> */
    private function payload(Customer $customer, Product $product, float $quantity, float $price): array
    {
        return [
            'customer_id' => $customer->id,
            'issue_date' => '2026-08-20',
            'due_date' => '2026-09-03',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $price,
            ]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ];
    }
}
