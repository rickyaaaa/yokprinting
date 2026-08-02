<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Provision expense permissions as deployment data, without requiring a seeder.
     */
    public function up(): void
    {
        $now = now();

        $definitions = [
            'view' => ['Lihat Pengeluaran', 'low', 70],
            'create' => ['Tambah Pengeluaran', 'medium', 71],
            'update' => ['Ubah Pengeluaran', 'medium', 72],
            'delete' => ['Hapus Pengeluaran', 'high', 73],
        ];

        foreach ($definitions as $action => [$name, $riskLevel, $sortOrder]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => "expense.{$action}"],
                [
                    'name' => $name,
                    'module' => 'expense',
                    'action' => $action,
                    'guard_name' => 'web',
                    'description' => "Izin {$name}.",
                    'risk_level' => $riskLevel,
                    'is_system' => true,
                    'sort_order' => $sortOrder,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $financeRoleId = DB::table('roles')->where('code', 'finance_admin')->value('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('code', array_map(fn (string $action): string => "expense.{$action}", array_keys($definitions)))
            ->pluck('id');

        if ($financeRoleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $financeRoleId, 'permission_id' => $permissionId],
                    ['constraints' => null, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    /**
     * Remove only the deployment data introduced by this migration.
     */
    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('code', ['expense.view', 'expense.create', 'expense.update', 'expense.delete'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
