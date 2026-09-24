<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class InvoiceListActionLockTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_list_locks_edit_but_keeps_cancellation_available_after_payment(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Invoice Action Lock']);

        $unpaid = $this->invoice($customer, 'INV-ACTION-UNPAID', Invoice::PAYMENT_UNPAID);
        $partial = $this->invoice($customer, 'INV-ACTION-PARTIAL', Invoice::PAYMENT_PARTIAL);
        $paid = $this->invoice($customer, 'INV-ACTION-PAID', Invoice::PAYMENT_PAID);

        $response = $this->get(route('invoices.index'))->assertOk();
        $rows = collect($response->viewData('invoiceRows'))->keyBy('number');

        $this->assertTrue($rows[$unpaid->invoice_number]['is_editable']);
        $this->assertTrue($rows[$unpaid->invoice_number]['can_be_cancelled']);
        $this->assertFalse($rows[$partial->invoice_number]['is_editable']);
        $this->assertTrue($rows[$partial->invoice_number]['can_be_cancelled']);
        $this->assertFalse($rows[$paid->invoice_number]['is_editable']);
        $this->assertTrue($rows[$paid->invoice_number]['can_be_cancelled']);
        $response->assertSee('name="csrf-token"', false);
        $response->assertSee('data-action-label="batalkan invoice"', false);
    }

    private function invoice(Customer $customer, string $number, string $paymentStatus): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => $paymentStatus,
            'total_amount' => 100000,
        ]);
    }
}
