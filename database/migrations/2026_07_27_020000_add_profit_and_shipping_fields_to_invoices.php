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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('shipping_type', 40)->default('none')->after('total_amount')->index();
            $table->decimal('shipping_cost', 19, 2)->default(0)->after('shipping_type');
            $table->string('order_process_status', 40)->default('draft')->after('shipping_cost')->index();
            $table->decimal('total_hpp', 19, 2)->default(0)->after('order_process_status');
            $table->decimal('gross_profit', 19, 2)->default(0)->after('total_hpp');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('jenis_cetak', 30)->nullable()->after('screen_printing_color');
            $table->decimal('purchase_cost_snapshot', 19, 2)->default(0)->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['jenis_cetak', 'purchase_cost_snapshot']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_type',
                'shipping_cost',
                'order_process_status',
                'total_hpp',
                'gross_profit',
            ]);
        });
    }
};
