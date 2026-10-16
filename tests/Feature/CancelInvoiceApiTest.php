<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryBatch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CashBank\CashBankService;
use App\Services\Inventory\FifoInventoryService;
use App\Services\Security\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CancelInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_cancel_an_invoice(): void
    {
        $invoice = $this->createInvoice();

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertUnauthorized();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
    }

    public function test_user_without_invoice_update_permission_cannot_cancel_an_invoice(): void
    {
        $invoice = $this->createInvoice();
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertForbidden();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
    }

    public function test_invoice_can_be_cancelled_with_a_reason(): void
    {
        $invoice = $this->createInvoice();
        $user = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($user);

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]), [
            'reason' => 'Customer batal order.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED)
            ->assertJsonPath('data.cancellation_reason', 'Customer batal order.');

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->status);
        $this->assertNotNull($invoice->cancelled_at);
        $this->assertSame($user->getKey(), $invoice->cancelled_by);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'invoice',
            'action' => 'cancelled',
            'subject_type' => $invoice->getMorphClass(),
            'subject_id' => $invoice->getKey(),
        ]);
    }

    public function test_already_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invoice_with_a_payment_can_be_cancelled(): void
    {
        $invoice = $this->createInvoice();
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260725-0001',
            'payment_date' => '2026-07-25',
            'method' => Payment::METHOD_CASH,
            'amount' => 1000000,
            'status' => Payment::STATUS_VERIFIED,
        ]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED);

        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->refresh()->status);
    }

    public function test_cancelling_invoice_reverses_its_cash_bank_payment(): void
    {
        $invoice = $this->createInvoice();
        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-20260725-0002',
            'payment_date' => '2026-07-25',
            'method' => Payment::METHOD_CASH,
            'amount' => 1000000,
            'status' => Payment::STATUS_VERIFIED,
        ]);
        app(CashBankService::class)->recordPayment($payment);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertOk();

        $this->assertDatabaseHas('cash_bank_transactions', [
            'source_type' => 'payment',
            'source_id' => $payment->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_partial_invoice_can_be_cancelled_even_if_payment_rows_are_missing(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill(['payment_status' => Invoice::PAYMENT_PARTIAL])->save();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED);

        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->refresh()->status);
    }

    public function test_paid_invoice_can_be_cancelled(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill(['payment_status' => Invoice::PAYMENT_PAID])->save();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED);

        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->refresh()->status);
    }

    public function test_invoice_with_completed_production_can_be_cancelled(): void
    {
        $invoice = $this->createInvoice();
        $invoice->forceFill(['production_status' => Invoice::PRODUCTION_COMPLETED])->save();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', ['invoice' => $invoice->invoice_number]))
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED);
    }

    public function test_client_invoice_0057_can_be_cancelled_after_receipt_partially_closed_its_deficit(): void
    {
        [$invoice, $product] = $this->createConsumedDeficitInvoice('INV-2026-10-0057', 10);
        $this->settleFromReceipt($product, 4, 725, 'GR-0057');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', $invoice->invoice_number), [
            'reason' => 'Customer membatalkan order.',
        ])->assertOk()->assertJsonPath('data.status', Invoice::STATUS_CANCELLED);

        $this->assertSame('4.0000', $product->refresh()->stock);
        $this->assertSame(4.0, (float) InventoryBatch::query()->where('product_id', $product->id)->sum('qty_remaining'));
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'source_type' => 'deficit_compensation',
            'source_reference' => 'GR-0057',
            'qty_remaining' => 4,
            'unit_cost' => 725,
        ]);
    }

    public function test_client_invoice_0058_can_be_cancelled_after_receipt_fully_closed_its_deficit(): void
    {
        [$invoice, $product] = $this->createConsumedDeficitInvoice('INV-2026-10-0058', 10);
        $this->settleFromReceipt($product, 10, 810, 'GR-0058');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->postJson(route('api.invoices.cancel.store', $invoice->invoice_number))
            ->assertOk()
            ->assertJsonPath('data.status', Invoice::STATUS_CANCELLED);

        $this->assertSame('10.0000', $product->refresh()->stock);
        $this->assertSame(10.0, (float) InventoryBatch::query()->where('product_id', $product->id)->sum('qty_remaining'));
        $this->assertDatabaseHas('inventory_batches', [
            'product_id' => $product->id,
            'source_type' => 'deficit_compensation',
            'source_reference' => 'GR-0058',
            'qty_remaining' => 10,
            'unit_cost' => 810,
        ]);

        $this->postJson(route('api.invoices.cancel.store', $invoice->invoice_number))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
        $this->assertSame('10.0000', $product->refresh()->stock, 'a repeated request must not restore stock twice');
    }

    public function test_cancellation_rolls_back_fifo_cash_bank_and_status_when_audit_write_fails(): void
    {
        [$invoice, $product] = $this->createConsumedDeficitInvoice('INV-ROLLBACK-DEFICIT', 10);
        $this->settleFromReceipt($product, 4, 725, 'GR-ROLLBACK');
        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-ROLLBACK-DEFICIT',
            'payment_date' => '2026-10-16',
            'method' => Payment::METHOD_CASH,
            'amount' => 1000,
            'status' => Payment::STATUS_VERIFIED,
        ]);
        $cashTransaction = app(CashBankService::class)->recordPayment($payment);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $logger = \Mockery::mock(ActivityLogger::class);
        $logger->shouldReceive('record')->once()->andThrow(new RuntimeException('simulated audit database failure'));
        $this->app->instance(ActivityLogger::class, $logger);

        $this->withoutExceptionHandling();
        try {
            $this->postJson(route('api.invoices.cancel.store', $invoice->invoice_number));
            $this->fail('The simulated audit failure should escape the request.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated audit database failure', $exception->getMessage());
        }

        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
        $this->assertSame('-6.0000', $product->refresh()->stock);
        $this->assertSame(-6.0, (float) InventoryBatch::query()->where('product_id', $product->id)->sum('qty_remaining'));
        $this->assertSame('posted', $cashTransaction->refresh()->status);
        $this->assertSame(0, InventoryBatch::query()->where('source_type', 'deficit_compensation')->count());
        $this->assertSame(0, $invoice->items()->firstOrFail()->costLayers()->whereNotNull('reversed_at')->count());
    }

    private function createInvoice(): Invoice
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0501',
            'issue_date' => '2026-07-23',
            'due_date' => '2026-08-06',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 5000000,
        ]);
    }

    /** @return array{0: Invoice, 1: Product} */
    private function createConsumedDeficitInvoice(string $number, float $quantity): array
    {
        $customer = Customer::query()->create(['name' => "Customer {$number}"]);
        $product = Product::query()->create([
            'name' => "Produk {$number}",
            'sku' => str_replace('INV-', 'SKU-', $number),
            'track_stock' => true,
            'stock' => 0,
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
        ]);
        $product->forceFill(['average_purchase_cost' => 500])->save();
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'issue_date' => '2026-10-16',
            'due_date' => '2026-10-30',
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_PARTIAL,
            'total_amount' => $quantity * 1000,
        ]);
        $item = $invoice->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => $quantity,
            'unit_price' => 1000,
            'purchase_cost_snapshot' => 500,
            'subtotal' => $quantity * 1000,
            'total_amount' => $quantity * 1000,
        ]);

        app(FifoInventoryService::class)->consume($invoice, $item);

        return [$invoice->refresh(), $product->refresh()];
    }

    private function settleFromReceipt(Product $product, float $quantity, float $unitCost, string $reference): void
    {
        $before = (float) $product->refresh()->stock;
        $leftover = InventoryBatch::closeDeficitFor($product->id, $quantity, [
            'unit_cost' => $unitCost,
            'source_type' => 'goods_receipt',
            'source_reference' => $reference,
            'source_date' => '2026-10-16',
        ]);

        if ($leftover > 0) {
            InventoryBatch::query()->create([
                'product_id' => $product->id,
                'purchase_date' => '2026-10-16',
                'qty_received' => $leftover,
                'qty_remaining' => $leftover,
                'unit_cost' => $unitCost,
                'source_type' => 'goods_receipt',
                'source_reference' => $reference,
            ]);
        }

        $product->forceFill(['stock' => round($before + $quantity, 4)])->save();
        StockMovement::query()->create([
            'product_id' => $product->id,
            'type' => StockMovement::TYPE_PURCHASE,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => round($before + $quantity, 4),
            'reference_number' => $reference,
            'notes' => "Penerimaan barang {$reference}.",
        ]);
    }
}
