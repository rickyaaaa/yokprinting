<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\Invoices\GenerateInvoiceNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GenerateInvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_invoice_of_a_month_starts_at_0001_and_increments(): void
    {
        $generator = app(GenerateInvoiceNumber::class);

        $this->assertSame('INV-2026-11-0001', $generator->generate(CarbonImmutable::parse('2026-11-02')));
        $this->assertSame('INV-2026-11-0002', $generator->generate(CarbonImmutable::parse('2026-11-30')));
    }

    public function test_each_month_has_an_independent_sequence(): void
    {
        $generator = app(GenerateInvoiceNumber::class);

        $this->assertSame('INV-2026-10-0001', $generator->generate(CarbonImmutable::parse('2026-10-01')));
        $this->assertSame('INV-2026-10-0002', $generator->generate(CarbonImmutable::parse('2026-10-31')));
        $this->assertSame('INV-2026-11-0001', $generator->generate(CarbonImmutable::parse('2026-11-01')));
        $this->assertSame('INV-2026-12-0001', $generator->generate(CarbonImmutable::parse('2026-12-01')));
        $this->assertSame('INV-2026-10-0003', $generator->generate(CarbonImmutable::parse('2026-10-15')));
    }

    public function test_year_change_starts_again_at_0001(): void
    {
        $generator = app(GenerateInvoiceNumber::class);

        $this->assertSame('INV-2026-12-0001', $generator->generate(CarbonImmutable::parse('2026-12-31')));
        $this->assertSame('INV-2027-01-0001', $generator->generate(CarbonImmutable::parse('2027-01-01')));
    }

    public function test_november_does_not_continue_from_october_legacy_numbers(): void
    {
        $this->createInvoice('INV-2026-10-0198', '2026-10-31');

        $number = app(GenerateInvoiceNumber::class)->generate(CarbonImmutable::parse('2026-11-02'));

        $this->assertSame('INV-2026-11-0001', $number);
    }

    public function test_legacy_yearly_numbers_do_not_become_the_monthly_baseline(): void
    {
        // State left behind by the old yearly generator on 2 November 2026.
        DB::table('invoice_number_sequences')->insert([
            'year' => 2026,
            'last_number' => 212,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createInvoice('INV-2026-0042', '2026-07-01');
        $this->createInvoice('INV-2026-11-0211', '2026-11-01');
        $legacy = $this->createInvoice('INV-2026-11-0212', '2026-11-02');

        $number = app(GenerateInvoiceNumber::class)->generate(CarbonImmutable::parse('2026-11-02'));

        $this->assertSame('INV-2026-11-0001', $number);
        $this->assertSame('INV-2026-11-0212', $legacy->fresh()->invoice_number);
        $this->assertSame(212, (int) DB::table('invoice_number_sequences')->where('year', 2026)->value('last_number'));
    }

    public function test_sequence_skips_numbers_already_used_by_legacy_invoices(): void
    {
        $this->createInvoice('INV-2026-11-0210', '2026-11-01');
        $this->createInvoice('INV-2026-11-0211', '2026-11-01');
        $this->createInvoice('INV-2026-11-0212', '2026-11-02');
        DB::table('invoice_monthly_number_sequences')->insert([
            'year' => 2026,
            'month' => 11,
            'last_number' => 209,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $generator = app(GenerateInvoiceNumber::class);

        $this->assertSame('INV-2026-11-0213', $generator->generate(CarbonImmutable::parse('2026-11-20')));
        $this->assertSame('INV-2026-11-0214', $generator->generate(CarbonImmutable::parse('2026-11-21')));
    }

    public function test_numbers_of_active_and_soft_deleted_invoices_are_never_reused(): void
    {
        $this->createInvoice('INV-2026-11-0001', '2026-11-01')->delete();
        $this->createInvoice('INV-2026-11-0002', '2026-11-01');
        $this->assertSoftDeleted('invoices', ['invoice_number' => 'INV-2026-11-0001']);

        $number = app(GenerateInvoiceNumber::class)->generate(CarbonImmutable::parse('2026-11-02'));

        $this->assertSame('INV-2026-11-0003', $number);
    }

    public function test_backdated_invoice_uses_the_month_of_its_issue_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
        $this->createInvoice('INV-2026-10-0001', '2026-10-01');
        $generator = app(GenerateInvoiceNumber::class);

        $this->assertSame('INV-2026-11-0001', $generator->generate(CarbonImmutable::parse('2026-11-02')));
        $this->assertSame('INV-2026-10-0002', $generator->generate(CarbonImmutable::parse('2026-10-28')));
        $this->assertSame('INV-2026-11-0002', $generator->generate());
    }

    public function test_sequence_table_allows_only_one_row_per_month(): void
    {
        $row = ['year' => 2026, 'month' => 11, 'last_number' => 0];
        DB::table('invoice_monthly_number_sequences')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('invoice_monthly_number_sequences')->insert($row);
    }

    public function test_concurrent_processes_never_receive_the_same_number(): void
    {
        $database = sys_get_temp_dir().DIRECTORY_SEPARATOR.'invoice-number-race-'.uniqid().'.sqlite';
        touch($database);
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database];
        $workers = 4;
        $perWorker = 10;

        try {
            (new Process([PHP_BINARY, 'artisan', 'migrate', '--force'], base_path(), $env, null, 300))->mustRun();

            $processes = collect(range(1, $workers))->map(function () use ($env, $perWorker): Process {
                $process = new Process(
                    [PHP_BINARY, base_path('tests/Fixtures/invoice-number-worker.php'), (string) $perWorker, '2026-11-15'],
                    base_path(),
                    $env,
                    null,
                    300,
                );
                $process->start();

                return $process;
            });

            $processes->each(function (Process $process): void {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            });

            $pdo = new PDO('sqlite:'.$database);
            $numbers = $pdo->query('select invoice_number from invoices order by invoice_number')
                ->fetchAll(PDO::FETCH_COLUMN);
            $pdo = null;

            $expected = array_map(
                fn (int $n): string => sprintf('INV-2026-11-%04d', $n),
                range(1, $workers * $perWorker),
            );
            $this->assertSame($expected, $numbers);
        } finally {
            @unlink($database);
        }
    }

    private function createInvoice(string $number, string $issueDate): Invoice
    {
        return Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => $number,
            'issue_date' => $issueDate,
            'due_date' => $issueDate,
        ]);
    }
}
