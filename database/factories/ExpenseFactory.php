<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_date' => fake()->date(),
            'category' => Expense::CATEGORY_PRODUCTION,
            'subcategory' => null,
            'amount' => fake()->randomFloat(2, 1000, 10000000),
            'description' => fake()->sentence(),
            'recipient' => fake()->name(),
            'payment_method' => Expense::METHOD_BANK_TRANSFER,
            'proof_path' => 'expense-proofs/'.fake()->uuid().'.pdf',
            'proof_original_name' => 'bukti-pembayaran.pdf',
            'proof_mime_type' => 'application/pdf',
            'created_by' => User::factory(),
            'version' => 1,
        ];
    }

    /**
     * Create an employee expense with an approved subcategory.
     */
    public function employee(string $subcategory = Expense::SUBCATEGORY_SALARY): static
    {
        return $this->state(fn (): array => [
            'category' => Expense::CATEGORY_EMPLOYEE,
            'subcategory' => $subcategory,
        ]);
    }
}
