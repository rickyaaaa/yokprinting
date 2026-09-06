<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Piutang lists the newest invoice first, by client decision.
 *
 * This replaces the earlier deliberate "nearest due date first" default
 * (most actionable for collections). That view is still one click away on
 * the Jatuh tempo column; only the default changed. The id tiebreak stays,
 * so invoices sharing a date never shuffle between requests.
 */
class ReceivablePageSortingTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_receivables_default_to_newest_invoice_date_first(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Piutang Test']);

        // Deliberately mismatched against due date: the oldest invoice here
        // has the nearest due date, so an accidental fallback to the old
        // due-date default would flip this order and fail.
        $oldest = $this->invoice($customer, 'INV-DUE-A', '2026-08-10', '2026-09-01');
        $middle = $this->invoice($customer, 'INV-DUE-B', '2026-08-11', '2026-09-20');
        $newest = $this->invoice($customer, 'INV-DUE-C', '2026-08-12', '2026-09-10');

        $this->get(route('payments.receivables.index'))
            ->assertOk()
            ->assertSeeInOrder([
                $newest->invoice_number,
                $middle->invoice_number,
                $oldest->invoice_number,
            ]);
    }

    public function test_invoices_sharing_an_issue_date_fall_back_to_newest_id(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Piutang Sama Tanggal']);

        $first = $this->invoice($customer, 'INV-SAME-A', '2026-08-10', '2026-09-05');
        $second = $this->invoice($customer, 'INV-SAME-B', '2026-08-10', '2026-09-05');

        $this->assertTrue($second->id > $first->id);

        $this->get(route('payments.receivables.index'))
            ->assertOk()
            ->assertSeeInOrder([$second->invoice_number, $first->invoice_number]);
    }

    public function test_rows_carry_the_sortable_date_and_id_the_table_needs(): void
    {
        // 'issued'/'due' are formatted for display and would sort as text,
        // so the table sorts on these numeric companions instead.
        $customer = Customer::query()->create(['name' => 'PT Piutang Field']);
        $invoice = $this->invoice($customer, 'INV-FIELD-A', '2026-08-10', '2026-09-05');

        $this->get(route('payments.receivables.index'))
            ->assertOk()
            ->assertSee('issuedSort', false)
            ->assertSee('20260810', false)
            ->assertSee((string) $invoice->getKey(), false);
    }

    private function invoice(Customer $customer, string $number, string $issued, string $due): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => $issued,
            'due_date' => $due,
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 100000,
        ]);
    }
}
