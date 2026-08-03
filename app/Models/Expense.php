<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, SoftDeletes;

    public const CATEGORY_PRODUCTION = 'production';

    public const CATEGORY_SHOPPING = 'shopping';

    public const CATEGORY_EMPLOYEE = 'employee';

    public const CATEGORY_PREMISES = 'premises';

    public const SUBCATEGORY_SALARY = 'salary';

    public const SUBCATEGORY_THR = 'thr';

    public const SUBCATEGORY_BONUS = 'bonus';

    public const SUBCATEGORY_OVERTIME = 'overtime';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'expense_date',
        'category',
        'subcategory',
        'amount',
        'description',
        'recipient',
        'payment_method',
        'proof_path',
        'proof_original_name',
        'proof_mime_type',
        'created_by',
        'version',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    /**
     * User who created this expense.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Categories explicitly approved by the owner.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_PRODUCTION => 'Biaya Produksi',
            self::CATEGORY_SHOPPING => 'Belanjaan',
            self::CATEGORY_EMPLOYEE => 'Biaya Karyawan',
            self::CATEGORY_PREMISES => 'Biaya Tempat',
        ];
    }

    /**
     * Subcategories explicitly approved for employee expenses.
     *
     * @return array<string, string>
     */
    public static function employeeSubcategoryOptions(): array
    {
        return [
            self::SUBCATEGORY_SALARY => 'Gaji',
            self::SUBCATEGORY_THR => 'THR',
            self::SUBCATEGORY_BONUS => 'Bonus',
            self::SUBCATEGORY_OVERTIME => 'Lemburan',
        ];
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_keys(self::categoryOptions());
    }

    /**
     * @return list<string>
     */
    public static function employeeSubcategories(): array
    {
        return array_keys(self::employeeSubcategoryOptions());
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? $this->category;
    }

    public function subcategoryLabel(): ?string
    {
        if ($this->subcategory === null) {
            return null;
        }

        return self::employeeSubcategoryOptions()[$this->subcategory] ?? $this->subcategory;
    }
}
