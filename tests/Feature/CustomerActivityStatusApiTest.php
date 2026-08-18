<?php

namespace Tests\Feature;

use App\Jobs\UpdateCustomerFollowUpStatusesJob;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class CustomerActivityStatusApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_customer_activity_status_is_computed_from_latest_paid_invoice(): void
    {
        $this->assertTrue(Schema::hasColumn('customers', 'last_order_at'));

        Carbon::setTestNow('2026-07-27 09:00:00');

        $active = $this->createCustomer('CUS-ACTIVE', 'PT Aktif Printing');
        $followUp = $this->createCustomer('CUS-FOLLOW', 'CV Perlu Follow Up');
        $neverOrdered = $this->createCustomer('CUS-NEVER', 'UD Belum Order');

        $this->createPaidInvoice($active, 'INV-ACTIVE', now()->subDays(10));
        $this->createPaidInvoice($followUp, 'INV-FOLLOW', now()->subDays(45));

        $this->assertSame(Customer::ACTIVITY_ACTIVE, $active->fresh()->activity_status);
        $this->assertSame(Customer::ACTIVITY_NEEDS_FOLLOW_UP, $followUp->fresh()->activity_status);
        $this->assertSame(Customer::ACTIVITY_NEVER_ORDERED, $neverOrdered->fresh()->activity_status);
    }

    public function test_customer_show_payload_includes_activity_status(): void
    {
        Carbon::setTestNow('2026-07-27 09:00:00');

        $customer = $this->createCustomer('CUS-API', 'PT API Status');
        $this->createPaidInvoice($customer, 'INV-API', now()->subDays(31));

        $this->getJson(route('api.customers.show', $customer))
            ->assertOk()
            ->assertJsonPath('data.activity_status', Customer::ACTIVITY_NEEDS_FOLLOW_UP)
            ->assertJsonMissingPath('data.segment');
    }

    public function test_dashboard_customer_activity_alert_returns_follow_up_customers(): void
    {
        Carbon::setTestNow('2026-07-27 09:00:00');

        $active = $this->createCustomer('CUS-ACTIVE', 'PT Aktif Printing');
        $followUp = $this->createCustomer('CUS-FOLLOW', 'CV Perlu Follow Up');
        $neverOrdered = $this->createCustomer('CUS-NEVER', 'UD Belum Order');

        $this->createPaidInvoice($active, 'INV-ACTIVE', now()->subDays(7));
        $this->createPaidInvoice($followUp, 'INV-FOLLOW', now()->subDays(40));

        $this->getJson(route('api.dashboard.customer-activity-alerts'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.needs_attention', true)
            ->assertJsonPath('data.threshold_days', 30)
            ->assertJsonPath('data.customers.0.code', 'CUS-FOLLOW')
            ->assertJsonPath('data.customers.0.activity_status', Customer::ACTIVITY_NEEDS_FOLLOW_UP)
            ->assertJsonMissing(['code' => $active->code])
            ->assertJsonMissing(['code' => $neverOrdered->code]);
    }

    public function test_daily_job_updates_last_order_and_auto_follow_up_statuses(): void
    {
        Carbon::setTestNow('2026-07-27 09:00:00');

        $active = $this->createCustomer('CUS-ACTIVE', 'PT Aktif Printing');
        $inactiveOneMonth = $this->createCustomer('CUS-1M', 'CV Sebulan Sepi');
        $autoFollowUp = $this->createCustomer('CUS-2M', 'UD Dua Bulan Sepi');

        $this->createPaidInvoice($active, 'INV-ACTIVE', now()->subDays(10));
        $this->createPaidInvoice($inactiveOneMonth, 'INV-1M', now()->subDays(35));
        $this->createPaidInvoice($autoFollowUp, 'INV-2M', now()->subDays(65));

        app(UpdateCustomerFollowUpStatusesJob::class)->handle();

        $this->assertSame(Customer::STATUS_ACTIVE, $active->refresh()->status);
        $this->assertSame(Customer::STATUS_INACTIVE_1M, $inactiveOneMonth->refresh()->status);
        $this->assertSame(Customer::STATUS_AUTO_FOLLOWUP, $autoFollowUp->refresh()->status);
        $this->assertNotNull($autoFollowUp->last_order_at);
    }

    private function createCustomer(string $code, string $name): Customer
    {
        return Customer::query()->create([
            'code' => $code,
            'name' => $name,
            'email' => strtolower($code).'@example.test',
            'address' => 'Jl. Customer No. 1',
            'city' => 'Tangerang',
        ]);
    }

    private function createPaidInvoice(Customer $customer, string $invoiceNumber, Carbon $paidAt): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->getKey(),
            'invoice_number' => $invoiceNumber,
            'issue_date' => $paidAt->toDateString(),
            'due_date' => $paidAt->copy()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PAID,
            'currency' => 'IDR',
            'total_amount' => 1000000,
            'paid_at' => $paidAt,
        ]);
    }
}
