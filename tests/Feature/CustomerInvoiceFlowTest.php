<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_customer_flows_from_create_through_options_search_and_invoice_draft(): void
    {
        $create = $this->postJson(route('api.customers.store'), [
            'name' => 'PT Flow Demo Baru',
            'email' => 'finance@flow-demo.test',
            'phone' => '0812-555-0199',
            'address' => 'Jl. Demo Nyata No. 7',
            'city' => 'Tangerang',
        ])->assertCreated();

        $customerId = $create->json('data.id');
        $customerCode = $create->json('data.code');

        $this->actingAs(User::factory()->create())
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('PT Flow Demo Baru')
            ->assertSee('finance@flow-demo.test');

        $this->getJson(route('api.customers.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $customerId)
            ->assertJsonPath('data.0.code', $customerCode)
            ->assertJsonPath('data.0.name', 'PT Flow Demo Baru');

        foreach ([$customerCode, 'Flow Demo', 'finance@flow-demo.test', '0812-555-0199'] as $search) {
            $this->getJson(route('api.customers.index', ['search' => $search]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $customerId);
        }

        $product = Product::query()->create([
            'name' => 'Produk Flow Demo',
            'sku' => 'DEMO-FLOW-001',
            'purchase_price' => 500,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);

        $draft = $this->postJson(route('api.invoices.drafts.store'), [
            'customer_id' => $customerId,
            'issue_date' => '2026-08-03',
            'due_date' => '2026-08-10',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'price' => 1000,
            ]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ])->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'id' => $draft->json('data.id'),
            'customer_id' => $customerId,
            'status' => 'draft',
        ]);
        $invoice = Invoice::query()->findOrFail($draft->json('data.id'));

        $this->assertSame($customerId, $invoice->customer_id);
        $this->assertSame('PT Flow Demo Baru', $invoice->customer->name);
        $this->assertSame('finance@flow-demo.test', $invoice->customer->email);
        $this->assertSame('0812-555-0199', $invoice->customer->phone);

        $this->actingAs(User::factory()->create())
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('PT Flow Demo Baru')
            ->assertDontSee('PT Sinar Nusantara');
    }

    public function test_inactive_deleted_and_unknown_customers_cannot_be_selected_for_invoice(): void
    {
        $inactive = Customer::query()->create([
            'name' => 'Pelanggan Nonaktif',
            'email' => 'inactive-flow@example.test',
            'status' => Customer::STATUS_INACTIVE,
        ]);
        $deleted = Customer::query()->create([
            'name' => 'Pelanggan Terhapus',
            'email' => 'deleted-flow@example.test',
        ]);
        $deleted->delete();

        $this->getJson(route('api.customers.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $inactive->id])
            ->assertJsonMissing(['id' => $deleted->id]);

        foreach ([$inactive->id, $deleted->id, 999999] as $customerId) {
            $this->postJson(route('api.invoices.drafts.store'), [
                'customer_id' => $customerId,
                'issue_date' => '2026-08-03',
                'due_date' => '2026-08-10',
                'items' => [],
                'discount' => ['type' => 'percentage', 'value' => 0],
                'tax' => ['enabled' => false, 'rate' => 0],
            ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        }
    }

    public function test_duplicate_customer_email_is_rejected_with_email_validation_error(): void
    {
        Customer::query()->create([
            'name' => 'PT Email Existing',
            'email' => 'duplicate-flow@example.test',
        ]);

        $this->postJson(route('api.customers.store'), [
            'name' => 'PT Email Duplikat',
            'email' => 'duplicate-flow@example.test',
            'address' => 'Jl. Duplikat No. 1',
            'city' => 'Tangerang',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
