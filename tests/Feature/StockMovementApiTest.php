<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movements_table_contains_inventory_ledger_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('stock_movements', [
            'id',
            'product_id',
            'type',
            'quantity',
            'stock_before',
            'stock_after',
            'reference_number',
            'notes',
            'user_id',
            'created_at',
        ]));
    }

    public function test_stock_adjustment_endpoint_records_signed_mutation_and_warning(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'sku' => 'CUP-16OV-8G',
            'name' => 'Cup 16 Oz Oval 8gr',
            'stock' => 6,
            'minimum_stock' => 12,
            'track_stock' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.stock-movements.store'), [
                'product_id' => $product->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'quantity' => -2,
                'reference_number' => 'ADJ-001',
                'notes' => 'Koreksi stok rusak.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', StockMovement::TYPE_ADJUSTMENT)
            ->assertJsonPath('data.quantity', -2)
            ->assertJsonPath('data.stock_before', 6)
            ->assertJsonPath('data.stock_after', 4)
            ->assertJsonPath('data.warning.low_stock', true);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'quantity' => -2,
            'stock_before' => 6,
            'stock_after' => 4,
            'reference_number' => 'ADJ-001',
            'user_id' => $user->id,
        ]);
        $this->assertSame('4.0000', $product->refresh()->stock);
    }

    public function test_invoice_draft_records_sale_stock_movement_and_returns_low_stock_alert(): void
    {
        $product = Product::query()->create([
            'sku' => 'CUP-16OV-8G',
            'name' => 'Cup 16 Oz Oval 8gr',
            'purchase_price' => 500,
            'stock' => 1200,
            'minimum_stock' => 500,
            'minimum_order_qty' => 1000,
            'package_conversion' => 1000,
            'track_stock' => true,
        ]);

        $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => 1,
            'issue_date' => '2026-07-27',
            'due_date' => '2026-08-10',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1000,
                'price' => 850,
                'jenis_cetak' => '1 warna',
            ]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])
            ->assertCreated()
            ->assertJsonPath('data.stock_alerts.0.product_id', $product->id)
            ->assertJsonPath('data.stock_alerts.0.stock', 200)
            ->assertJsonPath('data.stock_alerts.0.minimum_stock', 500);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_SALE,
            'quantity' => -1000,
            'stock_before' => 1200,
            'stock_after' => 200,
            'reference_number' => 'INV-2026-0001',
        ]);
        $this->assertSame('200.0000', $product->refresh()->stock);
    }

    public function test_stock_movement_report_calculates_opening_incoming_outgoing_adjustment_and_closing(): void
    {
        $product = Product::query()->create([
            'sku' => 'CUP-12DT-7G',
            'name' => 'Cup 12 Oz Datar 7gr',
            'stock' => 580,
            'minimum_stock' => 100,
            'track_stock' => true,
        ]);

        $this->movement($product, StockMovement::TYPE_OPENING_BALANCE, 500, '2026-07-01 09:00:00');
        $this->movement($product, StockMovement::TYPE_PURCHASE, 300, '2026-07-10 09:00:00');
        $this->movement($product, StockMovement::TYPE_SALE, -200, '2026-07-12 09:00:00');
        $this->movement($product, StockMovement::TYPE_ADJUSTMENT, -20, '2026-07-15 09:00:00');

        $this->getJson(route('api.reports.stock-movements.index', [
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-20',
        ]))
            ->assertOk()
            ->assertJsonPath('data.summary.products_count', 1)
            ->assertJsonPath('data.summary.total_incoming', 300)
            ->assertJsonPath('data.summary.total_outgoing', 200)
            ->assertJsonPath('data.summary.total_adjustments', -20)
            ->assertJsonPath('data.products.0.opening_balance', 500)
            ->assertJsonPath('data.products.0.incoming_quantity', 300)
            ->assertJsonPath('data.products.0.outgoing_quantity', 200)
            ->assertJsonPath('data.products.0.adjustments', -20)
            ->assertJsonPath('data.products.0.closing_balance', 580);
    }

    private function movement(Product $product, string $type, int|float $quantity, string $createdAt): void
    {
        $createdAt = CarbonImmutable::parse($createdAt);

        $movement = StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => 0,
            'stock_after' => 0,
        ]);

        $movement->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
