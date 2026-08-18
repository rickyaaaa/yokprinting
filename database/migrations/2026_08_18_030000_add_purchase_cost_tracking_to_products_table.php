<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase A of the purchasing module rollout (see docs discussion): add
     * system-managed reference cost fields to Product without touching the
     * existing `purchase_price` column. Nothing reads these two new columns
     * yet, so this migration is purely additive and safe to run against
     * production data as-is.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('last_purchase_price', 15, 2)->nullable()->after('purchase_price');
            $table->decimal('average_purchase_cost', 15, 2)->nullable()->after('last_purchase_price');
        });

        // Backfill: existing products' only known cost signal is the legacy
        // purchase_price field, so seed both new columns from it. Products
        // with no purchase_price recorded (0 or null) are left null rather
        // than backfilled with a misleading 0 cost.
        DB::table('products')
            ->where('purchase_price', '>', 0)
            ->update([
                'last_purchase_price' => DB::raw('purchase_price'),
                'average_purchase_cost' => DB::raw('purchase_price'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['last_purchase_price', 'average_purchase_cost']);
        });
    }
};
