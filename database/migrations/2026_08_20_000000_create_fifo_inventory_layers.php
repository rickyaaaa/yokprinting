<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnUpdate();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->date('purchase_date');
            $table->decimal('qty_received', 18, 4);
            $table->decimal('qty_remaining', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            $table->string('source_type')->default('goods_receipt');
            $table->string('source_reference')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'purchase_date', 'id']);
            $table->index(['product_id', 'qty_remaining']);
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->decimal('hpp_total', 18, 2)->default(0)->after('purchase_cost_snapshot');
            $table->decimal('unit_hpp', 18, 2)->default(0)->after('hpp_total');
        });

        Schema::create('invoice_item_cost_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_consumed', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            $table->decimal('total_cost', 18, 2);
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_item_id', 'reversed_at']);
        });

        // Existing invoices remain historical records. Their legacy HPP is
        // copied into the explicit fields; no old transaction is recomputed.
        DB::table('invoice_items')->orderBy('id')->chunkById(500, function ($items): void {
            foreach ($items as $item) {
                $hpp = round((float) $item->quantity * (float) $item->purchase_cost_snapshot, 2);
                DB::table('invoice_items')->where('id', $item->id)->update([
                    'hpp_total' => $hpp,
                    'unit_hpp' => (float) $item->quantity > 0
                        ? round($hpp / (float) $item->quantity, 2)
                        : 0,
                ]);
            }
        });

        // Safe migration fallback for stock that predates FIFO. This is an
        // opening layer only; historical invoices are intentionally untouched.
        DB::table('products')
            ->where('track_stock', true)
            ->where('stock', '>', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($product): void {
                $unitCost = (float) ($product->average_purchase_cost
                    ?? $product->last_purchase_price
                    ?? $product->purchase_price
                    ?? 0);

                DB::table('inventory_batches')->insert([
                    'product_id' => $product->id,
                    'goods_receipt_item_id' => null,
                    'purchase_date' => now()->toDateString(),
                    'qty_received' => $product->stock,
                    'qty_remaining' => $product->stock,
                    'unit_cost' => $unitCost,
                    'source_type' => 'opening_balance',
                    'source_reference' => 'FIFO-MIGRATION',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_cost_layers');
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['hpp_total', 'unit_hpp']);
        });
        Schema::dropIfExists('inventory_batches');
    }
};
