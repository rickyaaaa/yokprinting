<?php

namespace Tests\Unit;

use App\Support\ProfitLossPeriod;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ProfitLossPeriodTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_presets_follow_application_timezone_across_year_boundary(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-31 17:30:00', 'UTC'));

        $this->assertSame([
            'daily' => ['date_from' => '2027-01-01', 'date_to' => '2027-01-01'],
            'weekly' => ['date_from' => '2026-12-28', 'date_to' => '2027-01-03'],
            'monthly' => ['date_from' => '2027-01-01', 'date_to' => '2027-01-31'],
            'yearly' => ['date_from' => '2027-01-01', 'date_to' => '2027-12-31'],
        ], ProfitLossPeriod::presets());
    }
}
