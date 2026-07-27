<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->default(0)->after('description');
            $table->string('brand')->nullable()->after('purchase_price');
            $table->string('short_description', 500)->nullable()->after('brand');
            $table->unsignedInteger('minimum_order_qty')->default(500)->after('short_description');
            $table->unsignedInteger('package_conversion')->default(500)->after('minimum_order_qty');
            $table->decimal('length_cm', 10, 2)->nullable()->after('package_conversion');
            $table->decimal('width_cm', 10, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 10, 2)->nullable()->after('width_cm');
            $table->decimal('weight_gram', 12, 2)->nullable()->after('height_cm');

            if (Schema::hasColumn('products', 'price')) {
                $table->dropColumn('price');
            }

            $table->index(['brand', 'status']);
            $table->index(['minimum_stock', 'status']);
        });

        DB::table('products')->update(['unit' => 'Pcs']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 19, 2)->default(0)->after('unit');

            $table->dropIndex(['brand', 'status']);
            $table->dropIndex(['minimum_stock', 'status']);
            $table->dropColumn([
                'purchase_price',
                'brand',
                'short_description',
                'minimum_order_qty',
                'package_conversion',
                'length_cm',
                'width_cm',
                'height_cm',
                'weight_gram',
            ]);
        });
    }
};
