<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money paid out against a purchase order.
 *
 * This is what a purchase order was missing: it could be approved and received,
 * but paying it left no record, so the cash going out had to be typed into
 * Pengeluaran by hand - as an operational expense, which it is not. A payment
 * here is the source of the Kas & Bank outflow, and the only thing allowed to
 * create one.
 *
 * Shaped after `payments` (the sales side) on purpose: same status vocabulary,
 * same verified_at/cancelled_at pairing, so the two read alike.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete, not cascade: a paid purchase order is a
            // financial record and must not be removable out from under its
            // payments.
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->date('payment_date')->index();
            $table->string('method', 30)->index();
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('pending')->index();
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['purchase_order_id', 'payment_date']);
            $table->index(['status', 'payment_date']);
        });

        // Same row-locked counter the PO and invoice numbers use, rather than
        // MAX(payment_number)+1, which hands two concurrent requests the same
        // number.
        Schema::create('purchase_payment_number_sequences', function (Blueprint $table) {
            $table->string('period', 6)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Payment state gets its own column instead of reusing
        // purchase_orders.status. That column already tracks receiving -
        // partially_received, fully_received - and writing "paid" into it would
        // erase how much of the order had actually arrived. Invoices already
        // separate the two the same way.
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('payment_status', 30)->default('unpaid')->after('status')->index();
            $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['payment_status', 'paid_amount']);
        });

        Schema::dropIfExists('purchase_payment_number_sequences');
        Schema::dropIfExists('purchase_payments');
    }
};
