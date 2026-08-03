<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseSessionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_web_login_session_authorizes_expense_and_profit_loss_apis_and_guest_is_rejected(): void
    {
        Storage::fake('expense_proofs');

        $this->getJson(route('api.expenses.index'))->assertUnauthorized();
        $this->getJson(route('api.reports.profit-loss.show'))->assertUnauthorized();

        $role = Role::factory()->create(['code' => Role::CODE_FINANCE_ADMIN]);
        $reportPermission = Permission::query()->create([
            'name' => 'Lihat Laporan',
            'code' => 'report.view',
            'module' => 'report',
            'action' => 'view',
        ]);
        $role->permissions()->syncWithoutDetaching($reportPermission);
        $user = User::factory()->create([
            'role' => Role::CODE_FINANCE_ADMIN,
            'password' => 'session-password',
        ]);

        $loginToken = $this->csrfTokenFrom($this->get(route('login'))->assertOk()->getContent());
        $this->post(route('login.store'), [
            '_token' => $loginToken,
            'email' => $user->email,
            'password' => 'session-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->getJson(route('api.expenses.index'))->assertOk();
        $this->getJson(route('api.reports.profit-loss.show'))->assertOk();
    }

    public function test_expense_mutation_requires_csrf_from_the_authenticated_web_session(): void
    {
        Storage::fake('expense_proofs');
        $this->app['env'] = 'local';

        $user = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'password' => 'session-password',
        ]);
        $loginToken = $this->csrfTokenFrom($this->get(route('login'))->assertOk()->getContent());

        $this->post(route('login.store'), [
            '_token' => $loginToken,
            'email' => $user->email,
            'password' => 'session-password',
        ])->assertRedirect(route('dashboard'));

        $payload = $this->validPayload();
        $this->post(route('api.expenses.store'), $payload, ['Accept' => 'application/json'])
            ->assertStatus(419);

        $expensePage = $this->get(route('expenses.index'))->assertOk();
        $csrfToken = $this->csrfTokenFromMeta($expensePage->getContent());
        $createResponse = $this->post(route('api.expenses.store'), $this->validPayload(), [
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $csrfToken,
        ])->assertCreated();

        $expense = Expense::query()->findOrFail($createResponse->json('data.id'));
        $updatePayload = [
            '_method' => 'PATCH',
            'version' => $expense->version,
            'description' => 'Perubahan melalui session browser.',
        ];

        $this->post(route('api.expenses.update', $expense), $updatePayload, [
            'Accept' => 'application/json',
        ])->assertStatus(419);
        $this->assertSame('Produksi pesanan.', $expense->refresh()->description);

        $this->post(route('api.expenses.update', $expense), $updatePayload, [
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $csrfToken,
        ])->assertOk();
        $this->assertSame('Perubahan melalui session browser.', $expense->refresh()->description);

        $this->delete(route('api.expenses.destroy', $expense), [], [
            'Accept' => 'application/json',
        ])->assertStatus(419);
        $this->assertNotSoftDeleted('expenses', ['id' => $expense->getKey()]);

        $this->delete(route('api.expenses.destroy', $expense), [], [
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $csrfToken,
        ])->assertNoContent();

        $this->assertDatabaseCount('expenses', 1);
        $this->assertSoftDeleted('expenses', ['id' => $expense->getKey()]);
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_PRODUCTION,
            'amount' => 125000,
            'description' => 'Produksi pesanan.',
            'recipient' => 'Vendor Produksi',
            'payment_method' => 'Transfer bank',
            'proof_payment' => UploadedFile::fake()->image('bukti.jpg'),
        ];
    }

    private function csrfTokenFrom(string $html): string
    {
        preg_match('/name="_token"[^>]*value="([^"]+)"/', $html, $matches);

        return html_entity_decode($matches[1] ?? '');
    }

    private function csrfTokenFromMeta(string $html): string
    {
        preg_match('/name="csrf-token"[^>]*content="([^"]+)"/', $html, $matches);

        return html_entity_decode($matches[1] ?? '');
    }
}
