<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';

    public const ROLE_FINANCE_ADMIN = 'finance_admin';

    public const ROLE_OPERATIONS = 'operations';

    public const ROLE_VIEWER = 'viewer';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INVITED = 'invited';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_OWNER,
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'company_name',
        'role',
        'status',
        'phone',
        'job_title',
        'avatar_path',
        'last_login_at',
        'last_login_ip',
        'security_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'security_preferences' => 'array',
        ];
    }

    /**
     * Determine whether the user can access the application.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Determine whether the user has a permission through their active role.
     */
    public function hasPermission(string $permissionCode): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->role === self::ROLE_OWNER) {
            return true;
        }

        return $this->roleDefinition()
            ->where('status', '!=', Role::STATUS_DISABLED)
            ->whereHas('permissions', fn ($query) => $query->where('code', $permissionCode))
            ->exists();
    }

    /**
     * Get the role definition that matches the stored role code.
     */
    public function roleDefinition(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'code');
    }
}
