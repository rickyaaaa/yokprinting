<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class CustomerCrudApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_customer_can_be_created_and_shown(): void
    {
        $payload = [
            'code' => 'CUS-910',
            'name' => 'PT Mentari Nusantara',
            'email' => 'finance@mentari.example.com',
            'phone' => '+62 21 9911 2211',
            'address' => 'Jl. Radio Dalam No. 10',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12140',
            'tax_number' => '09.910.221.1-011.000',
            'notes' => 'PIC finance minta invoice dikirim setiap Senin.',
        ];

        $createResponse = $this->postJson(route('api.customers.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.code', 'CUS-910')
            ->assertJsonPath('data.name', 'PT Mentari Nusantara')
            ->assertJsonPath('data.activity_status', Customer::ACTIVITY_NEVER_ORDERED)
            ->assertJsonPath('data.status', Customer::STATUS_ACTIVE)
            ->assertJsonPath('data.initials', 'PM')
            ->assertJsonPath('redirect_url', route('customers.index', ['created' => 'CUS-910']));

        $customerId = $createResponse->json('data.id');

        $this->getJson(route('api.customers.show', $customerId))
            ->assertOk()
            ->assertJsonPath('data.email', 'finance@mentari.example.com')
            ->assertJsonPath('data.city', 'Jakarta Selatan')
            ->assertJsonPath('data.notes', 'PIC finance minta invoice dikirim setiap Senin.');
    }

    public function test_newly_created_customer_appears_on_customer_index(): void
    {
        $response = $this->postJson(route('api.customers.store'), [
            'name' => 'PT Pelanggan Baru',
            'email' => 'pelanggan-baru@example.test',
            'phone' => '081234567890',
            'address' => 'Jl. Baru No. 1',
            'city' => 'Tangerang',
        ])->assertCreated();

        $customerCode = $response->json('data.code');

        $this->actingAs(User::factory()->create())
            ->get(route('customers.index', ['created' => $customerCode]))
            ->assertOk()
            ->assertSee('Pelanggan berhasil ditambahkan.')
            ->assertSee($customerCode)
            ->assertSee('PT Pelanggan Baru')
            ->assertSee('pelanggan-baru@example.test')
            ->assertSee('Tangerang');
    }

    public function test_customer_code_is_generated_when_omitted(): void
    {
        Customer::query()->create([
            'code' => 'CUS-009',
            'name' => 'PT Existing Customer',
            'email' => 'existing@example.com',
            'address' => 'Jl. Existing No. 1',
            'city' => 'Tangerang',
        ]);

        $this->postJson(route('api.customers.store'), [
            'name' => 'YokPrinting Baru',
            'email' => 'baru@yokprinting.test',
            'phone' => '+62 812 1000 2000',
            'address' => 'Jl. Karyawan II',
            'city' => 'Tangerang',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'CUS-010')
            ->assertJsonPath('data.name', 'YokPrinting Baru');

        $this->assertDatabaseHas('customers', [
            'code' => 'CUS-010',
            'email' => 'baru@yokprinting.test',
        ]);
    }

    public function test_customer_can_be_updated_and_soft_deleted(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-911',
            'name' => 'CV Lama Kreatif',
            'email' => 'billing@lama.example.com',
            'address' => 'Jl. Lama No. 1',
            'city' => 'Bandung',
        ]);

        $this->patchJson(route('api.customers.update', $customer), [
            'name' => 'CV Baru Kreatif',
            'status' => Customer::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'CV Baru Kreatif')
            ->assertJsonPath('data.status', Customer::STATUS_INACTIVE);

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.customers.destroy', $customer))
            ->assertOk()
            ->assertJsonPath('data.history_preserved', true);

        $this->assertSoftDeleted($customer);
    }

    public function test_customer_payload_is_validated(): void
    {
        Customer::query()->create([
            'code' => 'CUS-912',
            'name' => 'PT Sudah Ada',
            'email' => 'finance@sudahada.example.com',
            'address' => 'Jl. Ada No. 1',
            'city' => 'Jakarta',
        ]);

        $this->postJson(route('api.customers.store'), [
            'code' => 'CUS-912',
            'name' => '',
            'email' => 'not-an-email',
            'address' => '',
            'city' => '',
            'status' => 'archived',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'email', 'address', 'city', 'status']);
    }
}
