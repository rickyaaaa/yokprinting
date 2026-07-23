<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory, SoftDeletes;

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const MODULE_DASHBOARD = 'dashboard';

    public const MODULE_INVOICE = 'invoice';

    public const MODULE_CUSTOMER = 'customer';

    public const MODULE_PRODUCT = 'product';

    public const MODULE_PAYMENT = 'payment';

    public const MODULE_REPORT = 'report';

    public const MODULE_SETTING = 'setting';

    public const MODULE_ROLE = 'role';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'guard_name' => 'web',
        'risk_level' => self::RISK_LOW,
        'is_system' => false,
        'sort_order' => 0,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'module',
        'action',
        'guard_name',
        'description',
        'risk_level',
        'is_system',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Roles that contain this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->using(RolePermission::class)
            ->withPivot('constraints')
            ->withTimestamps();
    }

    /**
     * Scope permissions for a module.
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Set a normalized permission code when omitted.
     */
    protected static function booted(): void
    {
        static::creating(function (Permission $permission): void {
            if (blank($permission->code)) {
                $permission->code = Str::of($permission->module)
                    ->append('.', $permission->action)
                    ->lower()
                    ->toString();
            }
        });
    }
}
