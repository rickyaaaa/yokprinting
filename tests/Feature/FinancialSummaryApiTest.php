<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class FinancialSummaryApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_financial_summary_returns_zeroes_instead_of_demo_values_when_empty(): void
    {
        $response = $this->getJson(route('api.dashboard.financial-summary'));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_sales', 0)
            ->assertJsonPath('data.paid_amount', 0)
            ->assertJsonPath('data.unpaid_amount', 0)
            ->assertJsonPath('data.overdue_amount', 0);
    }

    public function test_financial_summary_calculates_totals_from_database_invoices(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        // Paid invoice
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0001',
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PAID,
            'total_amount' => 10000000,
        ]);

        // Unpaid invoice (not overdue)
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0002',
            'issue_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 5000000,
        ]);

        // Overdue invoice
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0003',
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 3000000,
        ]);

        // Cancelled invoice should not affect dashboard totals.
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0004',
            'issue_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'status' => Invoice::STATUS_CANCELLED,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 99000000,
        ]);

        // Active draft IS a real transaction - see
        // Invoice::scopeBusinessTransaction().
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0005',
            'issue_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(11)->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 2000000,
        ]);

        $response = $this->getJson(route('api.dashboard.financial-summary'));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_sales', 20000000)
            ->assertJsonPath('data.paid_amount', 10000000)
            ->assertJsonPath('data.paid_count', 1)
            ->assertJsonPath('data.unpaid_amount', 10000000)
            ->assertJsonPath('data.unpaid_count', 3)
            ->assertJsonPath('data.overdue_amount', 3000000)
            ->assertJsonPath('data.overdue_count', 1)
            ->assertJsonPath('data.total_invoices_count', 4);
    }
}
