<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BrowserSessionApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_web_login_session_can_download_invoice_pdf_and_open_sales_report_api(): void
    {
        User::factory()->create([
            'username' => 'browser-session-owner',
            'password' => Hash::make('password'),
            'role' => User::ROLE_OWNER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $customer = Customer::query()->create([
            'name' => 'PT Session Browser',
            'email' => 'browser-session@example.test',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-SESSION-001',
            'issue_date' => today(),
            'due_date' => today()->addWeek(),
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 1000000,
        ]);

        $loginPage = $this->get(route('login'))->assertOk();
        preg_match('/name="csrf-token"[^>]*content="([^"]+)"/', $loginPage->getContent(), $matches);

        $this->withHeader('X-CSRF-TOKEN', html_entity_decode($matches[1]))
            ->post(route('login.store'), [
                'username' => 'browser-session-owner',
                'password' => 'password',
            ])
            ->assertRedirect(route('dashboard'));

        $this->get(route('api.invoices.pdf.download', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->getJson(route('api.reports.sales.summary'))
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
