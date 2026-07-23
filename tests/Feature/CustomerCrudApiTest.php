<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_be_created_and_shown(): void
    {
        $payload = [
            'code' => 'CUS-910',
            'name' => 'PT Mentari Nusantara',
            'segment' => 'Corporate',
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
            ->assertJsonPath('data.segment', 'Corporate')
            ->assertJsonPath('data.status', Customer::STATUS_ACTIVE)
            ->assertJsonPath('data.initials', 'PM');

        $customerId = $createResponse->json('data.id');

        $this->getJson(route('api.customers.show', $customerId))
            ->assertOk()
            ->assertJsonPath('data.email', 'finance@mentari.example.com')
            ->assertJsonPath('data.city', 'Jakarta Selatan')
            ->assertJsonPath('data.notes', 'PIC finance minta invoice dikirim setiap Senin.');
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
            'segment' => 'UMKM',
            'status' => Customer::STATUS_INACTIVE,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'CV Baru Kreatif')
            ->assertJsonPath('data.segment', 'UMKM')
            ->assertJsonPath('data.status', Customer::STATUS_INACTIVE);

        $this->deleteJson(route('api.customers.destroy', $customer))
            ->assertNoContent();

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
