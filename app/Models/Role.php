<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, SoftDeletes;

    public const CODE_OWNER = 'owner';

    public const CODE_FINANCE_ADMIN = 'finance_admin';

    public const CODE_OPERATIONS = 'operations';

    public const CODE_VIEWER = 'viewer';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LIMITED = 'limited';

    public const STATUS_DISABLED = 'disabled';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'guard_name' => 'web',
        'status' => self::STATUS_ACTIVE,
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
        'guard_name',
        'description',
        'scope',
        'status',
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
     * Users assigned to this role code.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'code');
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->using(RolePermission::class)
            ->withPivot('constraints')
            ->withTimestamps();
    }

    /**
     * Determine whether this role has a permission code.
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }

    /**
     * Scope roles that can be assigned to users.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_LIMITED]);
    }

    /**
     * Set a normalized role code when omitted.
     */
    protected static function booted(): void
    {
        static::creating(function (Role $role): void {
            if (blank($role->code)) {
                $role->code = (string) Str::of($role->name)->snake()->lower();
            }
        });
    }
}
