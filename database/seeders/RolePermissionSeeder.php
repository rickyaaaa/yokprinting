<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed default roles and permission matrix used by the admin dashboard.
     */
    public function run(): void
    {
        $modules = [
            Permission::MODULE_DASHBOARD => 'Dashboard',
            Permission::MODULE_INVOICE => 'Invoice',
            Permission::MODULE_CUSTOMER => 'Customer',
            Permission::MODULE_PRODUCT => 'Produk',
            Permission::MODULE_PAYMENT => 'Pembayaran',
            Permission::MODULE_REPORT => 'Laporan',
            Permission::MODULE_EXPENSE => 'Pengeluaran',
            Permission::MODULE_CASH_BANK => 'Kas & Bank',
            Permission::MODULE_SETTING => 'Pengaturan',
            Permission::MODULE_ROLE => 'Role',
            Permission::MODULE_PURCHASE_ORDER => 'Purchase Order',
            Permission::MODULE_GOODS_RECEIPT => 'Penerimaan Barang',
        ];

        $actions = [
            'view' => ['label' => 'Lihat', 'risk' => Permission::RISK_LOW],
            'create' => ['label' => 'Tambah', 'risk' => Permission::RISK_MEDIUM],
            'update' => ['label' => 'Ubah', 'risk' => Permission::RISK_MEDIUM],
            'delete' => ['label' => 'Hapus', 'risk' => Permission::RISK_HIGH],
            'export' => ['label' => 'Export', 'risk' => Permission::RISK_MEDIUM],
            'submit' => ['label' => 'Ajukan', 'risk' => Permission::RISK_MEDIUM],
            'approve' => ['label' => 'Setujui', 'risk' => Permission::RISK_HIGH],
            'cancel' => ['label' => 'Batalkan', 'risk' => Permission::RISK_HIGH],
            'post' => ['label' => 'Posting', 'risk' => Permission::RISK_HIGH],
        ];

        $sort = 10;

        foreach ($modules as $module => $moduleLabel) {
            $moduleActions = match ($module) {
                Permission::MODULE_EXPENSE, Permission::MODULE_CASH_BANK => ['view', 'create', 'update', 'delete'],
                Permission::MODULE_PURCHASE_ORDER => ['view', 'create', 'update', 'submit', 'approve', 'cancel'],
                Permission::MODULE_GOODS_RECEIPT => ['view', 'create', 'post', 'cancel'],
                default => ['view', 'create', 'update', 'delete', 'export'],
            };

            foreach ($moduleActions as $action) {
                $meta = $actions[$action];
                Permission::query()->updateOrCreate(
                    ['code' => "{$module}.{$action}"],
                    [
                        'name' => "{$meta['label']} {$moduleLabel}",
                        'module' => $module,
                        'action' => $action,
                        'description' => "Izin {$meta['label']} modul {$moduleLabel}.",
                        'risk_level' => $meta['risk'],
                        'is_system' => true,
                        'sort_order' => $sort++,
                    ],
                );
            }
        }

        $roles = [
            Role::CODE_OWNER => [
                'name' => 'Owner',
                'description' => 'Role tertinggi dengan akses pengaturan dan permission.',
                'is_system' => true,
                'sort_order' => 10,
                'permissions' => Permission::query()->pluck('code')->all(),
            ],
            Role::CODE_FINANCE_ADMIN => [
                'name' => 'Admin Finance',
                'description' => 'Mengelola invoice, pengeluaran, pembayaran, dan laporan finance.',
                'is_system' => true,
                'sort_order' => 20,
                'permissions' => [
                    'dashboard.view',
                    'invoice.view',
                    'invoice.create',
                    'invoice.update',
                    'invoice.export',
                    'customer.view',
                    'customer.create',
                    'customer.update',
                    'product.view',
                    'payment.view',
                    'payment.create',
                    'payment.update',
                    'payment.export',
                    'report.view',
                    'report.export',
                    'expense.view',
                    'expense.create',
                    'expense.update',
                    'expense.delete',
                    'cash_bank.view',
                    'cash_bank.create',
                    'cash_bank.update',
                    'cash_bank.delete',
                    'purchase_order.view',
                    'purchase_order.create',
                    'purchase_order.update',
                    'purchase_order.submit',
                    'purchase_order.approve',
                    'purchase_order.cancel',
                    'goods_receipt.view',
                    'goods_receipt.create',
                    'goods_receipt.post',
                    'goods_receipt.cancel',
                ],
            ],
            Role::CODE_OPERATIONS => [
                'name' => 'Operasional',
                'description' => 'Mengelola pelanggan, produk, dan progres operasional invoice.',
                'is_system' => true,
                'sort_order' => 30,
                'permissions' => [
                    'dashboard.view',
                    'invoice.view',
                    'invoice.update',
                    'customer.view',
                    'product.view',
                    'product.update',
                    'payment.view',
                    'purchase_order.view',
                    'purchase_order.create',
                    'purchase_order.update',
                    'purchase_order.submit',
                    'goods_receipt.view',
                    'goods_receipt.create',
                    'goods_receipt.post',
                ],
            ],
            Role::CODE_VIEWER => [
                'name' => 'Viewer',
                'description' => 'Akses baca untuk dashboard dan laporan.',
                'is_system' => true,
                'sort_order' => 40,
                'permissions' => [
                    'dashboard.view',
                    'invoice.view',
                    'customer.view',
                    'product.view',
                    'payment.view',
                    'report.view',
                    'purchase_order.view',
                    'goods_receipt.view',
                ],
            ],
        ];

        foreach ($roles as $code => $payload) {
            $permissions = $payload['permissions'];
            unset($payload['permissions']);

            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                [
                    ...$payload,
                    'status' => Role::STATUS_ACTIVE,
                    'guard_name' => 'web',
                ],
            );

            $permissionIds = Permission::query()
                ->whereIn('code', $permissions)
                ->pluck('id')
                ->all();

            $role->syncPermissions($permissionIds);
        }
    }
}
