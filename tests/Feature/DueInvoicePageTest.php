<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DueInvoicePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-18 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_view_due_invoices_page(): void
    {
        $this->get(route('notifications.due-invoices.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_payment_permission_is_forbidden(): void
    {
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->get(route('notifications.due-invoices.index'))
            ->assertForbidden();
    }

    public function test_due_invoices_page_renders_real_invoice_data_not_the_old_mock(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $customer = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);

        $this->createInvoice($customer, 'INV-2026-0201', now()->subDays(3), 5600000);
        $this->createInvoice($customer, 'INV-2026-0202', now()->addDay(), 18450000);
        $this->createInvoice($customer, 'INV-2026-0203', now()->addDays(20), 9200000);
        $this->createInvoice($customer, 'INV-2026-0204', now()->addDays(90), 4000000);

        $response = $this->get(route('notifications.due-invoices.index'))
            ->assertOk()
            ->assertSee('INV-2026-0201')
            ->assertSee('INV-2026-0202')
            ->assertSee('INV-2026-0203')
            ->assertDontSee('INV-2026-0204')
            ->assertDontSee('PT Bumi Lestari')
            ->assertSee('Tandai follow-up')
            ->assertSee('Belum ada follow-up');

        $response->assertViewHas('dueInvoices', function ($dueInvoices): bool {
            return $dueInvoices->count() === 3;
        });

        $response->assertViewHas('summaryCards', function ($summaryCards): bool {
            return collect($summaryCards)->firstWhere('label', 'Overdue')['value'] === '1';
        });
    }

    private function createInvoice(Customer $customer, string $invoiceNumber, Carbon $dueDate, int $totalAmount): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $invoiceNumber,
            'issue_date' => $dueDate->copy()->subDays(14)->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => $totalAmount,
        ]);
    }
}
