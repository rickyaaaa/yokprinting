<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueChartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_chart_returns_monthly_dataset_by_default(): void
    {
        $response = $this->getJson(route('api.dashboard.revenue-chart'));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.period', 'monthly')
            ->assertJsonPath('data.labels', ['Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul']);
    }

    public function test_revenue_chart_supports_quarterly_and_yearly_periods(): void
    {
        $responseQuarterly = $this->getJson(route('api.dashboard.revenue-chart', ['period' => 'quarterly']));

        $responseQuarterly->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.period', 'quarterly')
            ->assertJsonPath('data.labels', ['Q4 2025', 'Q1 2026', 'Q2 2026', 'Q3 2026']);

        $responseYearly = $this->getJson(route('api.dashboard.revenue-chart', ['period' => 'yearly']));

        $responseYearly->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.period', 'yearly')
            ->assertJsonPath('data.labels', ['2024', '2025', '2026']);
    }
}
