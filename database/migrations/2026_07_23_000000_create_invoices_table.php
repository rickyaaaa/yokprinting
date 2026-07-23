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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->date('issue_date');
            $table->date('due_date')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->char('currency', 3)->default('IDR');
            $table->decimal('subtotal', 19, 2)->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 19, 2)->default(0);
            $table->decimal('discount_amount', 19, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 19, 2)->default(0);
            $table->decimal('total_amount', 19, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('template', 50)->default('default');
            $table->string('theme_color', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'issue_date']);
            $table->index(['status', 'due_date']);
            $table->index(['payment_status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
