<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\Invoices\GenerateInvoiceNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_sequential_numbers_per_year(): void
    {
        $generator = app(GenerateInvoiceNumber::class);

        $this->assertSame(
            'INV-2026-0001',
            $generator->generate(CarbonImmutable::parse('2026-07-23')),
        );
        $this->assertSame(
            'INV-2026-0002',
            $generator->generate(CarbonImmutable::parse('2026-12-31')),
        );
        $this->assertSame(
            'INV-2027-0001',
            $generator->generate(CarbonImmutable::parse('2027-01-01')),
        );
    }

    public function test_it_continues_after_existing_invoice_numbers(): void
    {
        Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => 'INV-2026-0042',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
        ]);
        Invoice::query()->create([
            'customer_id' => 1,
            'invoice_number' => 'MANUAL-2026-9999',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
        ]);

        $number = app(GenerateInvoiceNumber::class)
            ->generate(CarbonImmutable::parse('2026-07-23'));

        $this->assertSame('INV-2026-0043', $number);
    }
}
