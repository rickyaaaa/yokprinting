<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invoice numbers reset every month (INV-YYYY-MM-NNNN), so the sequence
     * is tracked per (year, month). The legacy yearly invoice_number_sequences
     * table is left untouched so existing data stays intact.
     */
    public function up(): void
    {
        Schema::create('invoice_monthly_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_monthly_number_sequences');
    }
};
