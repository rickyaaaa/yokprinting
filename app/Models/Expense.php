<?php

namespace App\Models;

use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, SoftDeletes;

    public const CATEGORY_PRODUCTION = 'production';

    public const CATEGORY_SHOPPING = 'shopping';

    public const CATEGORY_EMPLOYEE = 'employee';

    public const CATEGORY_PREMISES = 'premises';

    public const CATEGORY_EXPEDITION = 'expedition';

    public const SUBCATEGORY_SALARY = 'salary';

    public const SUBCATEGORY_THR = 'thr';

    public const SUBCATEGORY_BONUS = 'bonus';

    public const SUBCATEGORY_OVERTIME = 'overtime';

    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_CREDIT_CARD = 'credit_card';

    public const METHOD_QRIS = 'qris';

    public const METHOD_OTHER = 'other';

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

    public function cashBankTransaction(): HasOne
    {
        return $this->hasOne(CashBankTransaction::class, 'source_id')
            ->where('source_type', CashBankTransaction::SOURCE_EXPENSE);
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
            self::CATEGORY_EXPEDITION => 'Biaya Ekspedisi',
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
     * Fixed payment method vocabulary, shared with ExpenseBankMethodPolicy's
     * bank-vs-cash bucketing so Kas & Bank categorization can never silently
     * miscategorize a value it doesn't recognize (unlike free text before).
     *
     * @return array<string, string>
     */
    public static function paymentMethodOptions(): array
    {
        return [
            self::METHOD_CASH => 'Tunai',
            self::METHOD_BANK_TRANSFER => 'Transfer Bank',
            self::METHOD_CREDIT_CARD => 'Kartu Kredit',
            self::METHOD_QRIS => 'QRIS',
            self::METHOD_OTHER => 'Lainnya',
        ];
    }

    /**
     * @return list<string>
     */
    public static function paymentMethods(): array
    {
        return array_keys(self::paymentMethodOptions());
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

    public function paymentMethodLabel(): string
    {
        return self::paymentMethodOptions()[$this->payment_method] ?? $this->payment_method;
    }
}
