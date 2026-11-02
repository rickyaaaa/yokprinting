<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CashBankTransaction;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\Invoices\BuildInvoiceWhatsAppLink;
use App\Services\Invoices\GenerateInvoiceNumber;
use App\Services\Invoices\UpdateInvoiceDraft;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

/**
 * Client request: the invoice number can be changed by hand on the edit page
 * (e.g. INV-2026-11-0212 -> INV-2026-10-0205 for a Shopee order), without
 * touching anything else on the invoice. See UpdateInvoiceDraftRequest and
 * UpdateInvoiceDraft::renumber().
 */
class EditInvoiceNumberTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    private const REASON = 'Menyesuaikan nomor dokumen pesanan Shopee Oktober';

    public function test_invoice_number_can_be_changed_in_place(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $invoiceCount = Invoice::withTrashed()->count();

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-11-0500'))
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonPath('data.invoice_number', 'INV-2026-11-0500');

        $this->assertSame('INV-2026-11-0500', $invoice->refresh()->invoice_number);
        $this->assertSame($invoiceCount, Invoice::withTrashed()->count(), 'renumbering must not create an invoice');
        $this->assertDatabaseHas('invoice_number_histories', [
            'invoice_id' => $invoice->id,
            'invoice_number' => 'INV-2026-11-0001',
            'replaced_by' => 'INV-2026-11-0500',
            'reason' => self::REASON,
        ]);
    }

    public function test_editing_other_fields_keeps_the_number_without_reason_or_audit(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();

        $this->patchJson(route('api.invoices.update', $invoice), [
            ...$this->payload($customer, $product, price: 150000),
            'invoice_number' => 'INV-2026-11-0001',
        ])->assertOk()->assertJsonPath('data.invoice_number', 'INV-2026-11-0001');

        $this->patchJson(route('api.invoices.update', $invoice), $this->payload($customer, $product, price: 160000))
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-2026-11-0001');

        $this->assertSame('1600000.00', $invoice->refresh()->subtotal);
        $this->assertDatabaseCount('invoice_number_histories', 0);
        $this->assertSame(0, ActivityLog::query()->where('action', 'number_changed')->count());
    }

    public function test_legacy_number_is_kept_when_other_fields_are_edited(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $invoice->forceFill(['invoice_number' => 'INV-2026-0042'])->save();

        $this->patchJson(route('api.invoices.update', 'INV-2026-0042'), [
            ...$this->payload($customer, $product, price: 150000),
            'invoice_number' => 'INV-2026-0042',
        ])->assertOk()->assertJsonPath('data.invoice_number', 'INV-2026-0042');
    }

    public function test_number_of_another_invoice_is_rejected(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $this->createInvoice($customer, $product); // INV-2026-11-0002

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-11-0002'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice_number.0', 'Nomor invoice sudah digunakan. Silakan pilih nomor lain.');

        $this->assertSame('INV-2026-11-0001', $invoice->refresh()->invoice_number);
    }

    public function test_number_of_a_soft_deleted_invoice_is_rejected(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $this->createInvoice($customer, $product)->delete(); // INV-2026-11-0002

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-11-0002'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice_number.0', Invoice::NUMBER_TAKEN_MESSAGE);
    }

    public function test_previous_number_of_another_invoice_is_rejected_but_own_previous_number_can_be_restored(): void
    {
        [$customer, $product, $first] = $this->draftInvoice();
        $second = $this->createInvoice($customer, $product); // INV-2026-11-0002

        $this->patchJson(route('api.invoices.update', $first), $this->renumberPayload($customer, $product, 'INV-2026-10-0205'))->assertOk();

        $this->patchJson(route('api.invoices.update', $second), $this->renumberPayload($customer, $product, 'INV-2026-11-0001'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.invoice_number.0', Invoice::NUMBER_TAKEN_MESSAGE);

        $this->patchJson(route('api.invoices.update', $first->refresh()), $this->renumberPayload($customer, $product, 'INV-2026-11-0001'))
            ->assertOk();

        $this->assertSame('INV-2026-11-0001', $first->refresh()->invoice_number);
        $this->assertSame(['INV-2026-10-0205'], $first->numberHistories()->pluck('invoice_number')->all());
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_number_format_is_rejected(string $number): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, $number))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invoice_number']);

        $this->assertSame('INV-2026-11-0001', $invoice->refresh()->invoice_number);
    }

    /** @return array<string, array{string}> */
    public static function invalidNumbers(): array
    {
        return [
            'month 13' => ['INV-2026-13-0001'],
            'three digit suffix' => ['INV-2026-10-001'],
            'lowercase prefix' => ['inv-2026-10-0205'],
            'free text' => ['SHOPEE-2026-10-0205'],
            'trailing letters' => ['INV-2026-10-0205A'],
            'too long' => ['INV-2026-10-'.str_repeat('1', 40)],
        ];
    }

    public function test_empty_number_and_missing_reason_are_rejected(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();

        $this->patchJson(route('api.invoices.update', $invoice), [
            ...$this->payload($customer, $product),
            'invoice_number' => '',
        ])->assertUnprocessable()->assertJsonPath('errors.invoice_number.0', 'Nomor invoice wajib diisi.');

        $this->patchJson(route('api.invoices.update', $invoice), [
            ...$this->payload($customer, $product),
            'invoice_number' => 'INV-2026-11-0500',
        ])->assertUnprocessable()->assertJsonValidationErrors(['invoice_number_change_reason']);
    }

    public function test_free_october_number_can_replace_a_november_number_without_moving_the_invoice_period(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $invoice->forceFill(['invoice_number' => 'INV-2026-11-0212'])->save();

        $this->patchJson(route('api.invoices.update', 'INV-2026-11-0212'), $this->renumberPayload($customer, $product, 'INV-2026-10-0205'))
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-2026-10-0205');

        $invoice->refresh();
        $this->assertSame('2026-11-02', $invoice->issue_date->toDateString(), 'the issue date must not follow the number');
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'payment_status' => Invoice::PAYMENT_PAID])->save();
        $this->assertTrue(
            Invoice::query()->recognizedBetween('2026-11-01', '2026-12-01')->whereKey($invoice->id)->exists(),
            'the invoice must stay in the November report period',
        );

        $log = ActivityLog::query()->where('action', 'number_changed')->sole();
        $this->assertTrue($log->metadata['number_period_differs_from_issue_date']);
    }

    public function test_cancelled_invoice_still_cannot_be_renumbered(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $invoice->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-11-0500', confirmed: true))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('INV-2026-11-0001', $invoice->refresh()->invoice_number);
        $this->assertDatabaseCount('invoice_number_histories', 0);
    }

    public function test_sent_or_paid_invoice_requires_confirmation(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $invoice->forceFill(['status' => Invoice::STATUS_SENT, 'sent_at' => now()])->save();

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-11-0500'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirm_invoice_number_change']);

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-11-0500', confirmed: true))
            ->assertOk();
    }

    public function test_renumbering_a_paid_invoice_leaves_payments_stock_hpp_and_cash_untouched(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
        $customer = Customer::query()->create(['name' => 'PT Shopee Paid']);
        $product = $this->product('RENUM-FIFO-01', trackStock: true);
        $this->receiveGoods($product, quantity: 100, unitCost: 600);
        $invoice = $this->createInvoice($customer, $product);
        $payment = $this->verifiedPayment($invoice, 1000000);

        $invoice->refresh();
        $before = [
            'customer_id' => $invoice->customer_id,
            'status' => $invoice->status,
            'payment_status' => $invoice->payment_status,
            'production_status' => $invoice->production_status,
            'total_amount' => $invoice->total_amount,
            'total_hpp' => $invoice->total_hpp,
            'gross_profit' => $invoice->gross_profit,
            'items' => $invoice->items()->pluck('id')->all(),
        ];
        $paymentIds = Payment::query()->where('invoice_id', $invoice->id)->pluck('id')->all();
        $stock = (float) $product->refresh()->stock;
        $movements = StockMovement::query()->count();
        $cashBank = CashBankTransaction::query()->count();

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-10-0205', confirmed: true))
            ->assertOk();

        $invoice->refresh();
        $this->assertSame('INV-2026-10-0205', $invoice->invoice_number);
        $this->assertSame($before, [
            'customer_id' => $invoice->customer_id,
            'status' => $invoice->status,
            'payment_status' => $invoice->payment_status,
            'production_status' => $invoice->production_status,
            'total_amount' => $invoice->total_amount,
            'total_hpp' => $invoice->total_hpp,
            'gross_profit' => $invoice->gross_profit,
            'items' => $invoice->items()->pluck('id')->all(),
        ]);
        $this->assertSame(Invoice::PAYMENT_PAID, $invoice->payment_status);
        $this->assertSame($paymentIds, Payment::query()->where('invoice_id', $invoice->id)->pluck('id')->all());
        $this->assertSame($invoice->id, $payment->refresh()->invoice_id);
        $this->assertSame($stock, (float) $product->refresh()->stock);
        $this->assertSame($movements, StockMovement::query()->count());
        $this->assertSame($cashBank, CashBankTransaction::query()->count());
    }

    public function test_documents_and_links_use_the_new_number(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $invoice->forceFill(['production_status' => Invoice::PRODUCTION_READY_FOR_PICKUP])->save();
        $this->assertSame('SJ-2026-11-0001', $invoice->deliveryNoteNumber());

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-10-0205'))
            ->assertOk();
        $invoice->refresh();

        $this->get(route('api.invoices.pdf.download', ['invoice' => $invoice->id]), ['Accept' => 'application/pdf'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="invoice-inv-2026-10-0205.pdf"');
        $this->assertSame('SJ-2026-10-0205', $invoice->deliveryNoteNumber());
        $this->assertStringContainsString('INV-2026-10-0205', app(BuildInvoiceWhatsAppLink::class)->message($invoice));
        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('INV-2026-10-0205')
            ->assertDontSee('INV-2026-11-0001');
    }

    public function test_new_and_old_links_open_the_same_invoice(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $other = $this->createInvoice($customer, $product); // INV-2026-11-0002

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-10-0205'))
            ->assertOk();

        $this->get(route('invoices.edit', 'INV-2026-10-0205'))->assertOk()->assertSee('Edit INV-2026-10-0205');
        $this->get(route('invoices.edit', 'INV-2026-11-0001'))->assertOk()->assertSee('Edit INV-2026-10-0205');
        $this->get(route('payments.invoices.show', 'INV-2026-11-0001'))->assertOk();
        $this->getJson(route('api.invoices.payment-detail.show', 'INV-2026-11-0001'))
            ->assertOk()
            ->assertJsonPath('data.invoice.invoice_number', 'INV-2026-10-0205');
        $this->getJson(route('api.invoices.payment-detail.show', 'INV-2026-11-0002'))
            ->assertOk()
            ->assertJsonPath('data.invoice.invoice_number', $other->invoice_number);
        $this->get(route('invoices.edit', 'INV-2026-11-0999'))->assertNotFound();
    }

    public function test_renumbering_is_audited(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-10-0205'))
            ->assertOk();

        $log = ActivityLog::query()->where('action', 'number_changed')->sole();
        $this->assertSame(ActivityLog::RISK_HIGH, $log->risk_level);
        $this->assertSame(auth()->id(), $log->user_id);
        $this->assertSame($invoice->id, (int) $log->subject_id);
        $this->assertSame($invoice->id, $log->metadata['invoice_id']);
        $this->assertSame('INV-2026-11-0001', $log->metadata['old_invoice_number']);
        $this->assertSame('INV-2026-10-0205', $log->metadata['new_invoice_number']);
        $this->assertSame(self::REASON, $log->metadata['reason']);
        $this->assertSame(auth()->id(), $log->metadata['changed_by']);
        $this->assertArrayHasKey('changed_at', $log->metadata);
    }

    public function test_monthly_numbering_skips_manual_and_previous_numbers_without_resetting(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice(); // INV-2026-11-0001

        $this->patchJson(route('api.invoices.update', $invoice), $this->renumberPayload($customer, $product, 'INV-2026-11-0003'))
            ->assertOk();

        // 0001 stays reserved for the renumbered invoice, 0003 is taken by it.
        $this->assertSame('INV-2026-11-0002', $this->createInvoice($customer, $product)->invoice_number);
        $this->assertSame('INV-2026-11-0004', $this->createInvoice($customer, $product)->invoice_number);
        $this->assertSame(4, (int) DB::table('invoice_monthly_number_sequences')->where(['year' => 2026, 'month' => 11])->value('last_number'));
        $this->assertSame('INV-2026-12-0001', app(GenerateInvoiceNumber::class)->generate(CarbonImmutable::parse('2026-12-01')));
    }

    public function test_number_taken_after_validation_is_still_rejected_inside_the_transaction(): void
    {
        [$customer, $product, $invoice] = $this->draftInvoice();
        $other = $this->createInvoice($customer, $product);
        $other->forceFill(['invoice_number' => 'INV-2026-10-0205'])->save();

        try {
            app(UpdateInvoiceDraft::class)->handle($invoice, $this->renumberPayload($customer, $product, 'INV-2026-10-0205'));
            $this->fail('A number taken after request validation must still be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([Invoice::NUMBER_TAKEN_MESSAGE], $exception->errors()['invoice_number']);
        }

        $this->assertSame('INV-2026-11-0001', $invoice->refresh()->invoice_number);
        $this->expectException(UniqueConstraintViolationException::class);
        $invoice->forceFill(['invoice_number' => 'INV-2026-10-0205'])->save();
    }

    public function test_concurrent_renumbering_to_the_same_number_assigns_it_once(): void
    {
        $database = sys_get_temp_dir().DIRECTORY_SEPARATOR.'invoice-renumber-race-'.uniqid().'.sqlite';
        touch($database);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database];
        $worker = base_path('tests/Fixtures/invoice-renumber-worker.php');

        try {
            (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $env, null, 300))->mustRun();
            $seed = new Process([PHP_BINARY, $worker, 'seed', '4'], base_path(), $env, null, 120);
            $seed->mustRun();
            $invoiceIds = json_decode(trim($seed->getOutput()), true, flags: JSON_THROW_ON_ERROR);

            $startAt = (string) (microtime(true) + 3);
            $processes = collect($invoiceIds)->map(function (int $id) use ($worker, $env, $startAt): Process {
                $process = new Process([PHP_BINARY, $worker, 'renumber', (string) $id, 'INV-2026-10-0205', $startAt], base_path(), $env, null, 120);
                $process->start();

                return $process;
            });
            $outcomes = $processes->map(function (Process $process): string {
                $process->wait();

                return trim($process->getOutput());
            });

            $pdo = new PDO('sqlite:'.$database);
            $holders = $pdo->query("select count(*) from invoices where invoice_number = 'INV-2026-10-0205'")->fetchColumn();
            $histories = $pdo->query("select count(*) from invoice_number_histories where replaced_by = 'INV-2026-10-0205'")->fetchColumn();
            $pdo = null;

            $this->assertSame(1, $outcomes->filter(fn (string $outcome): bool => $outcome === 'renumbered')->count(), $outcomes->implode(' | '));
            $this->assertSame(1, (int) $holders);
            $this->assertSame(1, (int) $histories);
        } finally {
            @unlink($database);
        }
    }

    /** @return array{Customer, Product, Invoice} */
    private function draftInvoice(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
        $customer = Customer::query()->create(['name' => 'PT Renumber']);
        $product = $this->product('RENUM-'.random_int(1000, 9999));

        return [$customer, $product, $this->createInvoice($customer, $product)];
    }

    private function createInvoice(Customer $customer, Product $product): Invoice
    {
        $response = $this->postJson(route('api.invoices.drafts.store'), $this->payload($customer, $product))
            ->assertCreated();

        return Invoice::query()->with('items')->findOrFail($response->json('data.id'));
    }

    /** @return array<string, mixed> */
    private function renumberPayload(Customer $customer, Product $product, string $number, bool $confirmed = false): array
    {
        return [
            ...$this->payload($customer, $product),
            'invoice_number' => $number,
            'invoice_number_change_reason' => self::REASON,
            ...($confirmed ? ['confirm_invoice_number_change' => true] : []),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(Customer $customer, Product $product, float $price = 100000): array
    {
        return [
            'customer_id' => $customer->id,
            'issue_date' => '2026-11-02',
            'due_date' => '2026-11-16',
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'price' => $price]],
            'discount' => ['type' => 'percentage', 'value' => 0],
            'tax' => ['enabled' => false, 'rate' => 0],
        ];
    }

    private function product(string $sku, bool $trackStock = false): Product
    {
        return Product::query()->create([
            'name' => "Produk {$sku}",
            'sku' => $sku,
            'category' => 'Cetak premium',
            'minimum_order_qty' => 1,
            'package_conversion' => 1,
            'track_stock' => $trackStock,
            'stock' => $trackStock ? 0 : null,
        ]);
    }

    private function verifiedPayment(Invoice $invoice, float $amount): Payment
    {
        $response = $this->postJson(route('api.invoices.payments.store', $invoice->invoice_number), [
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => $amount,
        ])->assertCreated();

        return Payment::query()->findOrFail($response->json('data.id'));
    }

    private function receiveGoods(Product $product, float $quantity, float $unitCost): void
    {
        $supplier = Supplier::query()->create(['code' => 'SUP-'.random_int(10000, 99999), 'name' => 'Supplier Renumber']);
        $poResponse = $this->postJson(route('api.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-11-01',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $unitCost]],
        ])->assertCreated();

        $po = PurchaseOrder::query()->with('items')->findOrFail($poResponse->json('data.id'));
        $this->postJson(route('api.purchase-orders.submit', $po))->assertOk();
        $this->postJson(route('api.purchase-orders.approve', $po))->assertOk();

        $grResponse = $this->postJson(route('api.purchase-orders.goods-receipts.store', $po), [
            'receipt_date' => '2026-11-01',
            'items' => [['purchase_order_item_id' => $po->items->first()->id, 'quantity_received' => $quantity]],
        ])->assertCreated();

        $this->postJson(route('api.goods-receipts.post', GoodsReceipt::query()->findOrFail($grResponse->json('data.id'))))->assertOk();
    }
}
