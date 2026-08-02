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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date')->index();
            $table->string('category', 40)->index();
            $table->string('subcategory', 40)->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->string('recipient');
            $table->string('payment_method', 100);
            $table->string('proof_path');
            $table->string('proof_original_name');
            $table->string('proof_mime_type', 100);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'expense_date']);
            $table->index(['created_by', 'expense_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
