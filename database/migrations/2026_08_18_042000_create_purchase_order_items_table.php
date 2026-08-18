<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot', 100);
            $table->string('unit_snapshot', 30);
            $table->decimal('quantity', 15, 4);
            // Locked at PO creation - never rewritten once the PO leaves draft.
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            // Tracked by the future Goods Receipt phase; unused until then.
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['purchase_order_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
