<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_supplier_pages(): void
    {
        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
        $this->get(route('suppliers.create'))->assertRedirect(route('login'));
    }

    public function test_user_without_product_permission_is_forbidden(): void
    {
        $role = Role::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role->code]));

        $this->get(route('suppliers.index'))->assertForbidden();
        $this->get(route('suppliers.create'))->assertForbidden();
    }

    public function test_owner_can_view_supplier_index_and_form_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]));

        $this->get(route('suppliers.index'))->assertOk()->assertSee('Supplier');
        $this->get(route('suppliers.create'))->assertOk()->assertSee('Tambah Supplier');

        $supplier = Supplier::query()->create([
            'code' => 'SUP-001',
            'name' => 'PT ABC Supplier',
        ]);

        $this->get(route('suppliers.edit', $supplier))->assertOk()->assertSee('Ubah Supplier');
    }
}
