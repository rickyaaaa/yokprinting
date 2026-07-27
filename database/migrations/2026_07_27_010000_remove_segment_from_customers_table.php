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
        if (Schema::hasColumn('customers', 'segment')) {
            DB::statement('drop index if exists customers_segment_index');
            DB::statement('drop index if exists customers_segment_status_index');
        }

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'segment')) {
                $table->dropColumn('segment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'segment')) {
                $table->string('segment', 50)->nullable()->after('name')->index();
                $table->index(['segment', 'status']);
            }
        });
    }
};
