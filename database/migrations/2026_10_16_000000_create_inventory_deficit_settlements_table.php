<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_deficit_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_batch_id')->constrained('inventory_batches')->restrictOnDelete();
            $table->foreignId('invoice_item_cost_layer_id')->nullable()->constrained('invoice_item_cost_layers')->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('compensation_batch_id')->nullable()->constrained('inventory_batches')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            $table->string('source_type');
            $table->string('source_reference')->nullable();
            $table->date('source_date');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['inventory_batch_id', 'reversed_at'], 'deficit_settlements_batch_reversed_idx');
            $table->index(['invoice_item_cost_layer_id', 'reversed_at'], 'deficit_settlements_layer_reversed_idx');
        });

        // Older releases updated only inventory_batches.qty_remaining when an
        // incoming movement paid down a deficit. There is no surviving source
        // cost for those historical closures, so backfill them explicitly as
        // legacy estimates using each sale layer's immutable HPP snapshot.
        // This keeps old invoices cancellable without pretending the inferred
        // provenance came from a known goods receipt.
        DB::table('inventory_batches')
            ->where('source_type', 'deficit')
            ->orderBy('id')
            ->get()
            ->each(function (object $batch): void {
                $layers = DB::table('invoice_item_cost_layers')
                    ->where('inventory_batch_id', $batch->id)
                    ->whereNull('reversed_at')
                    ->orderBy('id')
                    ->get();

                $activeQuantity = round((float) $layers->sum('qty_consumed'), 4);
                $openDeficit = max(0, round(abs(min(0, (float) $batch->qty_remaining)), 4));
                $alreadySettled = max(0, round($activeQuantity - $openDeficit, 4));

                foreach ($layers as $layer) {
                    if ($alreadySettled <= 0) {
                        break;
                    }

                    $quantity = min($alreadySettled, (float) $layer->qty_consumed);
                    DB::table('inventory_deficit_settlements')->insert([
                        'inventory_batch_id' => $batch->id,
                        'invoice_item_cost_layer_id' => $layer->id,
                        'goods_receipt_item_id' => null,
                        'compensation_batch_id' => null,
                        'quantity' => $quantity,
                        'unit_cost' => $layer->unit_cost,
                        'source_type' => 'legacy_backfill',
                        'source_reference' => 'FIFO-LEGACY-SETTLEMENT',
                        'source_date' => $batch->purchase_date,
                        'reversed_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $alreadySettled = round($alreadySettled - $quantity, 4);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_deficit_settlements');
    }
};
