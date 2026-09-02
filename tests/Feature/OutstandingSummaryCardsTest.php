<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Client-confirmed split between the two outstanding headlines, which used to
 * be the same figure computed twice:
 *
 *   "Menunggu bayar" (invoice list) - orders nobody has paid into yet, shown
 *   at their full invoice nominal, since nothing has been received.
 *
 *   "Total piutang" (piutang page) - orders that already received a DP but
 *   have not been settled, shown as the amount still owed.
 *
 * This narrows the earlier Phase 3 rule, where "Total piutang" covered every
 * non-cancelled unpaid invoice - see ReceivablePiutangScopeTest.
 */
class OutstandingSummaryCardsTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_menunggu_bayar_shows_the_full_nominal_of_invoices_with_no_payment(): void
    {
        $customer = $this->customer();
        // Counted: nothing paid in, so the whole 400k is still waiting.
        $this->invoice($customer, 'INV-CARD-UNPAID', 400000);
        // Excluded: a DP already landed, so this is tracked as piutang.
        $withDp = $this->invoice($customer, 'INV-CARD-DP', 1000000);
        $this->verifiedPayment($withDp, 400000);

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Rp400.000')
            ->assertSee('1 invoice belum ada pembayaran');
    }

    public function test_menunggu_bayar_ignores_a_pending_unverified_payment(): void
    {
        $invoice = $this->invoice($this->customer(), 'INV-CARD-PENDING', 250000);
        $invoice->payments()->create([
            'payment_number' => 'PAY-CARD-PENDING',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => 100000,
            'status' => Payment::STATUS_PENDING,
        ]);

        // An unverified payment is not money in the bank, so the invoice is
        // still waiting for its full nominal.
        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Rp250.000')
            ->assertSee('1 invoice belum ada pembayaran');
    }

    public function test_total_piutang_counts_only_the_balance_of_invoices_that_received_a_dp(): void
    {
        $customer = $this->customer();
        $withDp = $this->invoice($customer, 'INV-PIUTANG-DP', 1000000);
        $this->verifiedPayment($withDp, 400000);
        $this->invoice($customer, 'INV-PIUTANG-NONE', 400000);

        $this->get(route('payments.receivables.index'))
            ->assertOk()
            ->assertSee('Rp600.000')
            ->assertSee('1 invoice sudah DP, belum lunas')
            // The unpaid invoice still belongs on the page - only the
            // headline excludes it.
            ->assertSee('INV-PIUTANG-NONE');
    }

    public function test_a_cancelled_invoice_is_absent_from_both_headlines(): void
    {
        $customer = $this->customer();
        $cancelled = $this->invoice($customer, 'INV-CARD-CANCELLED', 900000);
        $cancelled->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('0 invoice belum ada pembayaran');

        $this->get(route('payments.receivables.index'))
            ->assertOk()
            ->assertSee('0 invoice sudah DP, belum lunas');
    }

    private function customer(): Customer
    {
        return Customer::query()->create(['name' => 'PT Kartu '.random_int(1000, 9999)]);
    }

    private function invoice(Customer $customer, string $number, int $totalAmount): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => $totalAmount,
        ]);
    }

    private function verifiedPayment(Invoice $invoice, int $amount): Payment
    {
        $payment = $invoice->payments()->create([
            'payment_number' => 'PAY-CARD-'.random_int(1000, 9999),
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'currency' => 'IDR',
            'amount' => $amount,
            'status' => Payment::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $invoice->forceFill([
            'payment_status' => $amount >= (float) $invoice->total_amount ? Invoice::PAYMENT_PAID : Invoice::PAYMENT_PARTIAL,
        ])->save();

        return $payment;
    }
}
