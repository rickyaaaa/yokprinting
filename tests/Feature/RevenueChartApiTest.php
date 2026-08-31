<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class RevenueChartApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_revenue_chart_is_dynamic_and_empty_database_returns_zeroes(): void
    {
        CarbonImmutable::setTestNow('2026-08-03 10:00:00');

        $this->getJson(route('api.dashboard.revenue-chart'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.period', 'monthly')
            ->assertJsonPath('data.labels', ['Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt'])
            ->assertJsonPath('data.issued', [0, 0, 0, 0, 0, 0])
            ->assertJsonPath('data.paid', [0, 0, 0, 0, 0, 0]);
    }

    public function test_revenue_chart_uses_active_invoices_and_verified_payments(): void
    {
        // Client-confirmed rule: draft is a real transaction too - only
        // cancellation removes it. See Invoice::scopeBusinessTransaction().
        CarbonImmutable::setTestNow('2026-08-03 10:00:00');
        $customer = Customer::query()->create(['name' => 'PT Grafik', 'email' => 'grafik@example.test']);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GRAFIK-001',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 2000000,
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GRAFIK-DRAFT',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 500000,
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GRAFIK-CANCELLED',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'status' => Invoice::STATUS_CANCELLED,
            'total_amount' => 9000000,
        ]);
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-GRAFIK-001',
            'payment_date' => '2026-08-02',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 500000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        $this->getJson(route('api.dashboard.revenue-chart'))
            ->assertOk()
            ->assertJsonPath('data.issued.5', 2500000)
            ->assertJsonPath('data.paid.5', 500000);
    }

    public function test_revenue_chart_supports_quarterly_and_yearly_periods(): void
    {
        CarbonImmutable::setTestNow('2026-08-03 10:00:00');

        $this->getJson(route('api.dashboard.revenue-chart', ['period' => 'quarterly']))
            ->assertOk()
            ->assertJsonPath('data.labels', ['Q4 2025', 'Q1 2026', 'Q2 2026', 'Q3 2026']);
        $this->getJson(route('api.dashboard.revenue-chart', ['period' => 'yearly']))
            ->assertOk()
            ->assertJsonPath('data.labels', ['2024', '2025', '2026']);
    }
}
