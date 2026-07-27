<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'code' => Str::of($name)->snake()->lower()->append('_', fake()->unique()->numberBetween(100, 999))->toString(),
            'guard_name' => 'web',
            'description' => fake()->sentence(),
            'scope' => fake()->randomElement(['Semua modul', 'Finance & laporan', 'Operasional', 'Baca laporan']),
            'status' => Role::STATUS_ACTIVE,
            'is_system' => false,
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * Indicate that the role is a protected system role.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Indicate that the role cannot be assigned.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Role::STATUS_DISABLED,
        ]);
    }
}
