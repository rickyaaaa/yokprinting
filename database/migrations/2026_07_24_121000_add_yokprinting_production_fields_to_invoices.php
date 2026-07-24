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
            $table->string('production_status', 30)->default('draft')->index()->after('payment_status');
            $table->decimal('dp_required_percent', 5, 2)->default(50)->after('total_amount');
            $table->text('design_notes')->nullable()->after('terms');
            $table->string('mockup_url')->nullable()->after('design_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['production_status']);
            $table->dropColumn([
                'production_status',
                'dp_required_percent',
                'design_notes',
                'mockup_url',
            ]);
        });
    }
};
