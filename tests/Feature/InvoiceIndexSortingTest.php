<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Acceptance scenario from the client's revision request:
 *
 *   2026-08-21               Invoice C
 *   2026-08-22  Invoice A
 *   2026-08-22  Invoice B  (created after A, higher id)
 *
 * Default must be newest-first, and when the business date ties, the higher
 * id (i.e. the more recently created row) must win the tiebreak - so the
 * default render order is B, A, C.
 */
class InvoiceIndexSortingTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_index_defaults_to_newest_first_with_deterministic_id_tiebreak(): void
    {
        [$invoiceA, $invoiceB, $invoiceC] = $this->seedThreeInvoices();

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSeeInOrder([
                $invoiceB->invoice_number,
                $invoiceA->invoice_number,
                $invoiceC->invoice_number,
            ]);
    }

    public function test_invoice_index_sort_oldest_reverses_the_order(): void
    {
        [$invoiceA, $invoiceB, $invoiceC] = $this->seedThreeInvoices();

        $this->get(route('invoices.index', ['sort' => 'oldest']))
            ->assertOk()
            ->assertSeeInOrder([
                $invoiceC->invoice_number,
                $invoiceA->invoice_number,
                $invoiceB->invoice_number,
            ]);
    }

    public function test_invoice_index_sort_query_param_is_whitelisted(): void
    {
        [$invoiceA, $invoiceB, $invoiceC] = $this->seedThreeInvoices();

        // Anything other than the literal "oldest" must fall back to the
        // "latest" default rather than being fed into orderBy().
        $this->get(route('invoices.index', ['sort' => "id'); DROP TABLE invoices;--"]))
            ->assertOk()
            ->assertSeeInOrder([
                $invoiceB->invoice_number,
                $invoiceA->invoice_number,
                $invoiceC->invoice_number,
            ]);
    }

    /** @return array{0: Invoice, 1: Invoice, 2: Invoice} */
    private function seedThreeInvoices(): array
    {
        $customer = Customer::query()->create(['name' => 'PT Sorting Test']);

        $invoiceA = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SORT-A',
            'issue_date' => '2026-08-22',
            'due_date' => '2026-09-05',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 100000,
        ]);
        $invoiceB = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SORT-B',
            'issue_date' => '2026-08-22',
            'due_date' => '2026-09-05',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 200000,
        ]);
        $invoiceC = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SORT-C',
            'issue_date' => '2026-08-21',
            'due_date' => '2026-09-04',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 300000,
        ]);

        $this->assertTrue($invoiceB->id > $invoiceA->id, 'invoice B must have a higher id than invoice A for the tiebreak to be meaningful');

        return [$invoiceA, $invoiceB, $invoiceC];
    }
}
