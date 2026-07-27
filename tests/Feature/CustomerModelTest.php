<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_table_contains_profile_and_filter_fields(): void
    {
        foreach ([
            'code',
            'name',
            'email',
            'phone',
            'address',
            'city',
            'province',
            'postal_code',
            'tax_number',
            'status',
            'notes',
            'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('customers', $column),
                "Expected customers table to contain [{$column}] column.",
            );
        }

        $this->assertFalse(Schema::hasColumn('customers', 'segment'));
    }

    public function test_customer_can_be_created_with_defaults_profile_fields_and_soft_deleted(): void
    {
        $customer = Customer::query()->create([
            'code' => 'CUS-900',
            'name' => 'PT Arunika Studio',
            'email' => 'finance@arunika.example',
            'phone' => '+62 21 900 1122',
            'address' => 'Jl. Mawar No. 12',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'postal_code' => '12730',
            'tax_number' => '01.900.112.2-003.000',
            'notes' => 'Prioritas follow-up invoice desain.',
        ]);

        $this->assertSame(Customer::STATUS_ACTIVE, $customer->status);
        $this->assertSame('PA', $customer->initials());
        $this->assertTrue(Customer::query()->selectable()->whereKey($customer)->exists());
        $this->assertSame(Customer::ACTIVITY_NEVER_ORDERED, $customer->activity_status);

        $customer->delete();

        $this->assertSoftDeleted($customer);
    }
}
