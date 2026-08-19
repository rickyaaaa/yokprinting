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
        Schema::create('supplier_price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Supplier-quoted price. Every new quote is a new row - history is
            // immutable, never overwritten, see SaveSupplierPriceList.
            $table->decimal('price', 15, 2);
            $table->date('valid_from');
            // Null means "until further notice" (no known expiry yet).
            $table->date('valid_until')->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'product_id', 'valid_from']);
            $table->index(['product_id', 'valid_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_price_lists');
    }
};
