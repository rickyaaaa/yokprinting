<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_expense_pages_or_api(): void
    {
        $owner = User::factory()->create();
        $expense = Expense::factory()->create(['created_by' => $owner->id]);

        $this->getJson(route('api.expenses.index'))->assertUnauthorized();
        $this->postJson(route('api.expenses.store'), [])->assertUnauthorized();
        $this->getJson(route('api.expenses.show', $expense))->assertUnauthorized();
        $this->patchJson(route('api.expenses.update', $expense), [])->assertUnauthorized();
        $this->deleteJson(route('api.expenses.destroy', $expense))->assertUnauthorized();
        $this->getJson(route('api.expenses.proof.download', $expense))->assertUnauthorized();
        $this->get(route('expenses.index'))->assertRedirect(route('login'));
        $this->get(route('expenses.create'))->assertRedirect(route('login'));
        $this->get(route('expenses.edit', $expense))->assertRedirect(route('login'));
    }

    public function test_user_without_expense_permissions_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);
        $expense = Expense::factory()->create(['created_by' => $user->id]);
        $this->actingAs($user);

        $this->getJson(route('api.expenses.index'))->assertForbidden();
        $this->postJson(route('api.expenses.store'), [])->assertForbidden();
        $this->getJson(route('api.expenses.show', $expense))->assertForbidden();
        $this->patchJson(route('api.expenses.update', $expense), [])->assertForbidden();
        $this->deleteJson(route('api.expenses.destroy', $expense))->assertForbidden();
        $this->getJson(route('api.expenses.proof.download', $expense))->assertForbidden();
        $this->get(route('expenses.index'))->assertForbidden();
    }

    public function test_expense_permissions_are_enforced_per_crud_action(): void
    {
        Storage::fake('expense_proofs');
        $creator = User::factory()->create();
        $expense = Expense::factory()->create([
            'created_by' => $creator->id,
            'proof_path' => 'expense-proofs/authorized.pdf',
        ]);
        Storage::disk('expense_proofs')->put($expense->proof_path, 'proof');

        $viewer = $this->userWithPermissions(['expense.view']);
        $this->actingAs($viewer);
        $this->getJson(route('api.expenses.index'))->assertOk();
        $this->getJson(route('api.expenses.show', $expense))->assertOk();
        $this->get(route('api.expenses.proof.download', $expense))->assertOk();
        $this->get(route('expenses.index'))->assertOk();
        $this->postJson(route('api.expenses.store'), [])->assertForbidden();
        $this->patchJson(route('api.expenses.update', $expense), [])->assertForbidden();
        $this->deleteJson(route('api.expenses.destroy', $expense))->assertForbidden();

        $creatorWithPermission = $this->userWithPermissions(['expense.create']);
        $this->actingAs($creatorWithPermission);
        $this->post(route('api.expenses.store'), $this->validPayload(), ['Accept' => 'application/json'])
            ->assertCreated();
        $this->get(route('expenses.create'))->assertOk();
        $this->getJson(route('api.expenses.index'))->assertForbidden();

        $updater = $this->userWithPermissions(['expense.update']);
        $this->actingAs($updater);
        $this->patchJson(route('api.expenses.update', $expense), [
            'version' => $expense->version,
            'description' => 'Diperbarui oleh pengguna berizin.',
        ])
            ->assertOk();
        $this->get(route('expenses.edit', $expense))->assertOk();
        $this->deleteJson(route('api.expenses.destroy', $expense))->assertForbidden();

        $deleter = $this->userWithPermissions(['expense.delete']);
        $this->actingAs($deleter);
        $this->deleteJson(route('api.expenses.destroy', $expense))->assertNoContent();
    }

    public function test_finance_role_seeder_grants_only_requested_expense_crud_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $financeRole = Role::query()->where('code', Role::CODE_FINANCE_ADMIN)->firstOrFail();
        $codes = $financeRole->permissions()->where('module', Permission::MODULE_EXPENSE)->pluck('code')->sort()->values()->all();

        $this->assertSame([
            'expense.create',
            'expense.delete',
            'expense.update',
            'expense.view',
        ], $codes);
        $this->assertDatabaseMissing('permissions', ['code' => 'expense.export']);
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::factory()->create();

        foreach ($permissionCodes as $permissionCode) {
            $permission = Permission::query()->where('code', $permissionCode)->firstOrFail();
            $role->permissions()->syncWithoutDetaching($permission);
        }

        return User::factory()->create(['role' => $role->code]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'expense_date' => '2026-08-02',
            'category' => Expense::CATEGORY_SHOPPING,
            'amount' => 45000,
            'description' => 'Belanja kebutuhan toko.',
            'recipient' => 'Toko ATK',
            'payment_method' => 'Tunai',
            'proof_payment' => UploadedFile::fake()->image('bukti.jpg'),
        ];
    }
}
