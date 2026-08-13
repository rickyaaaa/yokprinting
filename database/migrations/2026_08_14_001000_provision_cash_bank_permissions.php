<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $definitions = [
            'view' => ['Lihat Kas & Bank', 'low', 80],
            'create' => ['Tambah Transaksi Kas & Bank', 'medium', 81],
            'update' => ['Ubah Kas & Bank', 'medium', 82],
            'delete' => ['Batalkan Transaksi Kas & Bank', 'high', 83],
        ];

        foreach ($definitions as $action => [$name, $risk, $sort]) {
            $code = "cash_bank.{$action}";
            $attributes = [
                'name' => $name,
                'module' => 'cash_bank',
                'action' => $action,
                'guard_name' => 'web',
                'description' => "Izin {$name}.",
                'risk_level' => $risk,
                'is_system' => true,
                'sort_order' => $sort,
                'deleted_at' => null,
                'updated_at' => $now,
            ];
            $permission = DB::table('permissions')->where('code', $code)->first();

            if ($permission) {
                DB::table('permissions')->where('id', $permission->id)->update($attributes);
            } else {
                DB::table('permissions')->insert(['code' => $code, ...$attributes, 'created_at' => $now]);
            }
        }

        $financeRoleId = DB::table('roles')->where('code', 'finance_admin')->value('id');

        if ($financeRoleId) {
            $permissionIds = DB::table('permissions')
                ->whereIn('code', array_map(fn (string $action): string => "cash_bank.{$action}", array_keys($definitions)))
                ->whereNull('deleted_at')
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $financeRoleId,
                    'permission_id' => $permissionId,
                    'constraints' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permission deployment data is intentionally preserved on rollback.
    }
};
