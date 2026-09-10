<?php

namespace Tests\Unit;

use App\Services\Invoices\SpellRupiah;
use PHPUnit\Framework\TestCase;

class SpellRupiahTest extends TestCase
{
    private SpellRupiah $spell;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spell = new SpellRupiah;
    }

    public function test_it_spells_the_amount_from_the_client_brief(): void
    {
        $this->assertSame('Dua juta empat puluh ribu rupiah', $this->spell->spell(2_040_000));
    }

    /**
     * Indonesian has two irregular forms that a naive implementation gets
     * wrong: eleven, and a leading single hundred or thousand.
     */
    public function test_it_handles_the_irregular_forms(): void
    {
        $this->assertSame('Sebelas ribu rupiah', $this->spell->spell(11_000));
        $this->assertSame('Seribu rupiah', $this->spell->spell(1_000));
        $this->assertSame('Seratus ribu rupiah', $this->spell->spell(100_000));
        $this->assertSame('Seratus sebelas rupiah', $this->spell->spell(111));
    }

    public function test_it_spells_each_magnitude(): void
    {
        $this->assertSame('Nol rupiah', $this->spell->spell(0));
        $this->assertSame('Sembilan ratus sembilan puluh sembilan rupiah', $this->spell->spell(999));
        $this->assertSame(
            'Satu juta dua ratus tiga puluh empat ribu lima ratus enam puluh tujuh rupiah',
            $this->spell->spell(1_234_567),
        );
        $this->assertSame('Dua miliar rupiah', $this->spell->spell(2_000_000_000));
        $this->assertSame('Satu triliun rupiah', $this->spell->spell(1_000_000_000_000));
    }

    /**
     * The summary box prints totals with no decimals, so the words have to
     * describe the figure the reader can actually see.
     */
    public function test_it_rounds_to_whole_rupiah_to_match_the_printed_total(): void
    {
        $this->assertSame('Dua juta empat puluh ribu rupiah', $this->spell->spell(2_040_000.49));
        $this->assertSame('Dua juta empat puluh ribu satu rupiah', $this->spell->spell(2_040_000.5));
    }

    public function test_it_handles_a_negative_amount_without_breaking(): void
    {
        $this->assertSame('Minus seribu rupiah', $this->spell->spell(-1_000));
    }
}
