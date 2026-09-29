<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDummyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-dummy-data {--force : Force execution without confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely wipe dummy transactional, customer, supplier, and inventory data while preserving products, categories, users, company profile, bank accounts, and application settings.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('PERINGATAN: Perintah ini akan MENGHAPUS SELURUH DATA DUMMY (Customer, Invoice, Expense, PO, Supplier, Stock Movement, Log). Lanjutkan?')) {
                $this->info('Pembersihan data dibatalkan.');

                return self::FAILURE;
            }
        }

        $tablesToWipe = [
            'invoice_items',
            'payments',
            'invoices',
            'invoice_number_sequences',
            'customers',
            'expense_proof_cleanup_tasks',
            'expenses',
            'cash_bank_transactions',
            'purchase_order_items',
            'purchase_payments',
            'purchase_orders',
            'purchase_order_number_sequences',
            'goods_receipt_items',
            'goods_receipts',
            'goods_receipt_number_sequences',
            'supplier_price_lists',
            'product_supplier',
            'suppliers',
            'stock_movements',
            'fifo_inventory_layers',
            'activity_logs',
        ];

        $this->info('Memulai pembersihan data dummy...');
        $this->line('----------------------------------------------------');

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        $summary = [];

        foreach ($tablesToWipe as $table) {
            if (Schema::hasTable($table)) {
                $countBefore = DB::table($table)->count();
                DB::table($table)->truncate();
                $countAfter = DB::table($table)->count();
                $summary[] = [
                    'table' => $table,
                    'before' => $countBefore,
                    'after' => $countAfter,
                ];
                $this->line("Tabel [{$table}]: membersihkan {$countBefore} record.");
            }
        }

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->line('----------------------------------------------------');
        $this->info('Pembersihan data selesai!');
        $this->table(['Tabel', 'Sebelum', 'Sesudah'], $summary);

        $this->line('----------------------------------------------------');
        $this->info('Pengecekan Data yang Dipertahankan:');
        $preserved = [
            'products' => Schema::hasTable('products') ? DB::table('products')->count() : 0,
            'product_categories' => Schema::hasTable('product_categories') ? DB::table('product_categories')->count() : 0,
            'users' => Schema::hasTable('users') ? DB::table('users')->count() : 0,
            'roles' => Schema::hasTable('roles') ? DB::table('roles')->count() : 0,
            'company_profiles' => Schema::hasTable('company_profiles') ? DB::table('company_profiles')->count() : 0,
            'bank_accounts' => Schema::hasTable('bank_accounts') ? DB::table('bank_accounts')->count() : 0,
            'application_settings' => Schema::hasTable('application_settings') ? DB::table('application_settings')->count() : 0,
        ];

        foreach ($preserved as $table => $count) {
            $this->info("✓ [{$table}]: {$count} record dipertahankan.");
        }

        return self::SUCCESS;
    }
}
