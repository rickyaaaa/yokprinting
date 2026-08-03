<?php

namespace Tests\Feature;

use App\Jobs\CleanupTemporaryReportFilesJob;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\GenerateProfitLossSpreadsheet;
use App\Services\Reports\ProfitLossReport;
use App\Services\Reports\TemporaryReportFileCleanup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class ProfitLossReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Jakarta']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-03-15 10:00:00', 'Asia/Jakarta'));
        $this->owner = User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_report_calculates_all_totals_on_the_server_from_stored_records(): void
    {
        $this->actingAs($this->owner);
        $customer = Customer::query()->create(['code' => 'CUS-PL-001', 'name' => 'PT Laba Bersih']);

        $this->invoice($customer, 'INV-PL-001', '2027-03-05', 1100, 600, 50, Invoice::SHIPPING_COMPANY_FREE_SHIPPING, 10);
        $this->invoice($customer, 'INV-PL-002', '2027-03-15', 1300, 800, 100, Invoice::SHIPPING_PAID_BY_CUSTOMER, 5);
        $this->invoice($customer, 'INV-CANCELLED', '2027-03-08', 9999, 1, 1, Invoice::SHIPPING_COMPANY_FREE_SHIPPING, 99, Invoice::STATUS_CANCELLED);
        $this->invoice($customer, 'INV-DRAFT', '2027-03-09', 8888, 1, 1, Invoice::SHIPPING_PAID_BY_CUSTOMER, 88, Invoice::STATUS_DRAFT);
        $this->invoice($customer, 'INV-OUTSIDE', '2027-02-28', 9999, 1, 1, Invoice::SHIPPING_COMPANY_FREE_SHIPPING, 99);

        $this->expense(Expense::CATEGORY_PRODUCTION, 100, '2027-03-05');
        $this->expense(Expense::CATEGORY_EMPLOYEE, 200, '2027-03-06', Expense::SUBCATEGORY_SALARY);
        $this->expense(Expense::CATEGORY_PREMISES, 300, '2027-03-07');
        $this->expense(Expense::CATEGORY_SHOPPING, 400, '2027-03-08');
        $this->expense(Expense::CATEGORY_PRODUCTION, 9999, '2027-02-28');

        $this->getJson(route('api.reports.profit-loss.show', [
            'period' => 'custom',
            'date_from' => '2027-03-01',
            'date_to' => '2027-03-31',
            'sales_revenue' => 1,
            'gross_profit' => 1,
        ]))
            ->assertOk()
            ->assertJsonPath('data.period.date_from', '2027-03-01')
            ->assertJsonPath('data.period.date_to', '2027-03-31')
            ->assertJsonPath('data.summary.gross_sales', 2400)
            ->assertJsonPath('data.summary.sales_discount', 0)
            ->assertJsonPath('data.summary.sales_revenue', 2400)
            ->assertJsonPath('data.summary.tax_collected', 0)
            ->assertJsonPath('data.summary.customer_shipping_charged', 100)
            ->assertJsonPath('data.summary.total_invoice', 2500)
            ->assertJsonPath('data.summary.invoice_reconciliation_difference', 0)
            ->assertJsonPath('data.summary.total_hpp', 1400)
            ->assertJsonPath('data.summary.shipping_expenses', 50)
            ->assertJsonPath('data.summary.production_expenses', 100)
            ->assertJsonPath('data.summary.employee_expenses', 200)
            ->assertJsonPath('data.summary.premises_expenses', 300)
            ->assertJsonPath('data.summary.shopping_expenses', 400)
            ->assertJsonPath('data.summary.unclassified_expenses', 500)
            ->assertJsonPath('data.summary.recognized_expenses', 550)
            ->assertJsonPath('data.summary.recorded_expenses', 1050)
            ->assertJsonPath('data.summary.gross_profit', 1000)
            ->assertJsonPath('data.summary.net_profit_minimum', -50)
            ->assertJsonPath('data.summary.net_profit_maximum', 450)
            ->assertJsonPath('data.summary.profit_range', 500)
            ->assertJsonPath('data.summary.minimum_profit_reconciliation_difference', 0)
            ->assertJsonPath('data.summary.maximum_profit_reconciliation_difference', 0)
            ->assertJsonPath('data.summary.sales_quantity', 15)
            ->assertJsonPath('data.summary.invoice_count', 2)
            ->assertJsonPath('data.summary.expense_count', 4)
            ->assertJsonPath('data.accounting_policy.profit_is_provisional', true)
            ->assertJsonPath('data.accounting_policy.tax_is_revenue', false);
    }

    public function test_draft_and_cancelled_invoices_do_not_enter_revenue_or_sales_quantity(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-PL-STATUS', 'name' => 'Status Test']);
        $this->invoice($customer, 'INV-FINAL', '2027-03-15', 1000, 400, 0, Invoice::SHIPPING_NONE, 10);
        $this->invoice($customer, 'INV-DRAFT-ONLY', '2027-03-15', 2000, 800, 0, Invoice::SHIPPING_NONE, 20, Invoice::STATUS_DRAFT);
        $this->invoice($customer, 'INV-CANCELLED-ONLY', '2027-03-15', 3000, 1200, 0, Invoice::SHIPPING_NONE, 30, Invoice::STATUS_CANCELLED);

        $summary = app(ProfitLossReport::class)->build('daily')['summary'];

        $this->assertSame(1000.0, $summary['gross_sales']);
        $this->assertSame(1000.0, $summary['sales_revenue']);
        $this->assertSame(10.0, $summary['sales_quantity']);
        $this->assertSame(1, $summary['invoice_count']);
    }

    public function test_tax_discount_and_customer_shipping_are_separated_and_reconcile_total_invoice(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-PL-COMP', 'name' => 'Component Test']);
        $this->invoice(
            $customer,
            'INV-COMPONENTS',
            '2027-03-15',
            1000,
            400,
            50,
            Invoice::SHIPPING_PAID_BY_CUSTOMER,
            10,
            discount: 100,
            tax: 90,
        );

        $summary = app(ProfitLossReport::class)->build('daily')['summary'];

        $this->assertSame(1000.0, $summary['gross_sales']);
        $this->assertSame(100.0, $summary['sales_discount']);
        $this->assertSame(900.0, $summary['sales_revenue']);
        $this->assertSame(90.0, $summary['tax_collected']);
        $this->assertSame(50.0, $summary['customer_shipping_charged']);
        $this->assertSame(1040.0, $summary['expected_invoice_total']);
        $this->assertSame(1040.0, $summary['total_invoice']);
        $this->assertSame(0.0, $summary['invoice_reconciliation_difference']);
        $this->assertSame(500.0, $summary['gross_profit']);
    }

    public function test_ambiguous_production_and_shopping_expenses_are_disclosed_without_double_counting_hpp(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-PL-OVERLAP', 'name' => 'Overlap Test']);
        $this->invoice($customer, 'INV-OVERLAP', '2027-03-15', 1000, 600, 0, Invoice::SHIPPING_NONE, 10);
        $this->expense(Expense::CATEGORY_PRODUCTION, 250, '2027-03-15');
        $this->expense(Expense::CATEGORY_SHOPPING, 150, '2027-03-15');
        $this->expense(Expense::CATEGORY_EMPLOYEE, 100, '2027-03-15', Expense::SUBCATEGORY_SALARY);

        $report = app(ProfitLossReport::class)->build('daily');
        $summary = $report['summary'];

        $this->assertSame(400.0, $summary['gross_profit']);
        $this->assertSame(400.0, $summary['unclassified_expenses']);
        $this->assertSame(500.0, $summary['recorded_expenses']);
        $this->assertSame(100.0, $summary['recognized_expenses']);
        $this->assertSame(-100.0, $summary['net_profit_minimum']);
        $this->assertSame(300.0, $summary['net_profit_maximum']);
        $this->assertSame(400.0, $summary['profit_range']);
        $this->assertSame(0.0, $summary['minimum_profit_reconciliation_difference']);
        $this->assertSame(0.0, $summary['maximum_profit_reconciliation_difference']);
        $this->assertTrue($report['accounting_policy']['profit_is_provisional']);
        $this->assertStringContainsString('dikurangkan', $report['accounting_policy']['minimum_profit_basis']);
        $this->assertStringContainsString('mungkin sudah termasuk HPP', $report['accounting_policy']['maximum_profit_basis']);
        $this->assertStringContainsString('Owner perlu menentukan', $report['accounting_policy']['decision_required']);
    }

    public function test_daily_weekly_monthly_yearly_and_custom_filters_use_server_periods(): void
    {
        $this->actingAs($this->owner);

        foreach ([
            'daily' => ['2027-03-15', '2027-03-15'],
            'weekly' => ['2027-03-15', '2027-03-21'],
            'monthly' => ['2027-03-01', '2027-03-31'],
            'yearly' => ['2027-01-01', '2027-12-31'],
        ] as $period => [$from, $to]) {
            $this->getJson(route('api.reports.profit-loss.show', ['period' => $period]))
                ->assertOk()
                ->assertJsonPath('data.period.key', $period)
                ->assertJsonPath('data.period.date_from', $from)
                ->assertJsonPath('data.period.date_to', $to);
        }

        $this->getJson(route('api.reports.profit-loss.show', [
            'period' => 'custom',
            'date_from' => '2026-12-29',
            'date_to' => '2027-01-02',
        ]))
            ->assertOk()
            ->assertJsonPath('data.period.date_from', '2026-12-29')
            ->assertJsonPath('data.period.date_to', '2027-01-02');
    }

    public function test_custom_range_validation_rejects_missing_or_reversed_dates(): void
    {
        $this->actingAs($this->owner);

        $this->getJson(route('api.reports.profit-loss.show', ['period' => 'custom']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from', 'date_to']);

        $this->getJson(route('api.reports.profit-loss.show', [
            'period' => 'custom',
            'date_from' => '2027-03-20',
            'date_to' => '2027-03-01',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_pdf_and_excel_exports_use_the_same_server_report_and_valid_file_formats(): void
    {
        $this->actingAs($this->owner);
        $customer = Customer::query()->create(['code' => 'CUS-PL-002', 'name' => 'CV Export Laporan']);
        $this->invoice($customer, 'INV-EXPORT', '2027-03-15', 125000, 75000, 5000, Invoice::SHIPPING_COMPANY_FREE_SHIPPING, 20);
        $this->expense(Expense::CATEGORY_PRODUCTION, 10000, '2027-03-15');

        $filters = ['period' => 'daily'];
        $this->assertSame(125000.0, app(ProfitLossReport::class)
            ->build('daily')['summary']['sales_revenue']);
        $this->getJson(route('api.reports.profit-loss.show', $filters))
            ->assertOk()
            ->assertJsonPath('data.summary.sales_revenue', 125000)
            ->assertJsonPath('data.summary.net_profit_minimum', 35000)
            ->assertJsonPath('data.summary.net_profit_maximum', 45000);
        $pdf = $this->get(route('api.reports.profit-loss.pdf', $filters));
        $excel = $this->get(route('api.reports.profit-loss.excel', $filters));

        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $excel->assertOk()->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringStartsWith('PK', $excel->getContent());

        $temporaryPath = tempnam(sys_get_temp_dir(), 'profit-loss-test-');
        file_put_contents($temporaryPath, $excel->getContent());
        $archive = new ZipArchive;

        try {
            $this->assertTrue($archive->open($temporaryPath) === true);
            $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($worksheet);
            $this->assertStringContainsString('Omzet Penjualan', $worksheet);
            $this->assertStringContainsString('Pajak dipungut (bukan omzet)', $worksheet);
            $this->assertStringContainsString('Pengeluaran belum diklasifikasikan terhadap HPP', $worksheet);
            $this->assertStringContainsString('<c r="B23" s="5"><v>35000</v></c>', $worksheet);
            $this->assertStringContainsString('<c r="B24" s="5"><v>45000</v></c>', $worksheet);
            $this->assertStringContainsString('<v>20</v>', $worksheet);
        } finally {
            $archive->close();
            $this->assertTrue(unlink($temporaryPath));
        }
    }

    public function test_spreadsheet_cleanup_failure_is_logged_without_breaking_export(): void
    {
        Log::spy();
        $temporaryDirectory = storage_path('framework/testing/report-temporary-'.uniqid());
        config([
            'reports.temporary_directory' => $temporaryDirectory,
            'reports.temporary_file_grace_minutes' => 60,
        ]);
        $cleanup = new class extends TemporaryReportFileCleanup
        {
            /** @var list<string> */
            public array $paths = [];

            protected function removeFile(string $path): bool
            {
                $this->paths[] = $path;

                return false;
            }
        };
        try {
            $file = (new GenerateProfitLossSpreadsheet($cleanup))
                ->generate(app(ProfitLossReport::class)->build('daily'));

            $this->assertStringStartsWith('PK', $file->contents);
            $this->assertCount(1, $cleanup->paths);
            $this->assertFileExists($cleanup->paths[0]);
            $this->assertStringStartsWith($temporaryDirectory, $cleanup->paths[0]);
            Log::shouldHaveReceived('warning')
                ->once()
                ->with('Temporary report file cleanup failed.', Mockery::on(
                    fn (array $context): bool => isset($context['path_hash'], $context['error_type'])
                        && ! isset($context['path'], $context['contents']),
                ));

            touch($cleanup->paths[0], now()->subMinutes(61)->getTimestamp());
            $this->assertSame(1, app(CleanupTemporaryReportFilesJob::class)
                ->handle(app(TemporaryReportFileCleanup::class)));
            $this->assertFileDoesNotExist($cleanup->paths[0]);
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    public function test_report_exports_share_rate_limit_and_reset_after_window(): void
    {
        config(['reports.export_rate_limit_per_minute' => 2]);
        $this->actingAs($this->owner);
        $key = 'report-export:user:'.$this->owner->getAuthIdentifier();
        RateLimiter::clear($key);

        $this->get(route('api.reports.profit-loss.excel'))->assertOk();
        $this->get(route('api.reports.profit-loss.pdf'))->assertOk();
        $this->getJson(route('api.reports.sales.export'))
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak permintaan export laporan. Silakan coba lagi nanti.');

        CarbonImmutable::setTestNow(now()->addMinute()->addSecond());
        $this->get(route('api.reports.profit-loss.excel'))->assertOk();
    }

    public function test_report_page_and_endpoints_enforce_view_and_export_permissions_separately(): void
    {
        $this->getJson(route('api.reports.profit-loss.show'))->assertUnauthorized();
        $this->getJson(route('api.reports.profit-loss.pdf'))->assertUnauthorized();
        $this->get(route('reports.profit-loss.index'))->assertRedirect(route('login'));

        $this->actingAs($this->userWithPermissions([]));
        $this->getJson(route('api.reports.profit-loss.show'))->assertForbidden();
        $this->getJson(route('api.reports.profit-loss.excel'))->assertForbidden();
        $this->get(route('reports.profit-loss.index'))->assertForbidden();

        $this->actingAs($this->userWithPermissions(['report.view']));
        $this->get(route('reports.profit-loss.index'))->assertOk()->assertSee('Laporan laba rugi');
        $this->getJson(route('api.reports.profit-loss.show'))->assertOk();
        $this->getJson(route('api.reports.profit-loss.pdf'))->assertForbidden();

        $this->actingAs($this->userWithPermissions(['report.export']));
        $this->get(route('api.reports.profit-loss.excel'))->assertOk();
        $this->getJson(route('api.reports.profit-loss.show'))->assertForbidden();
    }

    private function invoice(
        Customer $customer,
        string $number,
        string $date,
        float $revenue,
        float $hpp,
        float $shipping,
        string $shippingType,
        float $quantity,
        string $status = Invoice::STATUS_SENT,
        float $discount = 0,
        float $tax = 0,
    ): Invoice {
        $customerShipping = $shippingType === Invoice::SHIPPING_PAID_BY_CUSTOMER ? $shipping : 0;
        $totalInvoice = $revenue - $discount + $tax + $customerShipping;
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->getKey(),
            'created_by' => $this->owner->getKey(),
            'invoice_number' => $number,
            'issue_date' => $date,
            'due_date' => CarbonImmutable::parse($date)->addDays(14)->toDateString(),
            'status' => $status,
            'subtotal' => $revenue,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => $totalInvoice,
            'total_hpp' => $hpp,
            'shipping_type' => $shippingType,
            'shipping_cost' => $shipping,
        ]);
        $invoice->items()->create([
            'product_name' => 'Produk Laporan',
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? $revenue / $quantity : 0,
            'purchase_cost_snapshot' => $quantity > 0 ? $hpp / $quantity : 0,
            'subtotal' => $revenue,
            'total_amount' => $revenue,
        ]);

        return $invoice;
    }

    private function expense(string $category, float $amount, string $date, ?string $subcategory = null): Expense
    {
        return Expense::query()->create([
            'expense_date' => $date,
            'category' => $category,
            'subcategory' => $subcategory,
            'amount' => $amount,
            'description' => 'Biaya untuk regression test laporan',
            'recipient' => 'Penerima Test',
            'payment_method' => 'Transfer',
            'proof_path' => 'expense-proofs/test.pdf',
            'proof_original_name' => 'test.pdf',
            'proof_mime_type' => 'application/pdf',
            'created_by' => $this->owner->getKey(),
        ]);
    }

    /** @param list<string> $permissions */
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::factory()->create();

        foreach ($permissions as $code) {
            [$module, $action] = explode('.', $code, 2);
            $permission = Permission::factory()->create([
                'code' => $code,
                'module' => $module,
                'action' => $action,
            ]);
            $role->permissions()->attach($permission);
        }

        return User::factory()->create(['role' => $role->code]);
    }
}
