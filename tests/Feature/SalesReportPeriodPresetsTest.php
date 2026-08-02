<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\SalesReportPeriodPresets;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportPeriodPresetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_presets_use_the_current_calendar_week_month_and_year(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-02-10 09:00:00', 'Asia/Jakarta'));

        $this->assertSame([
            'weekly' => [
                'date_from' => '2027-02-08',
                'date_to' => '2027-02-14',
            ],
            'monthly' => [
                'date_from' => '2027-02-01',
                'date_to' => '2027-02-28',
            ],
            'yearly' => [
                'date_from' => '2027-01-01',
                'date_to' => '2027-12-31',
            ],
        ], SalesReportPeriodPresets::current());
    }

    public function test_presets_follow_the_application_timezone_at_a_month_boundary(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 17:30:00', 'UTC'));

        $this->assertSame([
            'weekly' => [
                'date_from' => '2026-08-31',
                'date_to' => '2026-09-06',
            ],
            'monthly' => [
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ],
            'yearly' => [
                'date_from' => '2026-01-01',
                'date_to' => '2026-12-31',
            ],
        ], SalesReportPeriodPresets::current());
    }

    public function test_presets_handle_a_week_crossing_the_year_boundary(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-01 08:00:00', 'Asia/Jakarta'));

        $this->assertSame([
            'weekly' => [
                'date_from' => '2026-12-28',
                'date_to' => '2027-01-03',
            ],
            'monthly' => [
                'date_from' => '2027-01-01',
                'date_to' => '2027-01-31',
            ],
            'yearly' => [
                'date_from' => '2027-01-01',
                'date_to' => '2027-12-31',
            ],
        ], SalesReportPeriodPresets::current());
    }

    public function test_sales_report_page_receives_dynamic_period_ranges(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2027-02-10 09:00:00', 'Asia/Jakarta'),
        );

        $this->actingAs(User::factory()->create())
            ->get(route('reports.sales.index'))
            ->assertOk()
            ->assertSee('2027-02-01')
            ->assertSee('2027-02-28')
            ->assertDontSee('23 Juli 2026');
    }

    public function test_invoice_filter_and_export_use_the_same_frozen_monthly_range(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-03-15 10:00:00', 'Asia/Jakarta'));

        $customer = Customer::query()->create([
            'code' => 'CUS-PERIOD-001',
            'name' => 'PT Periode Sama',
            'email' => 'finance@example.test',
        ]);
        $this->createInvoice($customer, 'INV-MARCH', '2027-03-01');
        $this->createInvoice($customer, 'INV-FEBRUARY', '2027-02-28');
        $period = SalesReportPeriodPresets::current()['monthly'];

        $filterResponse = $this->getJson(route('api.reports.sales.invoices.index', $period));
        $exportResponse = $this->get(route('api.reports.sales.export', $period));

        $filterResponse
            ->assertOk()
            ->assertJsonPath('meta.date_from', $period['date_from'])
            ->assertJsonPath('meta.date_to', $period['date_to'])
            ->assertJsonFragment(['invoice_number' => 'INV-MARCH'])
            ->assertJsonMissing(['invoice_number' => 'INV-FEBRUARY']);
        $this->assertStringContainsString('INV-MARCH', $exportResponse->getContent());
        $this->assertStringNotContainsString('INV-FEBRUARY', $exportResponse->getContent());
    }

    private function createInvoice(Customer $customer, string $invoiceNumber, string $issueDate): void
    {
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'due_date' => CarbonImmutable::parse($issueDate)->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'currency' => 'IDR',
            'total_amount' => 100000,
        ]);
    }
}
