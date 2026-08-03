<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 80)->nullable()->after('name');
        });

        DB::table('users')
            ->select(['id', 'name', 'email'])
            ->orderBy('id')
            ->each(function (object $user): void {
                $source = Str::before((string) $user->email, '@') ?: (string) $user->name;
                $base = (string) Str::of($source)
                    ->lower()
                    ->replaceMatches('/[^a-z0-9_]+/', '_')
                    ->trim('_')
                    ->limit(64, '');
                $base = $base !== '' ? $base : "user_{$user->id}";
                $username = $base;
                $suffix = 1;

                while (DB::table('users')->where('username', $username)->exists()) {
                    $username = Str::limit($base, 70, '').'_'.$suffix++;
                }

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 80)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
