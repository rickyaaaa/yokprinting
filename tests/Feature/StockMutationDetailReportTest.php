<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class StockMutationDetailReportTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_selected_product_report_lists_customer_and_running_balance(): void
    {
        $customer = Customer::query()->create(['name' => 'Toko Plastik Berkah']);
        $product = Product::query()->create([
            'sku' => '100053',
            'name' => 'Cup Injection 14Oz Datar (400ml) Natural',
            'track_stock' => true,
            'stock' => 32000,
        ]);
        $this->movement($product, StockMovement::TYPE_OPENING_BALANCE, 34000, 'SALDO-AWAL', '2026-08-31 09:00:00');
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-09-0001',
            'issue_date' => '2026-09-07',
            'due_date' => '2026-09-21',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 100000,
        ]);
        $this->movement($product, StockMovement::TYPE_SALE, -2000, $invoice->invoice_number, '2026-09-07 10:00:00');

        $response = $this->getJson(route('api.reports.stock-movements.index', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'product_id' => $product->id,
        ]))->assertOk();

        $response
            ->assertJsonPath('data.product.sku', '100053')
            ->assertJsonPath('data.detail_summary.opening_balance', 34000)
            ->assertJsonPath('data.detail_summary.incoming', 0)
            ->assertJsonPath('data.detail_summary.outgoing', 2000)
            ->assertJsonPath('data.detail_summary.closing_balance', 32000)
            ->assertJsonPath('data.mutations.0.document_number', $invoice->invoice_number)
            ->assertJsonPath('data.mutations.0.description', 'Penjualan ke Toko Plastik Berkah')
            ->assertJsonPath('data.mutations.0.party', 'Toko Plastik Berkah')
            ->assertJsonPath('data.mutations.0.outgoing', 2000)
            ->assertJsonPath('data.mutations.0.balance', 32000)
            ->assertJsonPath('data.mutations.1.description', 'Saldo awal per 31/08/2026')
            ->assertJsonPath('data.mutations.1.balance', 34000);
    }

    public function test_cancelled_invoice_mutations_are_hidden_and_restore_rows_have_no_customer(): void
    {
        $customer = Customer::query()->create(['name' => 'Warkop Elbareen']);
        $product = Product::query()->create([
            'sku' => 'MUTASI-CANCEL',
            'name' => 'Produk Mutasi Cancel',
            'track_stock' => true,
            'stock' => 500,
        ]);
        $this->movement($product, StockMovement::TYPE_OPENING_BALANCE, 500, 'SALDO-CANCEL', '2026-08-31 09:00:00');

        $active = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-ACTIVE-MUTASI',
            'issue_date' => '2026-09-20',
            'due_date' => '2026-10-01',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 100000,
        ]);
        $this->movement($product, StockMovement::TYPE_SALE, -100, $active->invoice_number, '2026-09-20 10:00:00');
        $this->movement($product, StockMovement::TYPE_ADJUSTMENT, 100, $active->invoice_number, '2026-09-21 10:00:00');

        $cancelled = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CANCELLED-MUTASI',
            'issue_date' => '2026-09-22',
            'due_date' => '2026-10-01',
            'status' => Invoice::STATUS_CANCELLED,
            'total_amount' => 100000,
        ]);
        $this->movement($product, StockMovement::TYPE_SALE, -50, $cancelled->invoice_number, '2026-09-22 10:00:00');
        $this->movement($product, StockMovement::TYPE_ADJUSTMENT, 50, $cancelled->invoice_number, '2026-09-23 10:00:00');

        $response = $this->getJson(route('api.reports.stock-mutations.index', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'product_id' => $product->id,
        ]))->assertOk();

        $mutations = $response->json('data.mutations');
        $activeRows = collect($mutations)->where('document_number', $active->invoice_number)->values();

        $this->assertCount(2, $activeRows);
        $this->assertSame('Penjualan ke Warkop Elbareen', $activeRows[1]['description']);
        $this->assertSame('Warkop Elbareen', $activeRows[1]['party']);
        $this->assertSame('Penyesuaian stok', $activeRows[0]['description']);
        $this->assertNull($activeRows[0]['party']);
        $this->assertCount(0, collect($mutations)->where('document_number', $cancelled->invoice_number));
    }

    public function test_mutation_page_and_selected_product_exports_are_available(): void
    {
        $product = Product::query()->create([
            'sku' => 'EXPORT-MUTASI',
            'name' => 'Produk Export Mutasi',
            'track_stock' => true,
            'stock' => 10,
        ]);
        $this->movement($product, StockMovement::TYPE_OPENING_BALANCE, 10, 'SALDO-EXPORT', '2026-08-31 09:00:00');

        $this->get(route('reports.stock-mutations.index'))
            ->assertOk()
            ->assertSee('Mutasi per Barang')
            ->assertSee('Barang &amp; Jasa', false);

        $query = [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'product_id' => $product->id,
        ];

        $this->get(route('api.reports.stock-mutations.csv', $query))
            ->assertOk()
            ->assertSee('Nomor Dokumen')
            ->assertSee('Saldo awal per 31/08/2026');
        $this->assertStringStartsWith('%PDF', $this->get(route('api.reports.stock-mutations.pdf', $query))->assertOk()->getContent());
    }

    private function movement(Product $product, string $type, int|float $quantity, string $reference, string $createdAt): void
    {
        $movement = StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => 0,
            'stock_after' => 0,
            'reference_number' => $reference,
        ]);

        $date = CarbonImmutable::parse($createdAt);
        $movement->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
    }
}
