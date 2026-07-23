<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentActivitiesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_activities_returns_all_activities_by_default(): void
    {
        $response = $this->getJson(route('api.dashboard.activities'));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(5, 'data');
    }

    public function test_recent_activities_filters_by_type(): void
    {
        $response = $this->getJson(route('api.dashboard.activities', ['type' => 'payment']));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'payment')
            ->assertJsonPath('data.1.type', 'payment');
    }
}
