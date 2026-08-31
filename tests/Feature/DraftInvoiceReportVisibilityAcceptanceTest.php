<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\ProfitLossReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 5 acceptance - the exact client scenario (INV-2026-0042: status=draft,
 * payment=paid, production=ready_for_pickup), driven end to end through the
 * real HTTP API and NEVER pressing "kirim via WhatsApp".
 *
 * The rule under test: an invoice is a real business transaction the moment it
 * exists, because that is already when its stock is deducted. Before this
 * change every report gated on status=sent, so such an invoice deducted stock
 * but appeared in no report - stock and reporting permanently out of sync.
 * Only cancellation (which also restores the FIFO stock) removes it.
 *
 * See Invoice::scopeBusinessTransaction().
 */
class DraftInvoiceReportVisibilityAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Anchored mid-month on purpose. The test database is SQLite, which has
     * no DATE type, so Laravel's `date` cast writes "Y-m-d H:i:s" and a
     * whereBetween(issue_date, [from, to]) becomes a STRING comparison there:
     * an invoice issued on the range's last day sorts after the bare "to"
     * date and drops out. Production MySQL stores a real DATE (verified:
     * `SHOW COLUMNS FROM invoices LIKE 'issue_date'` -> type `date`) and
     * matches correctly, so this is a test-environment artifact only - but it
     * does mean the suite cannot catch same-day range-boundary bugs.
     */
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 09:00:00', 'Asia/Jakarta'));
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_paid_in_production_invoice_never_sent_appears_in_every_report(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Bahagia Acceptance']);
        $product = Product::query()->create([
            'name' => 'Cup Injection Acceptance',
            'sku' => 'ACCEPT-DRAFT-01',
            'category' => 'Cup Injection',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        // Stock in 20 pcs @ Rp10.000 through the real PO -> Goods Receipt flow.
        $this->receiveGoods($product, quantity: 20, unitCost: 10000);
        $this->assertSame('20.0000', $product->refresh()->stock);

        // 1. Buat invoice: 10 pcs @ Rp30.000 = Rp300.000, HPP FIFO Rp100.000.
        $created = $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => 30000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->assertCreated();

        $invoice = Invoice::query()->findOrFail($created->json('data.id'));

        // 2. Stok langsung berkurang - inilah alasan invoice ini nyata.
        $this->assertSame('10.0000', $product->refresh()->stock);

        // 3. Pembayaran lunas Rp300.000 masuk dan terverifikasi.
        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => now()->toDateString(),
            'method' => 'transfer_bca',
            'amount' => 300000,
        ])->assertCreated()->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PAID);

        // 4. Produksi jalan sampai siap diambil/kirim.
        foreach ([Invoice::PRODUCTION_IN_PRODUCTION, Invoice::PRODUCTION_READY_FOR_PICKUP] as $productionStatus) {
            $this->patchJson(route('api.invoices.production-status.update', $invoice->invoice_number), [
                'production_status' => $productionStatus,
            ])->assertOk();
        }

        $invoice->refresh();

        // 5. Kondisi persis INV-2026-0042 - dan status masih draft, karena
        //    "kirim via WhatsApp" tidak pernah ditekan.
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertNull($invoice->sent_at);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertSame(Invoice::PRODUCTION_READY_FOR_PICKUP, $invoice->production_status);

        $number = $invoice->invoice_number;
        $period = ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString()];

        // A. Laporan Penjualan - tabel invoice.
        $this->getJson(route('api.reports.sales.invoices.index', $period))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice_number', $number)
            ->assertJsonPath('data.0.total_amount', 300000)
            ->assertJsonPath('data.0.status', Invoice::PAYMENT_PAID);

        // B. Laporan Penjualan - KPI + grafik + export.
        $this->getJson(route('api.reports.sales.summary', $period))
            ->assertOk()
            ->assertJsonPath('data.total_sales', 300000)
            ->assertJsonPath('data.invoice_count', 1)
            ->assertJsonPath('data.paid_amount', 300000);
        $this->getJson(route('api.reports.sales.revenue-chart'))
            ->assertOk()
            ->assertJsonPath('data.totals.revenue', 300000);
        $this->assertStringContainsString(
            $number,
            $this->get(route('api.reports.sales.export', $period))->assertOk()->getContent(),
        );

        // C. Penjualan per Pelanggan - HPP FIFO & laba kotor ikut dihitung.
        $this->getJson(route('api.reports.customer-sales.index', $period))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 1)
            ->assertJsonPath('data.summary.sales', 300000)
            ->assertJsonPath('data.summary.fifo_hpp', 100000)
            ->assertJsonPath('data.summary.gross_profit', 200000)
            ->assertJsonPath('data.customers.0.customer', 'PT Bahagia Acceptance')
            ->assertJsonPath('data.customers.0.invoices.0.invoice_number', $number);

        // D. Laba Kotor.
        $this->getJson(route('api.reports.gross-profit.index', $period))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 1)
            ->assertJsonPath('data.summary.revenue', 300000)
            ->assertJsonPath('data.summary.total_hpp', 100000)
            ->assertJsonPath('data.summary.gross_profit', 200000);

        // E. Laba Rugi.
        $summary = app(ProfitLossReport::class)->build('monthly')['summary'];
        $this->assertSame(300000.0, $summary['sales_revenue']);
        $this->assertSame(100000.0, $summary['total_hpp']);
        $this->assertSame(200000.0, $summary['gross_profit']);
        $this->assertSame(10.0, $summary['sales_quantity']);
        $this->assertSame(1, $summary['invoice_count']);

        // F. Dashboard - halaman, financial summary, dan grafik pendapatan.
        $this->get(route('dashboard'))->assertOk()->assertSee('Rp300.000');
        $this->getJson(route('api.dashboard.financial-summary'))
            ->assertOk()
            ->assertJsonPath('data.total_sales', 300000)
            ->assertJsonPath('data.paid_amount', 300000)
            ->assertJsonPath('data.paid_count', 1)
            ->assertJsonPath('data.total_invoices_count', 1);
        $this->getJson(route('api.dashboard.revenue-chart'))
            ->assertOk()
            ->assertJsonPath('data.issued.5', 300000);

        // G. Detail pelanggan + rekening koran.
        $this->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Rp300.000')
            ->assertSee('1 invoice aktif');
        $this->getJson(route('api.customers.statement.show', ['customer' => $customer]))
            ->assertOk()
            ->assertJsonPath('data.summary.total_debit', 300000)
            ->assertJsonPath('data.summary.total_credit', 300000)
            ->assertJsonPath('data.summary.outstanding_amount', 0)
            ->assertJsonPath('data.transactions.0.reference_number', $number);

        // H. Produk terlaris / kolom "Terjual".
        $products = collect($this->get(route('products.index'))->assertOk()->viewData('products'));
        $this->assertSame(1, $products->firstWhere('sku', 'ACCEPT-DRAFT-01')['sales']);

        // I. Lunas -> tidak lagi jadi piutang (tidak double count).
        $this->assertFalse(Invoice::query()->receivable()->whereKey($invoice->getKey())->exists());
        $this->assertSame(0.0, $invoice->remainingAmount());

        // J. Tidak perlu diubah jadi "sent" agar semua di atas berlaku.
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->refresh()->status);
    }

    public function test_cancelling_that_same_invoice_removes_it_from_every_report(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Batal Acceptance']);
        $product = Product::query()->create([
            'name' => 'Cup Injection Batal',
            'sku' => 'ACCEPT-CANCEL-01',
            'category' => 'Cup Injection',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $this->receiveGoods($product, quantity: 20, unitCost: 10000);

        $created = $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => 30000]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->assertCreated();

        $invoice = Invoice::query()->findOrFail($created->json('data.id'));
        $period = ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString()];

        $this->getJson(route('api.reports.sales.invoices.index', $period))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->postJson(route('api.invoices.cancel.store', $invoice->invoice_number), [
            'reason' => 'Pesanan dibatalkan pelanggan.',
        ])->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->status);
        // Cancelling restores the FIFO stock, so it must stop counting as a
        // sale everywhere too.
        $this->assertSame('20.0000', $product->refresh()->stock);

        $this->getJson(route('api.reports.sales.invoices.index', $period))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson(route('api.reports.sales.summary', $period))
            ->assertOk()
            ->assertJsonPath('data.total_sales', 0)
            ->assertJsonPath('data.invoice_count', 0);
        $this->getJson(route('api.reports.customer-sales.index', $period))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 0)
            ->assertJsonPath('data.summary.sales', 0);
        $this->getJson(route('api.reports.gross-profit.index', $period))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 0);
        $this->getJson(route('api.dashboard.financial-summary'))
            ->assertOk()
            ->assertJsonPath('data.total_sales', 0)
            ->assertJsonPath('data.total_invoices_count', 0);

        $summary = app(ProfitLossReport::class)->build('monthly')['summary'];
        $this->assertSame(0.0, $summary['sales_revenue']);
        $this->assertSame(0, $summary['invoice_count']);

        $products = collect($this->get(route('products.index'))->assertOk()->viewData('products'));
        $this->assertSame(0, $products->firstWhere('sku', 'ACCEPT-CANCEL-01')['sales']);
    }

    private function receiveGoods(Product $product, float $quantity, float $unitCost): GoodsReceipt
    {
        $supplier = Supplier::query()->create([
            'code' => 'SUP-'.random_int(10000, 99999),
            'name' => 'Supplier Acceptance',
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
