<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Piutang intentionally stays sorted by nearest due date first (most
 * actionable for collections) rather than being flipped to a "recency"
 * order - confirmed deliberately, see ReceivablePageController. This only
 * locks in the deterministic id tiebreak for same-due-date invoices.
 */
class ReceivablePageSortingTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_receivables_default_to_nearest_due_date_with_deterministic_id_tiebreak(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Piutang Test']);

        $invoiceA = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DUE-A',
            'issue_date' => '2026-08-10',
            'due_date' => '2026-09-05',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 100000,
        ]);
        $invoiceB = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DUE-B',
            'issue_date' => '2026-08-11',
            'due_date' => '2026-09-05', // same due date as A, created after -> higher id
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 200000,
        ]);
        $invoiceC = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DUE-C',
            'issue_date' => '2026-08-12',
            'due_date' => '2026-09-01', // nearer due date - must come first
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 300000,
        ]);

        $this->assertTrue($invoiceB->id > $invoiceA->id);

        $this->get(route('payments.receivables.index'))
            ->assertOk()
            ->assertSeeInOrder([
                $invoiceC->invoice_number,
                $invoiceA->invoice_number,
                $invoiceB->invoice_number,
            ]);
    }
}
