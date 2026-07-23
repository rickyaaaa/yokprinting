<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    public const TEMPLATE_PROFESSIONAL = 'professional';

    public const TEMPLATE_MODERN = 'modern';

    public const TEMPLATE_CREATIVE = 'creative';

    public const NUMBERING_RESET_YEARLY = 'yearly';

    public const NUMBERING_RESET_MONTHLY = 'monthly';

    public const NUMBERING_RESET_NEVER = 'never';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'invoice_prefix' => 'INV',
        'brand_color' => '#52772c',
        'invoice_template' => self::TEMPLATE_PROFESSIONAL,
        'default_tax_rate' => 0,
        'default_due_days' => 14,
        'reminder_days_before_due' => 3,
        'numbering_reset' => self::NUMBERING_RESET_YEARLY,
        'is_default' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'business_name',
        'legal_name',
        'business_type',
        'registration_number',
        'industry',
        'business_scale',
        'founded_year',
        'pic_name',
        'pic_role',
        'email',
        'phone',
        'website',
        'tax_number',
        'invoice_prefix',
        'bank_name',
        'bank_account',
        'bank_holder',
        'address',
        'city',
        'province',
        'postal_code',
        'brand_color',
        'logo_path',
        'invoice_template',
        'default_tax_rate',
        'default_due_days',
        'reminder_days_before_due',
        'numbering_reset',
        'is_default',
        'notes',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
            'default_tax_rate' => 'decimal:2',
            'default_due_days' => 'integer',
            'reminder_days_before_due' => 'integer',
            'is_default' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
