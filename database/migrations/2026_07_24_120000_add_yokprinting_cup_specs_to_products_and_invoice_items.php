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
        Schema::table('products', function (Blueprint $table) {
            $table->string('cup_size', 10)->nullable()->after('category');
            $table->string('cup_model', 20)->nullable()->after('cup_size');
            $table->string('grammage', 10)->nullable()->after('cup_model');
            $table->string('screen_printing_color', 50)->nullable()->after('grammage');
            $table->unsignedTinyInteger('sides')->nullable()->after('screen_printing_color');
            $table->unsignedInteger('moq_quantity')->default(1000)->after('minimum_stock');
            $table->unsignedInteger('order_increment')->default(500)->after('moq_quantity');
            $table->string('packaging_unit', 20)->default('pcs')->after('order_increment');

            $table->index(['cup_size', 'cup_model']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('cup_size', 10)->nullable()->after('sku');
            $table->string('cup_model', 20)->nullable()->after('cup_size');
            $table->string('grammage', 10)->nullable()->after('cup_model');
            $table->string('screen_printing_color', 50)->nullable()->after('grammage');
            $table->unsignedTinyInteger('sides')->nullable()->after('screen_printing_color');
            $table->unsignedInteger('moq_quantity')->nullable()->after('sides');
            $table->unsignedInteger('order_increment')->nullable()->after('moq_quantity');
            $table->string('packaging_unit', 20)->nullable()->after('order_increment');

            $table->index(['cup_size', 'cup_model']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['cup_size', 'cup_model']);
            $table->dropColumn([
                'cup_size',
                'cup_model',
                'grammage',
                'screen_printing_color',
                'sides',
                'moq_quantity',
                'order_increment',
                'packaging_unit',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['cup_size', 'cup_model']);
            $table->dropColumn([
                'cup_size',
                'cup_model',
                'grammage',
                'screen_printing_color',
                'sides',
                'moq_quantity',
                'order_increment',
                'packaging_unit',
            ]);
        });
    }
};
