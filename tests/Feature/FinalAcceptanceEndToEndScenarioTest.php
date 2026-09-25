<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * PHASE 6 - final acceptance scenario from the client brief, end to end
 * through the real HTTP API (purchase order -> goods receipt -> invoice ->
 * unpaid edit -> DP -> production -> reports), covering all 5 phases together.
 *
 * DP-before-production is an existing, deliberately-preserved business rule
 * (UpdateInvoiceProductionStatus: advancing past awaiting_dp requires
 * verifiedPaidAmount() >= requiredDpAmount()), so this test records and
 * verifies the DP before moving production to in_production - the brief's
 * numbered narrative describes the end state each step reaches, not a
 * mandate to bypass that existing gate.
 */
class FinalAcceptanceEndToEndScenarioTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_full_lifecycle_status_payment_piutang_fifo_and_shipping_stay_correct(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Skenario Akhir']);
        $product = Product::query()->create([
            'name' => 'Produk Skenario Akhir',
            'sku' => 'FINAL-SCENARIO-01',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        // Stock in: 20 pcs @ Rp10.000 via a real PO -> Goods Receipt flow.
        $this->receiveGoods($product, quantity: 20, unitCost: 10000);
        $this->assertSame('20.0000', $product->refresh()->stock);

        // 1. Buat invoice Rp300.000 (10 pcs @ Rp30.000, no shipping).
        $response = $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => 30000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->assertCreated()
            ->assertJsonPath('data.total_amount', '300000.00')
            ->assertJsonPath('data.total_hpp', '100000.00');

        $invoice = Invoice::query()->with('items')->findOrFail($response->json('data.id'));
        $this->assertSame('10.0000', $product->refresh()->stock);

        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();

        // 2. While still unpaid, edit the invoice to Rp350.000. This remains
        // allowed and keeps its issuance/FIFO state intact.
        $this->patchJson(route('api.invoices.update', $invoice), [
            'customer_id' => $customer->id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => 35000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])
            ->assertOk()
            ->assertJsonPath('data.total_amount', '350000.00')
            ->assertJsonPath('data.status', 'sent');

        $invoice->refresh();

        // 3. Catat DP Rp175.000 dan verify (exactly the 50% default DP
        // requirement for a 350k invoice, so production can advance next).
        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => now()->toDateString(),
            'method' => 'transfer_bca',
            'amount' => 175000,
        ])->assertCreated()->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PARTIAL);

        // 4. Invoice masuk produksi.
        $this->patchJson(route('api.invoices.production-status.update', $invoice->invoice_number), [
            'production_status' => Invoice::PRODUCTION_IN_PRODUCTION,
        ])->assertOk();

        $invoice->refresh();

        // 5. Status pembayaran menjadi PARSIAL.
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->payment_status);
        // 6. Outstanding = Rp175.000.
        $this->assertSame(175000.0, $invoice->remainingAmount());
        // 7. Invoice masuk Total Piutang.
        $this->assertTrue(Invoice::query()->receivable()->whereKey($invoice->getKey())->exists());

        // 8. A partial invoice remains editable while production is running.
        $this->patchJson(route('api.invoices.update', $invoice), [
            'customer_id' => $customer->id,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => 40000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])
            ->assertOk()
            ->assertJsonPath('data.total_amount', '400000.00');

        $invoice->refresh();

        // 9. Verified paid tetap Rp175.000.
        $this->assertSame(175000.0, $invoice->verifiedPaidAmount());
        // 10. Payment status tetap PARSIAL.
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->payment_status);
        // 11. Outstanding dan total mengikuti edit terbaru.
        $this->assertSame(225000.0, $invoice->remainingAmount());
        $this->assertSame('400000.00', (string) $invoice->total_amount);
        // status tetap sent, tidak pernah kembali draft.
        $this->assertSame(Invoice::STATUS_SENT, $invoice->status);

        // 12. FIFO/HPP tetap valid: 10 pcs masih dari batch yang sama
        // (Rp10.000/pcs) - HPP tidak berubah walau harga jual berubah.
        $this->assertSame('100000.00', (string) $invoice->total_hpp);
        $this->assertSame('10.0000', $product->refresh()->stock, 'quantity tidak berubah, stok tidak boleh ikut berubah');
        $this->assertSame(
            round((float) InventoryBatch::query()->where('product_id', $product->id)->sum('qty_remaining'), 4),
            round((float) $product->stock, 4),
            'FIFO batch harus tetap rekonsiliasi dengan stok produk',
        );

        // 13. Production status tidak reset oleh edit.
        $this->assertSame(Invoice::PRODUCTION_IN_PRODUCTION, $invoice->production_status);

        // 14. Detail invoice menampilkan ongkir jika ada - di skenario ini
        // tidak ada ongkir sama sekali, jadi baris "Ongkir" harus tetap
        // tersembunyi (kasus "jika ada" terbukti sudah lengkap terpisah di
        // InvoiceDetailShippingBreakdownTest).
        $this->get(route('payments.invoices.show', $invoice->invoice_number))
            ->assertOk()
            ->assertDontSee('Ongkir')
            ->assertSeeInOrder(['Subtotal', 'Rp400.000', 'Total invoice', 'Rp400.000']);

        // 15. Semua laporan terkait membaca nilai terbaru dengan benar.
        $this->assertTrue(Invoice::query()->finalized()->whereKey($invoice->getKey())->exists());
        $this->get(route('invoices.index'))->assertOk()->assertSee('Rp400.000');
        $this->get(route('payments.receivables.index'))->assertOk()->assertSee('Rp225.000');
        $this->get(route('customers.show', $customer))->assertOk()->assertSee('Rp400.000');
    }

    private function receiveGoods(Product $product, float $quantity, float $unitCost): GoodsReceipt
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUP-'.random_int(10000, 99999),
            'name' => 'Supplier Skenario Akhir',
        ]);

        $poResponse = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitCost]],
        ])->assertCreated();

        $po = PurchaseOrder::query()->with('items')->findOrFail($poResponse->json('data.id'));
        $this->postJson(route('api.purchase-orders.submit', $po))->assertOk();
        $this->postJson(route('api.purchase-orders.approve', $po))->assertOk();
        $po->refresh()->load('items');

        /** @var PurchaseOrderItem $poItem */
        $poItem = $po->items->first();

        $grResponse = $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => now()->toDateString(),
            'items' => [['purchase_order_item_id' => $poItem->id, 'quantity_received' => $quantity]],
        ])->assertCreated();

        $receipt = GoodsReceipt::query()->findOrFail($grResponse->json('data.id'));
        $this->postJson(route('api.goods-receipts.post', $receipt))->assertOk();

        return $receipt->refresh();
    }
}
