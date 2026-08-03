<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UsernameMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_unique_usernames_before_making_the_column_required(): void
    {
        $migration = require database_path('migrations/2026_08_03_000000_add_username_to_users_table.php');
        $migration->down();
        $now = now();

        DB::table('users')->insert([
            [
                'name' => 'Owner Satu',
                'email' => 'owner@example.test',
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Owner Dua',
                'email' => 'owner@second.example',
                'password' => Hash::make('password'),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'username'));
        $this->assertSame(
            ['owner', 'owner_1'],
            DB::table('users')->orderBy('id')->pluck('username')->all(),
        );
    }
}
