<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DueInvoiceNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-24 08:00:00');

        $this->actingAs(User::factory()->create([
            'role' => User::ROLE_OWNER,
        ]));
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
