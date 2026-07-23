<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesReportIndexMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_indexes_are_available(): void
    {
        $this->assertIndexExists('invoices', 'invoices_issue_date_status_index');
        $this->assertIndexExists('invoices', 'invoices_issue_payment_due_index');
        $this->assertIndexExists('invoice_items', 'invoice_items_product_invoice_index');
        $this->assertIndexExists('payments', 'payments_invoice_status_index');
    }

    private function assertIndexExists(string $table, string $index): void
    {
        $driver = DB::connection()->getDriverName();
        $indexes = match ($driver) {
            'sqlite' => collect(DB::select(
                "select name from sqlite_master where type = 'index' and tbl_name = ?",
                [$table],
            ))->pluck('name'),
            'mysql' => collect(DB::select("show index from {$table}"))->pluck('Key_name'),
            default => collect(DB::connection()->getSchemaBuilder()->getIndexes($table))->pluck('name'),
        };

        $this->assertContains($index, $indexes->all());
    }
}
