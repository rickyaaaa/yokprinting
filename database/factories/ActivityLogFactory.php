<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_name' => fake()->name(),
            'actor_role' => User::ROLE_OWNER,
            'module' => fake()->randomElement(['auth', 'role', 'invoice', 'payment', 'setting']),
            'action' => fake()->randomElement(['login', 'create', 'update', 'delete', 'export']),
            'event' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => [
                'request_id' => fake()->uuid(),
            ],
            'risk_level' => ActivityLog::RISK_LOW,
            'occurred_at' => now(),
        ];
    }

    /**
     * Mark activity as high risk.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_level' => ActivityLog::RISK_HIGH,
        ]);
    }
}
