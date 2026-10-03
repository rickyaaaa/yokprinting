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
    protected $signature = 'app:reset-dummy-data
        {--force : Force execution without confirmation prompt}
        {--preserve-master-data : Preserve customers, suppliers, and supplier price lists}
        {--zero-opening-balance : Set active bank account opening balances to zero after the dummy-data reset}
        {--dry-run : Preview affected records and opening balances without changing data}';

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
        $tablesToWipe = [
            'invoice_items',
            'payments',
            'invoices',
            'invoice_number_sequences',
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
            'stock_movements',
            'inventory_batches',
        ];

        if (! $this->option('preserve-master-data')) {
            array_push(
                $tablesToWipe,
                'supplier_price_lists',
                'product_supplier',
                'suppliers',
                'customers',
                'activity_logs',
            );
        }

        if ($this->option('dry-run')) {
            $this->info('Pratinjau saja: tidak ada data yang diubah.');
            $this->table(['Tabel', 'Record yang akan dihapus'], collect($tablesToWipe)
                ->filter(fn (string $table): bool => Schema::hasTable($table))
                ->map(fn (string $table): array => [$table, DB::table($table)->count()])
                ->values()->all());

            if (Schema::hasTable('products')) {
                $this->line('Produk yang tetap ada tetapi stok/HPP-nya akan dikosongkan: '.DB::table('products')->count());
            }

            if (Schema::hasTable('bank_accounts')) {
                $accounts = DB::table('bank_accounts')->where('is_active', true)->get(['id', 'opening_balance']);
                foreach ($accounts as $account) {
                    $after = $this->option('zero-opening-balance') ? '0.00' : $account->opening_balance;
                    $this->line("Rekening aktif #{$account->id}: saldo awal {$account->opening_balance} -> {$after}");
                }
            }

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $message = 'PERINGATAN: Perintah ini akan MENGHAPUS SELURUH DATA DUMMY (Customer, Invoice, Expense, PO, Supplier, Stock Movement, Log).';
            if ($this->option('zero-opening-balance')) {
                $message .= ' Saldo awal rekening aktif juga akan diubah menjadi Rp0.';
            }

            if (! $this->confirm($message.' Lanjutkan?')) {
                $this->info('Pembersihan data dibatalkan.');

                return self::FAILURE;
            }
        }

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

        // A clean operational start must not leave the product catalogue
        // advertising the old dummy stock or purchase cost after its ledger
        // has been cleared. The catalogue rows themselves are master data and
        // stay in place.
        if (Schema::hasTable('products')) {
            DB::table('products')->update([
                'stock' => 0,
                'purchase_price' => 0,
                'last_purchase_price' => null,
                'average_purchase_cost' => null,
            ]);
        }

        if ($this->option('zero-opening-balance') && Schema::hasTable('bank_accounts')) {
            $changed = DB::table('bank_accounts')->where('is_active', true)->where('opening_balance', '!=', 0)
                ->update(['opening_balance' => 0, 'updated_at' => now()]);
            $this->line("Saldo awal {$changed} rekening aktif diubah menjadi Rp0; rekening tetap dipertahankan.");
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
