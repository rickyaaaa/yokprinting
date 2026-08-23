<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class UpdateInvoiceDraftApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_draft_invoice_items_and_totals_can_be_updated(): void
    {
        $customer = $this->createCustomer();
        $productA = $this->createProduct('Paket A', 'PKG-A');
        $productB = $this->createProduct('Paket B', 'PKG-B');

        $invoice = $this->createInvoiceDraft($customer, $productA, quantity: 1, price: 1000000);

        $this->patchJson(route('api.invoices.update', $invoice), $this->payload($customer, $productB, quantity: 2, price: 500000))
            ->assertOk()
            ->assertJsonPath('message', 'Invoice berhasil diperbarui.')
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.subtotal', '1000000.00');

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $productB->id,
            'quantity' => 2,
            'unit_price' => 500000,
        ]);
        // The superseded product-A item is soft-deleted for audit (see
        // InvoiceItem::SoftDeletes), not gone - only product B is active.
        $this->assertDatabaseHas('invoice_items', ['product_id' => $productA->id]);
        $this->assertNotNull(InvoiceItem::withTrashed()->where('product_id', $productA->id)->firstOrFail()->deleted_at);
        $this->assertSame(1, $invoice->items()->count());
    }

    public function test_editing_reverses_old_item_stock_and_applies_new_item_stock(): void
    {
        $customer = $this->createCustomer();
        $productA = $this->createProduct('Cup A', 'CUP-A', trackStock: true, stock: 1000);
        $productB = $this->createProduct('Cup B', 'CUP-B', trackStock: true, stock: 1000);

        $invoice = $this->createInvoiceDraft($customer, $productA, quantity: 100, price: 1000);

        $productA->refresh();
        $this->assertSame('900.0000', $productA->stock);

        $this->patchJson(route('api.invoices.update', $invoice), $this->payload($customer, $productB, quantity: 50, price: 1000))
            ->assertOk();

        $productA->refresh();
        $productB->refresh();
        $this->assertSame('1000.0000', $productA->stock, 'old item stock must be restored');
        $this->assertSame('950.0000', $productB->stock, 'new item stock must be deducted');

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id,
            'type' => 'adjustment',
            'quantity' => '100.0000',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productB->id,
            'type' => 'sale',
            'quantity' => '-50.0000',
        ]);
    }

    public function test_sent_invoice_can_be_updated_and_stays_sent(): void
    {
        // Client requirement: an invoice stays editable after being sent -
        // only `cancelled` blocks it. See Invoice::isEditable() and the
        // dedicated coverage in EditInvoiceAfterIssuanceTest.
        $customer = $this->createCustomer();
        $product = $this->createProduct('Paket A', 'PKG-A');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1, price: 1000000);
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();

        $this->patchJson(route('api.invoices.update', $invoice), $this->payload($customer, $product, quantity: 2, price: 1000000))
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.subtotal', '2000000.00');

        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
    }

    public function test_cancelled_invoice_cannot_be_updated(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct('Paket A', 'PKG-A');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1, price: 1000000);
        $invoice->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();

        $this->patchJson(route('api.invoices.update', $invoice), $this->payload($customer, $product, quantity: 1, price: 1000000))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_update_does_not_touch_order_process_status_or_mockup_fields(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct('Paket A', 'PKG-A');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1, price: 1000000);
        $invoice->forceFill([
            'order_process_status' => Invoice::ORDER_PROCESS_IN_PRODUCTION,
            'mockup_url' => 'https://yokprinting.id/mockup/test.png',
        ])->save();

        // The edit form's payload always includes order_process_status
        // (defaulting to 'draft' client-side when the field is absent from
        // the DOM) - the service must not let that silently regress it.
        $payload = $this->payload($customer, $product, quantity: 1, price: 1000000);
        $payload['order_process_status'] = Invoice::ORDER_PROCESS_DRAFT;

        $this->patchJson(route('api.invoices.update', $invoice), $payload)->assertOk();

        $invoice->refresh();
        $this->assertSame(Invoice::ORDER_PROCESS_IN_PRODUCTION, $invoice->order_process_status);
        $this->assertSame('https://yokprinting.id/mockup/test.png', $invoice->mockup_url);
    }

    public function test_guest_cannot_update_an_invoice(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct('Paket A', 'PKG-A');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1, price: 1000000);
        auth()->logout();

        $this->patchJson(route('api.invoices.update', $invoice), $this->payload($customer, $product, quantity: 1, price: 1000000))
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_update_an_invoice(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createProduct('Paket A', 'PKG-A');
        $invoice = $this->createInvoiceDraft($customer, $product, quantity: 1, price: 1000000);

        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->patchJson(route('api.invoices.update', $invoice), $this->payload($customer, $product, quantity: 1, price: 1000000))
            ->assertForbidden();
    }

    private function createCustomer(): Customer
    {
        return Customer::query()->create([
            'name' => 'PT Pelanggan Edit',
            'email' => fake()->unique()->safeEmail(),
            'address' => 'Jl. Edit No. 1',
            'city' => 'Tangerang',
        ]);
    }

    private function createProduct(string $name, string $sku, bool $trackStock = false, float $stock = 0): Product
    {
        $product = Product::query()->create([
            'name' => $name,
            'sku' => $sku,
            'category' => 'Cetak premium',
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
            'track_stock' => $trackStock,
            'stock' => $trackStock ? $stock : null,
        ]);
        $product->forceFill(['average_purchase_cost' => 500])->save();

        return $product;
    }

    private function createInvoiceDraft(Customer $customer, Product $product, float $quantity, float $price): Invoice
    {
        $response = $this->postJson(route('api.invoices.drafts.store'), $this->payload($customer, $product, $quantity, $price))
            ->assertCreated();

        return Invoice::query()->findOrFail($response->json('data.id'));
    }

    /** @return array<string, mixed> */
    private function payload(Customer $customer, Product $product, float $quantity, float $price): array
    {
        return [
            'customer_id' => $customer->id,
            'issue_date' => '2026-08-19',
            'due_date' => '2026-09-02',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'price' => $price,
                ],
            ],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
            'notes' => 'Catatan.',
            'terms' => 'Syarat.',
        ];
    }
}
