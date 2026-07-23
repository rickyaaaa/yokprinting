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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->index();
            $table->string('product_name');
            $table->string('sku', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 4)->default(1);
            $table->string('unit', 30)->default('item');
            $table->decimal('unit_price', 19, 2)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 19, 2)->default(0);
            $table->decimal('discount_amount', 19, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 19, 2)->default(0);
            $table->decimal('subtotal', 19, 2)->default(0);
            $table->decimal('total_amount', 19, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
