<?php

namespace Tests\Feature;

use App\Jobs\MarkOverdueInvoicesJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Security\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MarkOverdueInvoicesJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_job_marks_due_unpaid_and_partial_invoices_as_overdue(): void
    {
        Carbon::setTestNow('2026-07-24 08:00:00');

        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        $unpaidPast = $this->createInvoice($customer, 'INV-2026-0101', now()->subDays(3), Invoice::PAYMENT_UNPAID);
        $partialPast = $this->createInvoice($customer, 'INV-2026-0102', now()->subDay(), Invoice::PAYMENT_PARTIAL);
        $paidPast = $this->createInvoice($customer, 'INV-2026-0103', now()->subDays(4), Invoice::PAYMENT_PAID);
        $futureUnpaid = $this->createInvoice($customer, 'INV-2026-0104', now()->addDay(), Invoice::PAYMENT_UNPAID);
        $cancelledPast = $this->createInvoice($customer, 'INV-2026-0105', now()->subDays(2), Invoice::PAYMENT_UNPAID, Invoice::STATUS_CANCELLED);
        $alreadyOverdue = $this->createInvoice($customer, 'INV-2026-0106', now()->subDays(5), Invoice::PAYMENT_OVERDUE);

        $markedCount = (new MarkOverdueInvoicesJob)->handle(app(ActivityLogger::class));

        $this->assertSame(2, $markedCount);
        $this->assertSame(Invoice::PAYMENT_OVERDUE, $unpaidPast->refresh()->payment_status);
        $this->assertSame(Invoice::PAYMENT_OVERDUE, $partialPast->refresh()->payment_status);
        $this->assertSame(Invoice::PAYMENT_PAID, $paidPast->refresh()->payment_status);
        $this->assertSame(Invoice::PAYMENT_UNPAID, $futureUnpaid->refresh()->payment_status);
        $this->assertSame(Invoice::PAYMENT_UNPAID, $cancelledPast->refresh()->payment_status);
        $this->assertSame(Invoice::PAYMENT_OVERDUE, $alreadyOverdue->refresh()->payment_status);

        $log = ActivityLog::query()
            ->where('module', 'invoice')
            ->where('action', 'overdue_check')
            ->firstOrFail();

        $this->assertSame(2, $log->metadata['marked_count']);
        $this->assertEqualsCanonicalizing([$unpaidPast->id, $partialPast->id], $log->metadata['invoice_ids']);
        $this->assertSame(ActivityLog::RISK_MEDIUM, $log->risk_level);
    }

    public function test_job_is_idempotent_after_invoices_are_marked(): void
    {
        Carbon::setTestNow('2026-07-24 08:00:00');

        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        $this->createInvoice($customer, 'INV-2026-0201', now()->subDays(3), Invoice::PAYMENT_UNPAID);

        $firstRun = (new MarkOverdueInvoicesJob)->handle(app(ActivityLogger::class));
        $secondRun = (new MarkOverdueInvoicesJob)->handle(app(ActivityLogger::class));

        $this->assertSame(1, $firstRun);
        $this->assertSame(0, $secondRun);
        $this->assertSame(2, ActivityLog::query()->where('action', 'overdue_check')->count());
        $this->assertSame(0, ActivityLog::query()->where('action', 'overdue_check')->latest('id')->first()->metadata['marked_count']);
    }

    private function createInvoice(
        Customer $customer,
        string $invoiceNumber,
        Carbon $dueDate,
        string $paymentStatus,
        string $status = Invoice::STATUS_SENT,
    ): Invoice {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $dueDate->copy()->subDays(14)->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_amount' => 1000000,
        ]);
    }
}
