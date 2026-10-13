<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Invoices\BuildInvoiceDocument;
use App\Services\Invoices\CalculateInvoicePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ServicePrintingPerPcsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));
    }

    public function test_new_service_defaults_to_pcs_in_master_api_and_options(): void
    {
        $id = $this->postJson(route('api.products.store'), ['name' => 'Jasa Printing', 'product_type' => 'service'])
            ->assertCreated()->assertJsonPath('data.unit', 'Pcs')->assertJsonPath('data.track_stock', false)
            ->assertJsonPath('data.package_conversion', 1)->json('data.id');
        $this->getJson(route('api.products.show', $id))->assertOk()->assertJsonPath('data.unit', 'Pcs');
        $this->getJson(route('api.products.options.index', ['product_type' => 'service']))->assertOk()
            ->assertJsonPath('data.0.unit', 'Pcs')->assertJsonPath('data.0.track_stock', false);
    }

    public function test_official_unit_correction_is_audited_and_used_by_new_invoices_and_options(): void
    {
        $service = Product::create(['sku' => 'H-128', 'name' => 'Jasa Cetak', 'product_type' => 'service', 'unit' => 'Pekerjaan']);
        $this->patchJson(route('api.products.update', $service), ['unit' => 'Pcs'])
            ->assertOk()->assertJsonPath('data.unit', 'Pcs')->assertJsonPath('data.track_stock', false);
        $log = ActivityLog::where('action', 'unit_updated')->sole();
        $this->assertSame('Pekerjaan', $log->metadata['before']['unit']);
        $this->assertSame('Pcs', $log->metadata['after']['unit']);
        $this->assertSame('H-128', $log->metadata['sku']);
        $this->assertEquals($service->id, $log->subject_id);
        $this->assertSame(Product::class, $log->subject_type);
        $this->assertEquals(auth()->id(), $log->user_id);
        $this->assertSame('api.products.update', $log->metadata['route']);
        $this->getJson(route('api.products.show', $service))->assertOk()->assertJsonPath('data.unit', 'Pcs');
        $this->getJson(route('api.products.options.index', ['search' => 'H-128', 'product_type' => 'service']))
            ->assertOk()->assertJsonPath('data.0.unit', 'Pcs')->assertJsonPath('data.0.package_conversion', 1);

        $payload = $this->payload($service);
        $payload['items'][0] += ['unit' => 'Pekerjaan', 'packaging_unit' => 'Pekerjaan', 'product_type' => 'goods', 'track_stock' => true];
        $id = $this->postJson(route('api.invoices.drafts.store'), $payload)->assertCreated()
            ->assertJsonPath('data.items.0.unit', 'Pcs')->assertJsonPath('data.items.0.track_stock', false)->json('data.id');
        $invoice = Invoice::findOrFail($id);
        $this->assertEquals(200000, $invoice->total_amount);
        $item = $invoice->items()->firstOrFail();
        $this->assertSame('Pcs', $item->packaging_unit);
        $this->assertEquals(0, $item->hpp_total);
        $this->assertEquals(0, $service->fresh()->purchaseCostFallback());
        $this->assertSame('Pcs', $this->get(route('invoices.edit', $invoice->invoice_number))
            ->assertOk()->viewData('draftPayload')['items'][0]['unit']);
        $this->assertSame('Pcs', app(BuildInvoiceDocument::class)->fromInvoice($invoice)['items'][0]['unit']);
        $this->assertStringStartsWith('%PDF-', $this->get(route('api.invoices.pdf.download', $id))->assertOk()->getContent());
        $this->assertStringContainsString('1.000 Pcs', $this->get(route('api.invoices.export.orders.csv', [
            'date_from' => '2026-10-13', 'date_to' => '2026-10-13',
        ]))->assertOk()->getContent());
        $this->assertDatabaseCount('inventory_batches', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('invoice_item_cost_layers', 0);
    }

    public function test_no_op_unit_save_does_not_create_a_duplicate_audit_event(): void
    {
        $service = Product::create(['name' => 'Jasa Printing', 'product_type' => 'service', 'unit' => 'Pekerjaan']);
        foreach (['Pcs', 'Pcs'] as $unit) {
            $this->patchJson(route('api.products.update', $service), ['unit' => $unit])->assertOk();
        }
        $this->patchJson(route('api.products.update', $service), ['name' => 'Jasa Printing Baru'])->assertOk();
        $this->assertSame(1, ActivityLog::where('action', 'unit_updated')->count());
        $this->assertSame(0, ActivityLog::where('action', 'classification_updated')->count());
        $this->assertSame('Pcs', $service->fresh()->unit);
    }

    public function test_corrected_pcs_service_in_mixed_invoice_never_consumes_stock_on_dp_settlement_or_resave(): void
    {
        $service = Product::create(['name' => 'Jasa Cetak', 'product_type' => 'service', 'unit' => 'Pekerjaan']);
        $this->patchJson(route('api.products.update', $service), ['unit' => 'Pcs'])->assertOk();
        $goods = Product::create(['name' => 'Cup', 'product_type' => 'goods', 'track_stock' => true, 'stock' => 1000]);
        $batch = InventoryBatch::create(['product_id' => $goods->id, 'purchase_date' => '2026-10-01',
            'qty_received' => 1000, 'qty_remaining' => 1000, 'unit_cost' => 400, 'source_type' => 'manual_opening']);
        $payload = $this->payload($service);
        $payload['items'][] = ['product_id' => $goods->id, 'quantity' => 500, 'price' => 1000];
        $id = $this->postJson(route('api.invoices.drafts.store'), $payload)->assertCreated()->json('data.id');
        $invoice = Invoice::findOrFail($id);
        $this->assertEquals(700000, $invoice->total_amount);
        $this->assertEquals(1000, $goods->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
        foreach ([350000, 350000] as $amount) {
            $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
                'payment_date' => '2026-10-13', 'method' => Payment::METHOD_TRANSFER_BCA, 'amount' => $amount,
            ])->assertCreated();
            $this->assertEquals(500, $goods->fresh()->stock);
            $this->assertEquals(500, $batch->fresh()->qty_remaining);
            $this->assertDatabaseCount('stock_movements', 1);
        }
        foreach ([1, 2] as $attempt) {
            $this->patchJson(route('api.invoices.update', $invoice->invoice_number), $payload)->assertOk();
        }
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->fresh()->payment_status);
        $this->assertEquals(500, $goods->fresh()->stock);
        $this->assertEquals(500, $batch->fresh()->qty_remaining);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('inventory_batches', 1);
        $serviceItem = $invoice->items()->where('product_id', $service->id)->firstOrFail();
        $this->assertSame('Pcs', $serviceItem->unit);
        $this->assertEquals(1000, $serviceItem->quantity);
        $this->assertSame(0, $serviceItem->costLayers()->count());
        $this->assertSame(0, $service->stockMovements()->count());
        $this->assertSame(0, $service->inventoryBatches()->count());
    }

    public function test_printing_acceptance_1000_pcs_at_200_keeps_specs_without_inventory(): void
    {
        $service = Product::create(['name' => 'Jasa Printing', 'product_type' => 'service']);
        $service->forceFill(['last_purchase_price' => 50])->save();
        $payload = $this->payload($service);
        $id = $this->postJson(route('api.invoices.drafts.store'), $payload)->assertCreated()
            ->assertJsonPath('data.items.0.unit', 'Pcs')
            ->assertJsonPath('data.items.0.screen_printing_color', 'Hitam')
            ->assertJsonPath('data.items.0.jenis_cetak', '1 warna')->json('data.id');
        $invoice = Invoice::findOrFail($id);
        $item = $invoice->items()->firstOrFail();
        $this->assertEquals(200000, $invoice->total_amount);
        $this->assertEquals(50000, $item->hpp_total);
        $this->assertSame('Hitam', $item->screen_printing_color);
        $this->assertSame('1 warna', $item->jenis_cetak);
        $this->assertNull($item->cup_size);
        $this->assertFalse($item->tracksInventory());
        $this->assertEquals(1000, $item->quantity);

        foreach ([100000, 100000] as $amount) {
            $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
                'payment_date' => '2026-10-13', 'method' => Payment::METHOD_TRANSFER_BCA, 'amount' => $amount,
            ])->assertCreated();
        }
        // Catalogue cost changes must not reprice a previously saved service line.
        $service->forceFill(['last_purchase_price' => 70])->save();
        foreach ([1, 2] as $attempt) {
            $this->patchJson(route('api.invoices.update', $invoice->invoice_number), $payload)->assertOk()
                ->assertJsonPath('data.items.0.screen_printing_color', 'Hitam')
                ->assertJsonPath('data.items.0.jenis_cetak', '1 warna');
        }
        $this->assertEquals(50000, $item->fresh()->hpp_total);
        $this->assertSame('Printing desain pelanggan', $item->fresh()->description);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->fresh()->payment_status);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('inventory_batches', 0);
        $this->assertDatabaseCount('invoice_item_cost_layers', 0);
        $edit = $this->get(route('invoices.edit', $invoice->invoice_number))->assertOk();
        $draft = $edit->viewData('draftPayload');
        $this->assertSame('Pcs', $draft['items'][0]['unit']);
        $this->assertSame('Hitam', $draft['items'][0]['screen_printing_color']);
        $this->assertSame('1 warna', $draft['items'][0]['jenis_cetak']);

        $document = app(BuildInvoiceDocument::class)->fromInvoice($invoice->fresh());
        $this->assertSame('Pcs', $document['items'][0]['unit']);
        $this->assertSame('Printing desain pelanggan · Tinta Hitam · 1 warna', $document['items'][0]['description']);
        $html = view('pdf.invoices.document', ['document' => $document])->render();
        $this->assertStringContainsString('Tinta Hitam', $html);
        $this->assertStringContainsString('1 warna', $html);
        $this->assertStringContainsString('Pcs', $html);
        $this->assertStringStartsWith('%PDF-', $this->get(route('api.invoices.pdf.download', $invoice->id))->assertOk()->getContent());
        $csv = $this->get(route('api.invoices.export.orders.csv', ['date_from' => '2026-10-13', 'date_to' => '2026-10-13']))->assertOk()->getContent();
        $this->assertStringContainsString('1.000 Pcs', $csv);
        $this->assertStringContainsString('Hitam', $csv);
        $this->assertStringContainsString('1 warna', $csv);
    }

    public function test_legacy_invoice_keeps_unit_quantity_price_cost_and_blank_specs_after_master_edit(): void
    {
        $service = Product::create(['name' => 'Jasa lama', 'product_type' => 'service', 'unit' => 'Pekerjaan']);
        $service->forceFill(['last_purchase_price' => 50000])->save();
        $payload = $this->payload($service);
        $payload['items'] = [['product_id' => $service->id, 'quantity' => 1, 'price' => 150000, 'description' => 'Desain lama', 'screen_printing_color' => null, 'jenis_cetak' => null]];
        $id = $this->postJson(route('api.invoices.drafts.store'), $payload)->assertCreated()->json('data.id');
        $invoice = Invoice::findOrFail($id);
        $snapshotBefore = $invoice->items()->firstOrFail()->only([
            'unit', 'packaging_unit', 'quantity', 'unit_price', 'purchase_cost_snapshot', 'unit_hpp', 'hpp_total', 'subtotal', 'total_amount',
        ]);
        $invoiceBefore = $invoice->only(['subtotal', 'total_amount', 'total_hpp']);
        $this->patchJson(route('api.products.update', $service), ['unit' => 'Pcs', 'screen_printing_color' => 'Hitam'])->assertOk();
        $this->assertSame($snapshotBefore, $invoice->items()->firstOrFail()->only(array_keys($snapshotBefore)));
        $this->assertSame($invoiceBefore, $invoice->fresh()->only(array_keys($invoiceBefore)));
        $this->patchJson(route('api.invoices.update', $invoice->invoice_number), $payload)->assertOk();
        $item = $invoice->items()->firstOrFail();
        $this->assertSame($snapshotBefore, $item->only(array_keys($snapshotBefore)));
        $this->assertSame('Pekerjaan', $item->unit);
        $this->assertEquals(1, $item->quantity);
        $this->assertEquals(150000, $item->unit_price);
        $this->assertEquals(50000, $item->hpp_total);
        $this->assertNull($item->screen_printing_color);
        $this->assertNull($item->jenis_cetak);
        $document = app(BuildInvoiceDocument::class)->fromInvoice($invoice->fresh());
        $this->assertSame('Pekerjaan', $document['items'][0]['unit']);
        $this->assertSame('Desain lama', $document['items'][0]['description']);
        $edit = $this->get(route('invoices.edit', $invoice->invoice_number))->assertOk();
        $this->assertSame('Pekerjaan', $edit->viewData('draftPayload')['items'][0]['unit']);
        $this->assertStringStartsWith('%PDF-', $this->get(route('api.invoices.pdf.download', $invoice->id))->assertOk()->getContent());
    }

    public function test_preview_and_pdf_use_pcs_and_print_specs(): void
    {
        $preview = app(CalculateInvoicePreview::class)->calculate([
            'invoice_number' => 'PREVIEW-PCS', 'customer' => ['name' => 'Sejuk'],
            'items' => [['name' => 'Jasa Printing', 'quantity' => 1000, 'unit' => 'Pcs', 'unit_price' => 200,
                'description' => 'Printing desain pelanggan · Tinta Hitam · 1 warna',
                'print_description' => 'Printing desain pelanggan · Tinta Hitam · 1 warna']],
        ]);
        $this->assertSame('1.000 Pcs', $preview['items'][0]['quantity_label']);
        $this->assertEquals(200000, $preview['total_amount']);
        $document = app(BuildInvoiceDocument::class)->fromPreview($preview);
        $this->assertStringContainsString('Tinta Hitam', $document['items'][0]['description']);
        $this->assertStringContainsString('Tinta Hitam', view('pdf.invoices.document', ['document' => $document])->render());
        $this->assertStringStartsWith('%PDF-', $this->postJson(route('api.invoices.preview.pdf.download'), $preview)->assertOk()->getContent());
    }

    public function test_non_pcs_master_audit_is_read_only_and_lists_only_services_to_review(): void
    {
        $old = Product::create(['name' => 'Jasa lama', 'product_type' => 'service', 'unit' => 'Pekerjaan']);
        Product::create(['name' => 'Jasa per pcs', 'product_type' => 'service']);
        Artisan::call('app:audit-product-types', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);
        $this->assertTrue($report['read_only']);
        $this->assertSame(1, $report['service_non_pcs_count']);
        $this->assertSame($old->id, $report['services_needing_unit_review'][0]['id']);
        $this->assertSame('Pekerjaan', $old->fresh()->unit);
    }

    public function test_create_form_print_panel_is_shared_and_not_hidden_for_services(): void
    {
        $html = $this->get(route('invoices.create'))->assertOk()->getContent();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument;
            $dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $panel = $xpath->query('//*[@data-testid="create-invoice-specs"]')->item(0);
            $this->assertNotNull($panel);
            $this->assertFalse($panel->hasAttribute('x-show'));
            $this->assertStringContainsString('Tinta', $panel->textContent);
            $this->assertStringContainsString('Jenis cetak', $panel->textContent);
            $priceInput = $xpath->query('//input[@step="1"]')->item(0);
            $this->assertNotNull($priceInput);
            $this->assertSame('1', $priceInput->getAttribute('step'));
            $this->assertStringContainsString('Harga per', $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function payload(Product $service): array
    {
        return ['customer_id' => Customer::create(['name' => 'Sejuk'])->id,
            'issue_date' => '2026-10-13', 'due_date' => '2026-10-20',
            'items' => [['product_id' => $service->id, 'quantity' => 1000, 'price' => 200,
                'screen_printing_color' => 'Hitam', 'jenis_cetak' => '1 warna', 'cup_size' => '12 Oz',
                'description' => 'Printing desain pelanggan']],
            'discount' => ['type' => 'fixed', 'value' => 0], 'tax' => ['enabled' => false, 'rate' => 0]];
    }
}
