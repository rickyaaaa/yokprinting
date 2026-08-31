<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class ReportsSuiteApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_gross_profit_report_returns_revenue_hpp_shipping_and_profit(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-001', 'name' => 'PT Sinar Nusantara']);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0101',
            'issue_date' => '2026-07-10',
            'due_date' => '2026-07-24',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PAID,
            'subtotal' => 1000000,
            'discount_amount' => 100000,
            'shipping_type' => Invoice::SHIPPING_COMPANY_FREE_SHIPPING,
            'shipping_cost' => 50000,
            'total_hpp' => 500000,
            'gross_profit' => 350000,
            'total_amount' => 900000,
        ]);

        $this->getJson(route('api.reports.gross-profit.index', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 1)
            ->assertJsonPath('data.summary.revenue', 900000)
            ->assertJsonPath('data.summary.total_hpp', 500000)
            ->assertJsonPath('data.summary.company_shipping', 50000)
            ->assertJsonPath('data.summary.gross_profit', 350000)
            ->assertJsonPath('data.invoices.0.gross_margin_percent', 38.89);
    }

    public function test_gross_profit_report_includes_active_drafts_and_excludes_cancelled(): void
    {
        // See Invoice::scopeBusinessTransaction().
        $customer = Customer::query()->create(['code' => 'CUS-009', 'name' => 'PT Draft Laba']);

        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GP-SENT',
            'issue_date' => '2026-07-10', 'due_date' => '2026-07-24',
            'status' => Invoice::STATUS_SENT, 'payment_status' => Invoice::PAYMENT_PAID,
            'subtotal' => 1000000, 'total_hpp' => 600000, 'gross_profit' => 400000, 'total_amount' => 1000000,
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GP-DRAFT',
            'issue_date' => '2026-07-11', 'due_date' => '2026-07-25',
            'status' => Invoice::STATUS_DRAFT, 'payment_status' => Invoice::PAYMENT_PAID,
            'subtotal' => 500000, 'total_hpp' => 200000, 'gross_profit' => 300000, 'total_amount' => 500000,
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GP-CANCELLED',
            'issue_date' => '2026-07-12', 'due_date' => '2026-07-26',
            'status' => Invoice::STATUS_CANCELLED, 'payment_status' => Invoice::PAYMENT_UNPAID,
            'subtotal' => 9000000, 'total_hpp' => 100000, 'gross_profit' => 8900000, 'total_amount' => 9000000,
        ]);

        $this->getJson(route('api.reports.gross-profit.index', [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.summary.invoice_count', 2)
            ->assertJsonPath('data.summary.revenue', 1500000)
            ->assertJsonPath('data.summary.total_hpp', 800000)
            ->assertJsonPath('data.summary.gross_profit', 700000);
    }

    public function test_report_alias_endpoints_return_outstanding_inactive_low_stock_and_stock_mutation_data(): void
    {
        $customer = Customer::query()->create(['code' => 'CUS-002', 'name' => 'CV Lautan Rasa']);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0102',
            'issue_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'subtotal' => 1000000,
            'total_amount' => 1000000,
            'paid_at' => '2026-06-01 10:00:00',
        ]);
        $invoice->payments()->create([
            'payment_number' => 'PAY-001',
            'payment_date' => '2026-06-05',
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 300000,
            'status' => Payment::STATUS_VERIFIED,
            'currency' => 'IDR',
            'verified_at' => '2026-06-05 12:00:00',
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0098',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-14',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PAID,
            'subtotal' => 500000,
            'total_amount' => 500000,
            'paid_at' => '2026-05-01 10:00:00',
        ]);

        $product = Product::query()->create([
            'sku' => 'CUP-16OV-8G',
            'name' => 'Cup 16 Oz Oval 8gr',
            'stock' => 4,
            'minimum_stock' => 10,
            'track_stock' => true,
        ]);
        $this->movement($product, StockMovement::TYPE_OPENING_BALANCE, 20, '2026-07-01 09:00:00');
        $this->movement($product, StockMovement::TYPE_SALE, -16, '2026-07-10 09:00:00');

        $this->getJson(route('api.reports.outstanding-payments.index'))
            ->assertOk()
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0102')
            ->assertJsonPath('data.0.outstanding_amount', 700000);

        $this->getJson(route('api.reports.inactive-customers.index'))
            ->assertOk()
            ->assertJsonPath('data.needs_attention', true)
            ->assertJsonPath('data.customers.0.code', 'CUS-002');

        $this->getJson(route('api.reports.low-stock.index'))
            ->assertOk()
            ->assertJsonPath('data.products.0.sku', 'CUP-16OV-8G');

        $this->getJson(route('api.reports.stock-mutations.index', [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('data.products.0.incoming_quantity', 20)
            ->assertJsonPath('data.products.0.outgoing_quantity', 16)
            ->assertJsonPath('data.products.0.closing_balance', 4);
    }

    public function test_report_exports_download_excel_compatible_csv_files(): void
    {
        $this->actingAs(User::factory()->create());

        $customer = Customer::query()->create(['code' => 'CUS-003', 'name' => 'PT Bumi Lestari']);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0103',
            'issue_date' => '2026-07-11',
            'due_date' => '2026-07-25',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'subtotal' => 1000000,
            'total_hpp' => 600000,
            'gross_profit' => 400000,
            'total_amount' => 1000000,
        ]);
        $product = Product::query()->create([
            'sku' => 'CUP-12DT-7G',
            'name' => 'Cup 12 Oz Datar 7gr',
            'stock' => 2,
            'minimum_stock' => 5,
            'track_stock' => true,
        ]);
        $this->movement($product, StockMovement::TYPE_OPENING_BALANCE, 2, '2026-07-11 09:00:00');

        foreach ([
            'api.reports.sales.export',
            'api.reports.gross-profit.export',
            'api.reports.outstanding-payments.export',
            'api.reports.inactive-customers.export',
            'api.reports.low-stock.export',
            'api.reports.stock-mutations.export',
        ] as $route) {
            $response = $this->get(route($route, [
                'date_from' => '2026-07-01',
                'date_to' => '2026-07-31',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-31',
            ]));

            $response
                ->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

            $this->assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));
            $this->assertNotEmpty($response->getContent());
        }
    }

    public function test_legacy_report_exports_require_report_export_permission(): void
    {
        auth()->logout();

        foreach ($this->exportRoutes() as $route) {
            $this->getJson(route($route))->assertUnauthorized();
        }

        $this->actingAs($this->userWithPermissions([]));

        foreach ($this->exportRoutes() as $route) {
            $this->getJson(route($route))->assertForbidden();
        }

        $this->actingAs($this->userWithPermissions(['report.export']));

        foreach ($this->exportRoutes() as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    /**
     * @return list<string>
     */
    private function exportRoutes(): array
    {
        return [
            'api.reports.gross-profit.export',
            'api.reports.outstanding-payments.export',
            'api.reports.inactive-customers.export',
            'api.reports.low-stock.export',
            'api.reports.stock-mutations.export',
        ];
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            [$module, $action] = explode('.', $permissionCode, 2);
            $permission = Permission::factory()->create([
                'code' => $permissionCode,
                'module' => $module,
                'action' => $action,
            ]);
            $role->permissions()->attach($permission);
        }

        return User::factory()->create(['role' => $role->code]);
    }

    private function movement(Product $product, string $type, int|float $quantity, string $createdAt): void
    {
        $createdAt = CarbonImmutable::parse($createdAt);
        $movement = StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => 0,
            'stock_after' => 0,
        ]);

        $movement->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
