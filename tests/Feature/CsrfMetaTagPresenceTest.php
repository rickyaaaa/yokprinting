<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page that submits a mutating request (create/update/delete) through
 * a JS fetch() call reads its CSRF token from a <meta name="csrf-token">
 * tag. If that tag is missing, every submission on the page silently fails
 * with an HTTP 419 — this happened in production for customers, products,
 * invoices, role permissions, and the company profile page. This test locks
 * the fix in place so a future page can't reintroduce the same bug unnoticed.
 */
class CsrfMetaTagPresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));
    }

    public function test_customer_create_page_has_csrf_meta_tag(): void
    {
        $this->get(route('customers.create'))->assertSee('name="csrf-token"', false);
    }

    public function test_customer_index_page_has_csrf_meta_tag(): void
    {
        $this->get(route('customers.index'))->assertSee('name="csrf-token"', false);
    }

    public function test_customer_edit_page_has_csrf_meta_tag(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-CSRF-001', 'name' => 'PT CSRF Test', 'address' => 'Jl. Uji Coba',
        ]);

        $this->get(route('customers.edit', $customer))->assertSee('name="csrf-token"', false);
    }

    public function test_product_create_page_has_csrf_meta_tag(): void
    {
        $this->get(route('products.create'))->assertSee('name="csrf-token"', false);
    }

    public function test_product_index_page_has_csrf_meta_tag(): void
    {
        $this->get(route('products.index'))->assertSee('name="csrf-token"', false);
    }

    public function test_product_edit_page_has_csrf_meta_tag(): void
    {
        $product = Product::query()->create([
            'sku' => 'SKU-CSRF-001', 'name' => 'Produk Uji CSRF', 'unit' => 'Pcs',
        ]);

        $this->get(route('products.edit', $product))->assertSee('name="csrf-token"', false);
    }

    public function test_invoice_create_page_has_csrf_meta_tag(): void
    {
        $this->get(route('invoices.create'))->assertSee('name="csrf-token"', false);
    }

    public function test_role_permissions_page_has_csrf_meta_tag(): void
    {
        $this->get(route('roles.permissions.edit', 'finance_admin'))->assertSee('name="csrf-token"', false);
    }

    public function test_company_profile_settings_page_has_csrf_meta_tag(): void
    {
        $this->get(route('settings.company-profile.edit'))->assertSee('name="csrf-token"', false);
    }

    public function test_due_invoices_page_has_csrf_meta_tag(): void
    {
        $this->get(route('notifications.due-invoices.index'))->assertSee('name="csrf-token"', false);
    }

    public function test_invoice_payment_detail_page_has_csrf_meta_tag(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-CSRF-002', 'name' => 'PT CSRF Detail', 'address' => 'Jl. Uji Coba 2',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-CSRF-0001',
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-15',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 1000000,
        ]);

        $this->get(route('payments.invoices.show', $invoice))->assertSee('name="csrf-token"', false);
    }

    public function test_cash_bank_page_has_csrf_meta_tag(): void
    {
        $this->get(route('cash-bank.index'))->assertSee('name="csrf-token"', false);
    }

    public function test_expense_pages_have_csrf_meta_tag(): void
    {
        $this->get(route('expenses.index'))->assertSee('name="csrf-token"', false);
        $this->get(route('expenses.create'))->assertSee('name="csrf-token"', false);
    }

    public function test_purchase_order_pages_have_csrf_meta_tag(): void
    {
        $this->get(route('purchase-orders.index'))->assertSee('name="csrf-token"', false);
        $this->get(route('purchase-orders.create'))->assertSee('name="csrf-token"', false);
    }

    public function test_goods_receipt_pages_have_csrf_meta_tag(): void
    {
        $this->get(route('goods-receipts.index'))->assertSee('name="csrf-token"', false);
        $this->get(route('goods-receipts.create'))->assertSee('name="csrf-token"', false);
    }
}
