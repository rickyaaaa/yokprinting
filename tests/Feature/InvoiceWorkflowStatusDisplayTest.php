<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class InvoiceWorkflowStatusDisplayTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_invoice_index_shows_payment_and_order_workflow_statuses(): void
    {
        $customer = Customer::query()->create([
            'name' => 'PT Workflow Test',
            'email' => 'workflow@example.test',
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-WORKFLOW-001',
            'issue_date' => '2026-08-20',
            'due_date' => '2026-09-03',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'order_process_status' => Invoice::ORDER_PROCESS_IN_PRODUCTION,
            'total_amount' => 500000,
        ]);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-WORKFLOW-002',
            'issue_date' => '2026-08-19',
            'due_date' => '2026-09-02',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PAID,
            'order_process_status' => Invoice::ORDER_PROCESS_COMPLETED,
            'total_amount' => 350000,
        ]);

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Status pembayaran')
            ->assertSee('Status pesanan')
            ->assertSee('Parsial')
            ->assertSee('Masih produksi')
            ->assertSee('Lunas')
            ->assertSee('Selesai');
    }
}
