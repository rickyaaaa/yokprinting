<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * PHASE 1 test gate - locks in the single consistent rule for
 * Invoice.payment_status everywhere it's recalculated:
 *
 *   verified_paid = SUM(payment.amount WHERE status = verified)
 *   verified_paid <= 0                          -> unpaid
 *   0 < verified_paid < invoice.total_amount     -> partial
 *   verified_paid >= invoice.total_amount        -> paid
 *
 * Cases 2 (partial DP), 3 (full payment -> paid), 6 (edit a sent invoice
 * with an existing partial payment stays partial), and 7 (editing the
 * total down to exactly the verified amount -> paid) are already covered
 * by StoreInvoicePaymentApiTest and EditInvoiceAfterIssuanceTest - not
 * duplicated here, only referenced in the phase report.
 */
class InvoicePaymentStatusRecalculationTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_with_no_verified_payment_stays_unpaid(): void
    {
        $invoice = $this->createSentInvoice(totalAmount: 300000);

        $this->assertSame(Invoice::PAYMENT_UNPAID, $invoice->payment_status);
        $this->assertSame(0.0, $invoice->verifiedPaidAmount());
        $this->assertSame(300000.0, $invoice->remainingAmount());
    }

    public function test_pending_payment_does_not_change_payment_status(): void
    {
        $invoice = $this->createSentInvoice(totalAmount: 300000);

        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => '2026-08-24',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 150000,
            'status' => Payment::STATUS_PENDING,
        ])->assertCreated();

        $invoice->refresh();
        // A pending payment is not "money received" yet - the rule only
        // counts status=verified, so this must not move the needle.
        $this->assertSame(Invoice::PAYMENT_UNPAID, $invoice->payment_status);
        $this->assertSame(0.0, $invoice->verifiedPaidAmount());
        $this->assertSame(300000.0, $invoice->remainingAmount());
    }

    public function test_rejected_payment_does_not_count_toward_verified_paid(): void
    {
        $invoice = $this->createSentInvoice(totalAmount: 300000);
        $invoice->payments()->create([
            'payment_number' => 'PAY-REJECTED-0001',
            'payment_date' => '2026-08-24',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => 150000,
            'status' => Payment::STATUS_REJECTED,
        ]);

        // Nothing recalculates payment_status just from a payment row
        // existing - only the verified-payment sum ever should. This locks
        // in the invariant so a future "reject a pending payment" endpoint
        // can rely on payment_status never needing a separate fix.
        $this->assertSame(0.0, $invoice->verifiedPaidAmount());
        $this->assertSame(Invoice::PAYMENT_UNPAID, $invoice->refresh()->payment_status);
    }

    public function test_verified_dp_moves_status_to_partial_and_a_second_verified_payment_to_paid(): void
    {
        // Restates cases 2 and 3 end to end in one place for the phase
        // gate, in addition to the existing StoreInvoicePaymentApiTest coverage.
        $invoice = $this->createSentInvoice(totalAmount: 300000);

        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => '2026-08-24',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 150000,
        ])->assertCreated()->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PARTIAL);

        $invoice->refresh();
        $this->assertSame(Invoice::PAYMENT_PARTIAL, $invoice->payment_status);
        $this->assertSame(150000.0, $invoice->verifiedPaidAmount());
        $this->assertSame(150000.0, $invoice->remainingAmount());

        $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => '2026-08-24',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 150000,
        ])->assertCreated()->assertJsonPath('data.invoice_payment_status', Invoice::PAYMENT_PAID);

        $invoice->refresh();
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertSame(300000.0, $invoice->verifiedPaidAmount());
        $this->assertSame(0.0, $invoice->remainingAmount());
    }

    public function test_invoice_index_page_exposes_the_raw_payment_status_field(): void
    {
        // Case 8: the list page's data must carry the real payment_status
        // value regardless of what the display badge currently shows -
        // the badge itself is fixed in the next phase.
        $invoice = $this->createSentInvoice(totalAmount: 300000);
        $invoice->forceFill(['payment_status' => Invoice::PAYMENT_PARTIAL])->save();

        $response = $this->get(route('invoices.index'))->assertOk();
        $content = $response->getContent();

        $escapedQuote = chr(92).'u0022';
        $this->assertStringContainsString(
            "{$escapedQuote}payment_status{$escapedQuote}:{$escapedQuote}".Invoice::PAYMENT_PARTIAL."{$escapedQuote}",
            $content,
        );
        $this->assertStringContainsString(
            "{$escapedQuote}invoice_status{$escapedQuote}:{$escapedQuote}".Invoice::STATUS_SENT."{$escapedQuote}",
            $content,
        );
    }

    private function createSentInvoice(int $totalAmount): Invoice
    {
        $customer = Customer::query()->create(['name' => 'PT Payment Status Test']);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-PAYSTATUS-'.random_int(1000, 9999),
            'issue_date' => '2026-08-24',
            'due_date' => '2026-09-07',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }
}
