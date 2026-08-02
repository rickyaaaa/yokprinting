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
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('version')->default(1)->after('created_by');
        });

        Schema::create('expense_proof_cleanup_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id')->nullable()->index();
            $table->string('disk', 40)->default('expense_proofs');
            $table->string('path');
            $table->string('reason', 80);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['disk', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_proof_cleanup_tasks');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
