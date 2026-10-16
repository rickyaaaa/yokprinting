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

    public function test_dashboard_whatsapp_reminder_uses_the_real_web_session_and_csrf_token(): void
    {
        $this->app['env'] = 'local';

        $customer = Customer::query()->create([
            'code' => 'CUS-WA-SESSION',
            'name' => 'PT WhatsApp Session',
            'phone' => '0812-3456-7890',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-WA-SESSION-001',
            'issue_date' => today(),
            'due_date' => today()->addDays(3),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 9500000,
        ]);

        $endpoint = route('api.invoices.send-whatsapp', $invoice);
        $this->postJson($endpoint, ['purpose' => 'reminder'])->assertUnauthorized();

        $user = User::factory()->create([
            'username' => 'dashboard-whatsapp-owner',
            'password' => Hash::make('session-password'),
            'role' => User::ROLE_OWNER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $loginPage = $this->get(route('login'))->assertOk();

        $this->withHeader('X-CSRF-TOKEN', $this->csrfTokenFromMeta($loginPage->getContent()))
            ->post(route('login.store'), [
                'username' => $user->username,
                'password' => 'session-password',
            ])
            ->assertRedirect(route('dashboard'));

        $dashboard = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('name="csrf-token"', false)
            ->assertSee($endpoint, false);
        $dashboardToken = $this->csrfTokenFromMeta($dashboard->getContent());

        $this->post($endpoint, ['purpose' => 'reminder'], ['Accept' => 'application/json'])
            ->assertStatus(419);

        $this->post($endpoint, ['purpose' => 'reminder'], [
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $dashboardToken,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Pengingat WhatsApp siap dibuka.')
            ->assertJsonPath('data.recipient', '6281234567890')
            ->assertJsonPath('data.purpose', 'reminder')
            ->assertJsonPath('data.status', Invoice::STATUS_SENT)
            ->assertJsonPath('data.whatsapp_url', fn (string $url): bool => str_starts_with(
                $url,
                'https://wa.me/6281234567890?text=',
            ));
    }

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

    public function test_notification_read_uses_real_web_session_and_requires_csrf(): void
    {
        $this->app['env'] = 'local';

        $user = User::factory()->create([
            'username' => 'notification-session-owner',
            'password' => Hash::make('session-password'),
            'role' => User::ROLE_OWNER,
            'status' => User::STATUS_ACTIVE,
        ]);
        $customer = Customer::query()->create([
            'code' => 'CUS-NOTIFICATION-SESSION',
            'name' => 'PT Notification Session',
        ]);
        Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-NOTIFICATION-SESSION-001',
            'issue_date' => today(),
            'due_date' => today()->addDays(2),
            'status' => Invoice::STATUS_SENT,
            'payment_status' => Invoice::PAYMENT_UNPAID,
            'total_amount' => 1500000,
        ]);

        $endpoint = route('api.notifications.due-invoices.read');
        $this->postJson($endpoint)->assertUnauthorized();

        $loginPage = $this->get(route('login'))->assertOk();
        $this->withHeader('X-CSRF-TOKEN', $this->csrfTokenFromMeta($loginPage->getContent()))
            ->post(route('login.store'), [
                'username' => $user->username,
                'password' => 'session-password',
            ])
            ->assertRedirect(route('dashboard'));

        $dashboard = $this->get(route('dashboard'))->assertOk();
        $dashboardToken = $this->csrfTokenFromMeta($dashboard->getContent());

        $this->post($endpoint, [], ['Accept' => 'application/json'])
            ->assertStatus(419);

        $this->post($endpoint, [], [
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $dashboardToken,
        ])
            ->assertOk()
            ->assertJsonPath('data.marked_count', 1)
            ->assertJsonPath('data.unread_count', 0);
    }

    private function csrfTokenFromMeta(string $html): string
    {
        preg_match('/name="csrf-token"[^>]*content="([^"]+)"/', $html, $matches);

        return html_entity_decode($matches[1] ?? '');
    }
}
