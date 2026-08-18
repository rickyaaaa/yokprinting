<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOwner;
use Tests\TestCase;

class RecentActivitiesApiTest extends TestCase
{
    use ActsAsOwner;
    use RefreshDatabase;

    public function test_recent_activities_are_empty_instead_of_using_demo_records(): void
    {
        $this->getJson(route('api.dashboard.activities'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(0, 'data');
    }

    public function test_recent_activities_come_from_database_and_filter_by_type(): void
    {
        $customer = Customer::query()->create(['name' => 'PT Aktivitas', 'email' => 'aktivitas@example.test']);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-ACT-001',
            'issue_date' => today(),
            'due_date' => today()->addWeek(),
            'total_amount' => 1000000,
        ]);
        Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'PAY-ACT-001',
            'payment_date' => today(),
            'method' => Payment::METHOD_CASH,
            'amount' => 250000,
            'status' => Payment::STATUS_VERIFIED,
        ]);

        $this->getJson(route('api.dashboard.activities'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->getJson(route('api.dashboard.activities', ['type' => 'payment']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'payment')
            ->assertJsonPath('data.0.description', 'PT Aktivitas membayar Rp250.000 untuk INV-ACT-001.');
    }
}
