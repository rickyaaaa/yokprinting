<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Invoices\BuildInvoiceDocument;
use App\Services\Invoices\CalculateInvoicePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));
    }

    public function test_service_create_normalizes_inventory_fields_and_keeps_catalog_category(): void
    {
        $category = ProductCategory::create(['name' => 'Printing']);
        $data = $this->postJson(route('api.products.store'), [
            'name' => 'Jasa Printing', 'product_type' => 'service', 'category_id' => $category->id,
            'unit' => 'Pekerjaan', 'track_stock' => true, 'stock' => 5000,
            'manual_unit_cost' => 400, 'minimum_stock' => 500, 'package_conversion' => 500,
        ])->assertCreated()->assertJsonPath('data.product_type', 'service')
            ->assertJsonPath('data.track_stock', false)->assertJsonPath('data.stock', null)
            ->assertJsonPath('data.unit', 'Pekerjaan')->assertJsonPath('data.package_conversion', 1)
            ->assertJsonPath('data.category', 'Printing')->json('data');

        $this->assertDatabaseCount('inventory_batches', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0.0, Product::findOrFail($data['id'])->fifoInventoryValue());
        $this->assertSame(0, Product::lowStock()->count());
        $this->patchJson(route('api.products.update', $data['id']), ['name' => 'Jasa Full Printing', 'unit' => 'Paket'])
            ->assertOk()->assertJsonPath('data.unit', 'Paket')->assertJsonPath('data.product_type', 'service');
        $this->getJson(route('api.products.show', $data['id']))->assertOk()->assertJsonPath('data.track_stock', false);
    }

    public function test_type_filters_preserve_legacy_non_stock_goods_without_guessing_service(): void
    {
        $legacy = Product::create(['name' => 'Barang lama', 'track_stock' => false]);
        $service = Product::create(['name' => 'Jasa', 'product_type' => 'service', 'unit' => 'Jam']);
        foreach (['api.products.index', 'api.products.options.index'] as $route) {
            $this->getJson(route($route, ['product_type' => 'service']))->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $service->id)->assertJsonPath('data.0.unit', 'Jam');
            $this->getJson(route($route, ['product_type' => 'goods']))->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $legacy->id)->assertJsonPath('data.0.product_type_unclassified', true);
        }
        $this->assertNull($legacy->fresh()->product_type);
        $this->assertFalse($legacy->fresh()->track_stock);
    }

    public function test_classification_edit_is_audited_and_unsafe_changes_are_blocked(): void
    {
        $empty = Product::create(['name' => 'Belum dipakai', 'track_stock' => false]);
        $this->patchJson(route('api.products.update', $empty), ['product_type' => 'service'])
            ->assertOk()->assertJsonPath('data.product_type', 'service');
        $log = ActivityLog::where('action', 'classification_updated')->firstOrFail();
        $this->assertSame('service', $log->metadata['after']['product_type']);
        $this->assertNotNull($log->user_id);

        $goods = Product::create(['name' => 'Cup', 'stock' => 1000, 'track_stock' => true]);
        $this->patchJson(route('api.products.update', $goods), ['product_type' => 'service'])
            ->assertUnprocessable()->assertJsonValidationErrors('product_type');
        $this->assertEquals(1000, $goods->fresh()->stock);

        $invoice = Invoice::create(['customer_id' => Customer::create(['name' => 'Client'])->id,
            'invoice_number' => 'INV-GUARD', 'issue_date' => '2026-10-13', 'due_date' => '2026-10-20']);
        $invoice->items()->create(['product_id' => $empty->id, 'product_name' => $empty->name, 'quantity' => 1]);
        $this->patchJson(route('api.products.update', $empty), ['product_type' => 'goods', 'track_stock' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('product_type');
        $this->assertSame('service', $empty->fresh()->product_type);
    }

    public function test_mixed_invoice_dp_deducts_only_goods_once_and_preserves_service_snapshot_cost(): void
    {
        $goods = Product::create(['name' => 'Cup', 'product_type' => 'goods', 'track_stock' => true, 'stock' => 1000]);
        InventoryBatch::create(['product_id' => $goods->id, 'purchase_date' => '2026-10-01',
            'qty_received' => 1000, 'qty_remaining' => 1000, 'unit_cost' => 400, 'source_type' => 'manual_opening']);
        $service = Product::create(['name' => 'Jasa Printing', 'product_type' => 'service', 'unit' => 'Pekerjaan']);
        $service->forceFill(['last_purchase_price' => 50000])->save();
        $payload = $this->invoicePayload($goods, $service);
        // Spoofed type/stock/unit on a goods line must never override master.
        $payload['items'][0] += ['product_type' => 'service', 'track_stock' => false, 'unit' => 'Jam'];
        $id = $this->postJson(route('api.invoices.drafts.store'), $payload)->assertCreated()->json('data.id');
        $invoice = Invoice::findOrFail($id);
        $items = $invoice->items()->orderBy('sort_order')->get();
        $this->assertSame('Pcs', $items[0]->unit);
        $this->assertTrue($items[0]->tracksInventory());
        $this->assertSame('service', $items[1]->productType());
        $this->assertFalse($items[1]->tracksInventory());
        $this->assertNull($items[1]->cup_size);
        $this->assertEquals(50000, $items[1]->hpp_total);
        $this->assertSame('Pekerjaan', $items[1]->unit);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(1000, $goods->fresh()->stock);

        $this->pay($invoice, 325000);
        $this->assertEquals(500, $goods->fresh()->stock);
        $this->assertSame(1, StockMovement::where('product_id', $goods->id)->count());
        $this->assertSame(0, StockMovement::where('product_id', $service->id)->count());
        $this->assertSame(0, InventoryBatch::where('product_id', $service->id)->count());
        $this->pay($invoice, 325000);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->fresh()->payment_status);

        $this->patchJson(route('api.invoices.production-status.update', $invoice->invoice_number), ['production_status' => Invoice::PRODUCTION_COMPLETED])->assertOk();
        $service->forceFill(['last_purchase_price' => 90000])->save();
        $this->patchJson(route('api.invoices.update', $invoice->invoice_number), $payload)->assertOk();
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertEquals(50000, $items[1]->fresh()->hpp_total);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->fresh()->payment_status);
        $document = app(BuildInvoiceDocument::class)->fromInvoice($invoice->fresh()->load('items', 'customer'));
        $this->assertStringContainsString('Pekerjaan', json_encode($document));
        $this->assertStringContainsString('Jasa Printing', json_encode($document));
        $pdf = $this->get(route('api.invoices.pdf.download', $invoice->id))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->getJson(route('api.reports.sales.invoices.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->postJson(route('api.invoices.cancel.store', $invoice->invoice_number), ['reason' => 'Test restore'])->assertOk();
        $this->assertEquals(1000, $goods->fresh()->stock);
        $this->assertSame(0, StockMovement::where('product_id', $service->id)->count());
    }

    public function test_goods_quantity_rules_cannot_be_bypassed_by_claiming_to_be_service(): void
    {
        $goods = Product::create(['name' => 'Cup', 'track_stock' => true]);
        $service = Product::create(['name' => 'Jasa', 'product_type' => 'service']);
        $payload = $this->invoicePayload($goods, $service);
        $payload['items'][0]['quantity'] = 1;
        $payload['items'][0]['product_type'] = 'service';
        $this->postJson(route('api.invoices.drafts.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_service_only_invoice_can_be_paid_resaved_and_cancelled_without_inventory(): void
    {
        $service = Product::create(['name' => 'Desain Logo', 'product_type' => 'service', 'unit' => 'Paket']);
        $payload = $this->invoicePayload($service, $service);
        $payload['items'] = [['product_id' => $service->id, 'quantity' => 1, 'price' => 150000]];
        $id = $this->postJson(route('api.invoices.drafts.store'), $payload)->assertCreated()->json('data.id');
        $invoice = Invoice::findOrFail($id);
        $this->pay($invoice, 150000);
        $this->patchJson(route('api.invoices.update', $invoice->invoice_number), $payload)->assertOk();
        $this->postJson(route('api.invoices.cancel.store', $invoice->invoice_number), ['reason' => 'Test'])->assertOk();
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_batches', 0);
        $this->assertDatabaseCount('invoice_item_cost_layers', 0);
    }

    public function test_service_preview_unit_and_form_pages_are_correct(): void
    {
        $preview = app(CalculateInvoicePreview::class)->calculate([
            'items' => [['name' => 'Printing', 'quantity' => 1, 'unit' => 'Pekerjaan', 'unit_price' => 150000]],
        ]);
        $this->assertSame('1 Pekerjaan', $preview['items'][0]['quantity_label']);
        $this->get(route('products.create'))->assertOk()->assertSee('Jenis Barang')->assertSee('value="service"', false);
        $this->get(route('invoices.create'))->assertOk()->assertSee('Jenis Barang')->assertSee('Keterangan jasa');
    }

    public function test_service_cannot_enter_stock_adjustment_report_and_catalog_export_keeps_it(): void
    {
        $service = Product::create(['name' => 'Jasa Printing', 'product_type' => 'service', 'unit' => 'Pekerjaan']);
        $this->postJson(route('api.stock-movements.store'), ['product_id' => $service->id, 'type' => 'adjustment', 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->getJson(route('api.reports.stock-mutations.index', ['product_id' => $service->id]))
            ->assertOk()->assertJsonPath('data.product', null)->assertJsonCount(0, 'data.products');
        $this->assertStringStartsWith('PK', $this->get(route('api.products.export.excel'))->assertOk()->getContent());
        $this->assertStringStartsWith('%PDF-', $this->get(route('api.products.export.pdf'))->assertOk()->getContent());
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_read_only_type_audit_reports_ambiguous_legacy_data_without_backfill(): void
    {
        $legacy = Product::create(['name' => 'Belum dipetakan', 'track_stock' => false]);
        $this->artisan('app:audit-product-types', ['--json' => true])->assertSuccessful();
        $this->assertNull($legacy->fresh()->product_type);
        $this->assertFalse($legacy->fresh()->track_stock);
    }

    private function invoicePayload(Product $goods, Product $service): array
    {
        return ['customer_id' => Customer::create(['name' => 'Sejuk'])->id,
            'issue_date' => '2026-10-13', 'due_date' => '2026-10-20',
            'items' => [
                ['product_id' => $goods->id, 'quantity' => 500, 'price' => 1000],
                ['product_id' => $service->id, 'quantity' => 1, 'price' => 150000, 'description' => 'Printing desain pelanggan'],
            ], 'discount' => ['type' => 'fixed', 'value' => 0], 'tax' => ['enabled' => false, 'rate' => 0]];
    }

    private function pay(Invoice $invoice, float $amount): void
    {
        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => '2026-10-13', 'method' => Payment::METHOD_TRANSFER_BCA, 'amount' => $amount,
        ])->assertCreated();
    }
}
