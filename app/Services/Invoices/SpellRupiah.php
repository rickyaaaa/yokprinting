<?php

namespace App\Services\Invoices;

/**
 * Spell a rupiah amount in Indonesian, for the "Terbilang" line on printed
 * documents.
 *
 * The amount is rounded to whole rupiah first, on purpose: every total on the
 * document is printed with `number_format(..., 0)`, so spelling the unrounded
 * value would put a figure in words that the summary box does not show.
 */
class SpellRupiah
{
    private const UNITS = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    public function spell(float $amount): string
    {
        $rounded = (int) round(abs($amount), 0, PHP_ROUND_HALF_UP);
        $words = $rounded === 0 ? 'nol' : $this->words($rounded);
        $words = trim(preg_replace('/\s+/', ' ', $words) ?? '');

        $prefix = $amount < 0 ? 'minus ' : '';

        return ucfirst($prefix.$words.' rupiah');
    }

    /**
     * Indonesian has two irregularities this has to respect: 11 is "sebelas"
     * rather than "satu belas", and a leading single hundred or thousand is
     * "seratus"/"seribu" rather than "satu ratus"/"satu ribu".
     */
    private function words(int $number): string
    {
        return match (true) {
            $number < 12 => self::UNITS[$number],
            $number < 20 => $this->words($number - 10).' belas',
            $number < 100 => $this->words(intdiv($number, 10)).' puluh '.$this->words($number % 10),
            $number < 200 => 'seratus '.$this->words($number - 100),
            $number < 1_000 => $this->words(intdiv($number, 100)).' ratus '.$this->words($number % 100),
            $number < 2_000 => 'seribu '.$this->words($number - 1_000),
            $number < 1_000_000 => $this->words(intdiv($number, 1_000)).' ribu '.$this->words($number % 1_000),
            $number < 1_000_000_000 => $this->words(intdiv($number, 1_000_000)).' juta '.$this->words($number % 1_000_000),
            $number < 1_000_000_000_000 => $this->words(intdiv($number, 1_000_000_000)).' miliar '.$this->words($number % 1_000_000_000),
            default => $this->words(intdiv($number, 1_000_000_000_000)).' triliun '.$this->words($number % 1_000_000_000_000),
        };
    }
}
