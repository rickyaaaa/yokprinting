<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\InventoryDeficitSettlement;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\Inventory\FifoInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDeficitSettlementMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_closed_deficit_is_backfilled_and_can_be_reversed_safely(): void
    {
        $customer = Customer::query()->create(['name' => 'Legacy FIFO Customer']);
        $product = Product::query()->create([
            'name' => 'Legacy FIFO Product',
            'sku' => 'LEGACY-FIFO-BACKFILL',
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $product->forceFill(['average_purchase_cost' => 500])->save();
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-LEGACY-DEFICIT',
            'issue_date' => '2026-10-15',
            'due_date' => '2026-10-29',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 10000,
        ]);
        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 10,
            'unit_price' => 1000,
            'subtotal' => 10000,
            'total_amount' => 10000,
        ]);
        $service = app(FifoInventoryService::class);
        $service->consume($invoice, $item);

        // Reproduce the pre-migration representation: six units had arrived
        // and paid down the deficit, but only the aggregate batch balance was
        // retained. No settlement/provenance row existed yet.
        InventoryBatch::query()->where('source_type', 'deficit')->firstOrFail()
            ->forceFill(['qty_remaining' => -4])->save();
        $product->forceFill(['stock' => -4])->save();
        $this->assertDatabaseCount('inventory_deficit_settlements', 0);

        $migration = require database_path('migrations/2026_10_16_000000_create_inventory_deficit_settlements_table.php');
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('inventory_deficit_settlements', [
            'quantity' => 6,
            'unit_cost' => 500,
            'source_type' => 'legacy_backfill',
            'source_reference' => 'FIFO-LEGACY-SETTLEMENT',
        ]);

        $service->restoreInvoice($invoice->refresh());

        $this->assertSame('6.0000', $product->refresh()->stock);
        $this->assertSame(6.0, (float) InventoryBatch::query()->where('product_id', $product->id)->sum('qty_remaining'));
        $this->assertNotNull(InventoryDeficitSettlement::query()->firstOrFail()->reversed_at);
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'source_type' => 'deficit_compensation',
            'source_reference' => 'FIFO-LEGACY-SETTLEMENT',
            'qty_remaining' => 6,
        ]);
    }
}
