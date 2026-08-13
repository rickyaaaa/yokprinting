<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('cash_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('transaction_number', 32)->unique();
            $table->date('transaction_date')->index();
            $table->string('type', 16)->index();
            $table->string('category', 100)->index();
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->string('source_type', 20)->index();
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('status', 16)->default('posted')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'cash_bank_source_unique');
            $table->index(['bank_account_id', 'transaction_date', 'id'], 'cash_bank_running_balance_idx');
            $table->index(['bank_account_id', 'status', 'type'], 'cash_bank_summary_idx');
        });

        DB::table('bank_accounts')->insert([
            'name' => 'Rekening Utama',
            'bank_name' => 'BCA',
            'account_number' => null,
            'opening_balance' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_bank_transactions');
        Schema::dropIfExists('bank_accounts');
    }
};
