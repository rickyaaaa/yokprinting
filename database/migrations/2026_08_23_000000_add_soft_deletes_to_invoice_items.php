<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editing an already-issued invoice replaces its line items wholesale
 * (UpdateInvoiceDraft: restore FIFO -> delete old items -> create new
 * ones). A hard delete cascades to invoice_item_cost_layers
 * (cascadeOnDelete on invoice_item_id) and would destroy the very
 * `reversed_at` audit trail FifoInventoryService::restoreInvoice() just
 * wrote a moment earlier. Soft-deleting the item instead keeps its row
 * (and therefore its now-reversed cost layers) intact for history, while
 * every normal Eloquent query - the invoice's items relation, HPP sums,
 * reports - automatically excludes it going forward, exactly like the
 * `active cost state only` requirement for the edit flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
