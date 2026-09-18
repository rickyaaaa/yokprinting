<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the old 50% default from existing invoices and new records.
     * Other explicitly configured thresholds remain unchanged.
     */
    public function up(): void
    {
        DB::table('invoices')
            ->where('dp_required_percent', 50)
            ->update(['dp_required_percent' => 0]);

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('dp_required_percent', 5, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('dp_required_percent', 5, 2)->default(50)->change();
        });
    }
};
