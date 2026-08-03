<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
        ]);

        $this->call(RolePermissionSeeder::class);
        $this->call(ThemeAndDefaultSettingsSeeder::class);
        $this->call(ProductCatalogSeeder::class);
        $this->call(SalesReportDemoSeeder::class);
    }
}
