<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCustomerOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_customers_are_returned_in_name_order(): void
    {
        Customer::query()->create([
            'code' => 'CUS-002',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
            'phone' => '+62 21 555 0198',
            'address' => 'Jl. Jenderal Sudirman No. 88, Jakarta Selatan',
        ]);
        Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'CV Arunika Kreatif',
            'email' => 'halo@arunikakreatif.id',
            'phone' => '+62 812 3388 1042',
            'address' => 'Jl. Ciumbuleuit No. 42, Bandung',
        ]);
        Customer::query()->create([
            'code' => 'CUS-003',
            'name' => 'Pelanggan Nonaktif',
            'status' => Customer::STATUS_INACTIVE,
        ]);

        $this->getJson(route('api.customers.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'CV Arunika Kreatif')
            ->assertJsonPath('data.0.initials', 'CA')
            ->assertJsonPath('data.1.name', 'PT Sinar Nusantara')
            ->assertJsonPath('data.1.initials', 'PS')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonMissing(['name' => 'Pelanggan Nonaktif']);
    }

    public function test_customer_options_can_be_searched_and_filtered_by_selected_ids(): void
    {
        $sinar = Customer::query()->create([
            'code' => 'CUS-001',
            'name' => 'PT Sinar Nusantara',
            'email' => 'finance@sinarnusantara.co.id',
        ]);
        $arunika = Customer::query()->create([
            'code' => 'CUS-002',
            'name' => 'CV Arunika Kreatif',
            'email' => 'halo@arunikakreatif.id',
        ]);

        $this->getJson(route('api.customers.index', ['search' => 'arunika']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $arunika->id);

        $this->getJson(route('api.customers.index', ['ids' => [$sinar->id]]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sinar->id);
    }
}
