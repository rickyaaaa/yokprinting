<?php

namespace Tests\Feature;

use App\Jobs\SendDueInvoiceReminderEmailsJob;
use App\Mail\DueInvoiceReminderMail;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Invoices\SendDueInvoiceReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DueInvoiceReminderEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-24 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_due_invoice_reminder_service_sends_email_and_records_metadata(): void
    {
        Mail::fake();

        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);
        $noEmailCustomer = Customer::query()->create([
            'code' => 'CUS-002',
            'name' => 'CV Tanpa Email',
            'email' => null,
        ]);

        $overdue = $this->createInvoice($customer, 'INV-2026-0301', now()->subDays(2), 10000000, Invoice::PAYMENT_UNPAID);
        $dueSoon = $this->createInvoice($customer, 'INV-2026-0302', now()->addDays(2), 20000000, Invoice::PAYMENT_PARTIAL);
        $future = $this->createInvoice($customer, 'INV-2026-0303', now()->addDays(10), 9000000, Invoice::PAYMENT_UNPAID);
        $paid = $this->createInvoice($customer, 'INV-2026-0304', now()->addDay(), 5000000, Invoice::PAYMENT_PAID);
        $withoutEmail = $this->createInvoice($noEmailCustomer, 'INV-2026-0305', now()->addDay(), 7000000, Invoice::PAYMENT_UNPAID);

        Payment::query()->create([
            'invoice_id' => $dueSoon->id,
            'payment_number' => 'PAY-2026-0101',
            'payment_date' => now()->toDateString(),
            'method' => Payment::METHOD_TRANSFER_BCA,
            'amount' => 5000000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        $sentCount = app(SendDueInvoiceReminders::class)->handle(daysAhead: 3);

        $this->assertSame(2, $sentCount);
        Mail::assertSent(DueInvoiceReminderMail::class, 2);
        Mail::assertSent(
            DueInvoiceReminderMail::class,
            fn (DueInvoiceReminderMail $mail): bool => $mail->hasTo('finance@sinarnusantara.co.id')
                && $mail->invoice->is($overdue)
                && $mail->notificationStatus === 'overdue',
        );
        Mail::assertSent(
            DueInvoiceReminderMail::class,
            fn (DueInvoiceReminderMail $mail): bool => $mail->invoice->is($dueSoon)
                && $mail->notificationStatus === 'due_soon'
                && $mail->outstandingAmount === 15000000.0,
        );

        $this->assertSame(1, $overdue->refresh()->metadata['due_reminder']['sent_count']);
        $this->assertSame('overdue', $overdue->metadata['due_reminder']['last_status']);
        $this->assertNull($future->refresh()->metadata);
        $this->assertNull($paid->refresh()->metadata);
        $this->assertNull($withoutEmail->refresh()->metadata);
        $this->assertSame(2, ActivityLog::query()->where('action', 'due_reminder_sent')->count());
    }

    public function test_due_invoice_reminder_service_is_idempotent_for_same_day(): void
    {
        Mail::fake();

        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);
        $this->createInvoice($customer, 'INV-2026-0401', now()->addDay(), 10000000, Invoice::PAYMENT_UNPAID);

        $firstRun = app(SendDueInvoiceReminders::class)->handle(daysAhead: 3);
        $secondRun = app(SendDueInvoiceReminders::class)->handle(daysAhead: 3);

        $this->assertSame(1, $firstRun);
        $this->assertSame(0, $secondRun);
        Mail::assertSent(DueInvoiceReminderMail::class, 1);
    }

    public function test_due_invoice_reminder_job_delegates_to_service(): void
    {
        Mail::fake();

        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);
        $this->createInvoice($customer, 'INV-2026-0501', now(), 10000000, Invoice::PAYMENT_UNPAID);

        $sentCount = (new SendDueInvoiceReminderEmailsJob(daysAhead: 3))
            ->handle(app(SendDueInvoiceReminders::class));

        $this->assertSame(1, $sentCount);
        Mail::assertSent(DueInvoiceReminderMail::class, 1);
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
