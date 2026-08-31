<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client-confirmed business rule: an invoice counts as a real business
 * transaction (revenue / HPP / gross profit / customer sales) the moment it
 * exists with items - status=sent is NOT required. Only an explicit
 * cancellation removes it. This scope is the revenue gate that replaces
 * scopeFinalized() across the sales/profit reports (migrated per-report in
 * a later phase). See Invoice::scopeBusinessTransaction().
 */
class BusinessTransactionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_invoice_counts_as_a_business_transaction(): void
    {
        $invoice = $this->invoice('INV-BT-DRAFT', Invoice::STATUS_DRAFT);

        $this->assertTrue(
            Invoice::query()->businessTransaction()->whereKey($invoice->getKey())->exists(),
        );
    }

    public function test_sent_invoice_counts_as_a_business_transaction(): void
    {
        $invoice = $this->invoice('INV-BT-SENT', Invoice::STATUS_SENT);

        $this->assertTrue(
            Invoice::query()->businessTransaction()->whereKey($invoice->getKey())->exists(),
        );
    }

    public function test_cancelled_invoice_is_excluded(): void
    {
        $invoice = $this->invoice('INV-BT-CANCELLED', Invoice::STATUS_CANCELLED);

        $this->assertFalse(
            Invoice::query()->businessTransaction()->whereKey($invoice->getKey())->exists(),
        );
    }

    public function test_scope_counts_draft_and_sent_but_not_cancelled(): void
    {
        $this->invoice('INV-BT-1', Invoice::STATUS_DRAFT);
        $this->invoice('INV-BT-2', Invoice::STATUS_SENT);
        $this->invoice('INV-BT-3', Invoice::STATUS_DRAFT);
        $this->invoice('INV-BT-4', Invoice::STATUS_CANCELLED);

        $this->assertSame(3, Invoice::query()->businessTransaction()->count());
    }

    public function test_scope_resolves_in_a_joined_query_without_ambiguous_column_error(): void
    {
        $this->invoice('INV-BT-JOIN', Invoice::STATUS_DRAFT);

        // payments also has a `status` column - an unqualified `status`
        // predicate would throw "ambiguous column" here.
        $count = Invoice::query()
            ->businessTransaction()
            ->leftJoin('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_soft_deleted_invoice_is_excluded_by_the_default_scope(): void
    {
        $invoice = $this->invoice('INV-BT-TRASHED', Invoice::STATUS_DRAFT);
        $invoice->delete();

        $this->assertFalse(
            Invoice::query()->businessTransaction()->whereKey($invoice->getKey())->exists(),
        );
    }

    private function invoice(string $number, string $status): Invoice
    {
        $customer = Customer::query()->create(['name' => 'PT Business Transaction '.random_int(1000, 9999)]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => $status,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => 100000,
        ]);
    }
}
