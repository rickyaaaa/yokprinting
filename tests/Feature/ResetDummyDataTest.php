<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetDummyDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_dummy_data_command_clears_transactional_data_and_preserves_products_and_users(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'sku' => 'PROD-001',
            'name' => 'Testing Product',
            'unit' => 'Pcs',
            'selling_price' => 10000,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Testing Customer',
            'phone' => '0812345678',
        ]);
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-9999',
            'issue_date' => '2026-09-21',
            'due_date' => '2026-09-28',
            'subtotal' => 10000,
            'total_amount' => 10000,
        ]);

        $this->artisan('app:reset-dummy-data --force')
            ->assertSuccessful()
            ->expectsOutputToContain('Pembersihan data selesai!');

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
