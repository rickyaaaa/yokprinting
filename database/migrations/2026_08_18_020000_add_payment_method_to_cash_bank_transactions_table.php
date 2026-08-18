<?php

use App\Models\CashBankTransaction;
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
        Schema::table('cash_bank_transactions', function (Blueprint $table): void {
            $table->string('payment_method', 20)
                ->default(CashBankTransaction::PAYMENT_METHOD_TRANSFER)
                ->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_bank_transactions', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
    }
};
