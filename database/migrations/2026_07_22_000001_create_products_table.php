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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('unit', 30)->default('item');
            $table->decimal('price', 19, 2)->default(0);
            $table->decimal('stock', 15, 4)->nullable();
            $table->decimal('minimum_stock', 15, 4)->nullable();
            $table->boolean('track_stock')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
