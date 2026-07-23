<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $module = fake()->randomElement([
            Permission::MODULE_DASHBOARD,
            Permission::MODULE_INVOICE,
            Permission::MODULE_CUSTOMER,
            Permission::MODULE_PRODUCT,
            Permission::MODULE_PAYMENT,
            Permission::MODULE_REPORT,
            Permission::MODULE_SETTING,
            Permission::MODULE_ROLE,
        ]);
        $action = fake()->randomElement(['view', 'create', 'update', 'delete', 'export']).'_'.fake()->unique()->numberBetween(100, 999);

        return [
            'name' => str($module)->headline().' '.str($action)->before('_')->headline(),
            'code' => "{$module}.{$action}",
            'module' => $module,
            'action' => $action,
            'guard_name' => 'web',
            'description' => fake()->sentence(),
            'risk_level' => Permission::RISK_LOW,
            'is_system' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Mark this permission as high risk.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_level' => Permission::RISK_HIGH,
        ]);
    }
}
