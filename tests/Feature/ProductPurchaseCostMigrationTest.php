<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductPurchaseCostMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_reference_cost_from_existing_purchase_price(): void
    {
        $migration = require database_path('migrations/2026_08_18_030000_add_purchase_cost_tracking_to_products_table.php');
        $migration->down();
        $now = now();

        DB::table('products')->insert([
            [
                'sku' => 'CUP-PP-16-D', 'name' => 'PP Cup 16oz Datar',
                'purchase_price' => 700, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'sku' => 'CUP-PP-14-D', 'name' => 'PP Cup 14oz Datar',
                'purchase_price' => 0, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumn('products', 'last_purchase_price'));
        $this->assertTrue(Schema::hasColumn('products', 'average_purchase_cost'));

        $withCost = DB::table('products')->where('sku', 'CUP-PP-16-D')->first();
        $this->assertEquals(700, $withCost->last_purchase_price);
        $this->assertEquals(700, $withCost->average_purchase_cost);

        $withoutCost = DB::table('products')->where('sku', 'CUP-PP-14-D')->first();
        $this->assertNull($withoutCost->last_purchase_price);
        $this->assertNull($withoutCost->average_purchase_cost);
    }

    public function test_rollback_drops_the_new_columns_without_touching_purchase_price(): void
    {
        $migration = require database_path('migrations/2026_08_18_030000_add_purchase_cost_tracking_to_products_table.php');

        $migration->down();

        $this->assertFalse(Schema::hasColumn('products', 'last_purchase_price'));
        $this->assertFalse(Schema::hasColumn('products', 'average_purchase_cost'));
        $this->assertTrue(Schema::hasColumn('products', 'purchase_price'));

        $migration->up();
    }
}
