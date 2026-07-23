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
            $table->index(['issue_date', 'status'], 'invoices_issue_date_status_index');
            $table->index(['issue_date', 'payment_status', 'due_date'], 'invoices_issue_payment_due_index');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->index(['product_id', 'invoice_id'], 'invoice_items_product_invoice_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['invoice_id', 'status'], 'payments_invoice_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_invoice_status_index');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropIndex('invoice_items_product_invoice_index');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_issue_payment_due_index');
            $table->dropIndex('invoices_issue_date_status_index');
        });
    }
};
