<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DueInvoiceNotificationRead;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DueInvoiceNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-24 08:00:00');

        $this->user = User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_due_invoice_notifications_return_summary_and_invoice_rows(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
            'phone' => '+62 21 555 0198',
        ]);

        $overdue = $this->createInvoice($customer, 'INV-2026-0101', now()->subDays(4), 10000000, Invoice::PAYMENT_UNPAID);
        $dueToday = $this->createInvoice($customer, 'INV-2026-0102', now(), 20000000, Invoice::PAYMENT_PARTIAL);
        $dueSoon = $this->createInvoice($customer, 'INV-2026-0103', now()->addDays(3), 7000000, Invoice::PAYMENT_UNPAID);
        $this->createInvoice($customer, 'INV-2026-0104', now()->addDays(10), 9000000, Invoice::PAYMENT_UNPAID);
        $this->createInvoice($customer, 'INV-2026-0105', now()->subDays(2), 5000000, Invoice::PAYMENT_PAID);

        Payment::query()->create([
            'invoice_id' => $dueToday->id,
            'payment_number' => 'PAY-2026-0001',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 5000000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        $this->getJson(route('api.notifications.due-invoices.index', [
            'days' => 7,
            'sort' => 'due_date',
            'direction' => 'asc',
        ]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.invoice_number', $overdue->invoice_number)
            ->assertJsonPath('data.0.notification_status', 'overdue')
            ->assertJsonPath('data.1.invoice_number', $dueToday->invoice_number)
            ->assertJsonPath('data.1.outstanding_amount', 15000000)
            ->assertJsonPath('data.2.invoice_number', $dueSoon->invoice_number)
            ->assertJsonPath('summary.total_count', 3)
            ->assertJsonPath('summary.unread_count', 3)
            ->assertJsonPath('summary.overdue_count', 1)
            ->assertJsonPath('summary.due_today_count', 1)
            ->assertJsonPath('summary.due_soon_count', 1)
            ->assertJsonPath('summary.outstanding_amount', 32000000);
    }

    public function test_due_invoice_notifications_can_be_filtered_by_status_and_search(): void
    {
        $sinar = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);
        $laut = Customer::query()->create([
            'code' => 'CUS-002',
            'name' => 'CV Lautan Rasa',
            'email' => 'billing@lautanrasa.example',
        ]);

        $this->createInvoice($sinar, 'INV-2026-0201', now()->subDays(2), 10000000, Invoice::PAYMENT_UNPAID);
        $this->createInvoice($laut, 'INV-2026-0202', now()->subDay(), 8000000, Invoice::PAYMENT_UNPAID);
        $this->createInvoice($sinar, 'INV-2026-0203', now()->addDays(2), 6000000, Invoice::PAYMENT_UNPAID);

        $this->getJson(route('api.notifications.due-invoices.index', [
            'status' => 'overdue',
            'q' => 'Lautan',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'INV-2026-0202')
            ->assertJsonPath('meta.filters.status', 'overdue')
            ->assertJsonPath('meta.filters.q', 'Lautan');
    }

    public function test_due_invoice_notification_query_is_validated(): void
    {
        $this->getJson(route('api.notifications.due-invoices.index', [
            'status' => 'late-ish',
            'days' => 31,
            'limit' => 101,
            'sort' => 'created_at',
            'direction' => 'sideways',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'days', 'limit', 'sort', 'direction']);
    }

    public function test_guest_cannot_mark_due_invoice_notifications_as_read(): void
    {
        $this->app['auth']->logout();

        $this->postJson(route('api.notifications.due-invoices.read'))
            ->assertUnauthorized();
    }

    public function test_user_without_payment_permission_cannot_mark_notifications_as_read(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_VIEWER,
        ]));

        $this->postJson(route('api.notifications.due-invoices.read'))
            ->assertForbidden();
    }

    public function test_mark_read_is_persisted_per_user_and_is_idempotent(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-READ-001',
            'name' => 'PT Notification Read',
        ]);
        $invoice = $this->createInvoice(
            $customer,
            'INV-READ-0001',
            now()->addDays(2),
            1250000,
            Invoice::PAYMENT_UNPAID,
        );

        $this->getJson(route('api.notifications.due-invoices.index'))
            ->assertOk()
            ->assertJsonPath('summary.unread_count', 1)
            ->assertJsonPath('data.0.is_read', false);

        $this->postJson(route('api.notifications.due-invoices.read'))
            ->assertOk()
            ->assertJsonPath('data.marked_count', 1)
            ->assertJsonPath('data.unread_count', 0);

        $this->getJson(route('api.notifications.due-invoices.index'))
            ->assertOk()
            ->assertJsonPath('summary.total_count', 1)
            ->assertJsonPath('summary.unread_count', 0)
            ->assertJsonPath('data.0.is_read', true);

        $this->postJson(route('api.notifications.due-invoices.read'))
            ->assertOk()
            ->assertJsonPath('data.marked_count', 0);

        $this->assertDatabaseCount('due_invoice_notification_reads', 1);
        $this->assertDatabaseHas('due_invoice_notification_reads', [
            'user_id' => $this->user->id,
            'invoice_id' => $invoice->id,
        ]);

        $otherUser = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($otherUser)
            ->getJson(route('api.notifications.due-invoices.index'))
            ->assertOk()
            ->assertJsonPath('summary.unread_count', 1)
            ->assertJsonPath('data.0.is_read', false);
    }

    public function test_new_due_invoice_becomes_unread_after_existing_notifications_were_read(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-READ-002',
            'name' => 'PT Notification New',
        ]);
        $this->createInvoice($customer, 'INV-READ-0002', now()->addDay(), 500000, Invoice::PAYMENT_UNPAID);

        $this->postJson(route('api.notifications.due-invoices.read'))->assertOk();

        $this->createInvoice($customer, 'INV-READ-0003', now()->addDays(3), 750000, Invoice::PAYMENT_UNPAID);

        $this->getJson(route('api.notifications.due-invoices.index'))
            ->assertOk()
            ->assertJsonPath('summary.total_count', 2)
            ->assertJsonPath('summary.unread_count', 1);
    }

    public function test_mark_read_with_no_notifications_does_not_create_receipts(): void
    {
        $this->postJson(route('api.notifications.due-invoices.read'))
            ->assertOk()
            ->assertJsonPath('data.marked_count', 0)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertDatabaseCount('due_invoice_notification_reads', 0);
        $this->assertSame(0, DueInvoiceNotificationRead::query()->count());
    }

    private function createInvoice(
        Customer $customer,
        string $invoiceNumber,
        Carbon $dueDate,
        int $totalAmount,
        string $paymentStatus,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $dueDate->copy()->subDays(14)->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => $paymentStatus,
            'total_amount' => $totalAmount,
        ]);
    }
}
