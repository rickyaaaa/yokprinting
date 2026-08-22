<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_defines_only_owner_requested_categories_and_subcategories(): void
    {
        $this->assertSame([
            Expense::CATEGORY_PRODUCTION => 'Biaya Produksi',
            Expense::CATEGORY_SHOPPING => 'Belanjaan',
            Expense::CATEGORY_EMPLOYEE => 'Biaya Karyawan',
            Expense::CATEGORY_PREMISES => 'Biaya Tempat',
            Expense::CATEGORY_EXPEDITION => 'Biaya Ekspedisi',
        ], Expense::categoryOptions());

        $this->assertSame([
            Expense::SUBCATEGORY_SALARY => 'Gaji',
            Expense::SUBCATEGORY_THR => 'THR',
            Expense::SUBCATEGORY_BONUS => 'Bonus',
            Expense::SUBCATEGORY_OVERTIME => 'Lemburan',
        ], Expense::employeeSubcategoryOptions());
    }

    public function test_expense_casts_money_and_date_and_belongs_to_creator(): void
    {
        $creator = User::factory()->create();
        $expense = Expense::factory()->create([
            'created_by' => $creator->id,
            'expense_date' => '2026-08-02',
            'amount' => '123456.78',
        ]);

        $this->assertSame('2026-08-02', $expense->expense_date->toDateString());
        $this->assertSame('123456.78', $expense->amount);
        $this->assertTrue($expense->creator->is($creator));
    }
}
