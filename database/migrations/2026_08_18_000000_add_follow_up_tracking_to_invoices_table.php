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
        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestamp('last_follow_up_at')->nullable()->after('viewed_at');
            $table->string('last_follow_up_note', 500)->nullable()->after('last_follow_up_at');
            $table->foreignId('last_follow_up_by')
                ->nullable()
                ->after('last_follow_up_note')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_follow_up_by');
            $table->dropColumn(['last_follow_up_at', 'last_follow_up_note']);
        });
    }
};
