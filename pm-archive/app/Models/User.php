<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_ENCODER = 'encoder';

    public const ROLES = [
        self::ROLE_SUPERADMIN,
        self::ROLE_ADMIN,
        self::ROLE_ENCODER,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEncoder(): bool
    {
        return $this->role === self::ROLE_ENCODER;
    }

    public function roleLabel(): string
    {
        return config('auth_roles.roles.' . $this->role, ucfirst((string) $this->role));
    }

    public function manageableRoles(): array
    {
        if ($this->isSuperAdmin()) {
            $roles = [self::ROLE_ADMIN, self::ROLE_ENCODER];

            if (config('auth_roles.allow_creating_superadmins')) {
                array_unshift($roles, self::ROLE_SUPERADMIN);
            }

            return $roles;
        }

        if ($this->isAdmin()) {
            return [self::ROLE_ENCODER];
        }

        return [];
    }

    public function canManageRole(string $role): bool
    {
        return in_array($role, $this->manageableRoles(), true);
    }

    public function canManageUser(User $user): bool
    {
        if ($this->id === $user->id) {
            return false;
        }

        return $this->canManageRole($user->role);
    }

    public function permissions(): array
    {
        return [
            'manage_users' => $this->hasRole(self::ROLE_SUPERADMIN, self::ROLE_ADMIN),
            'manage_settings' => $this->isSuperAdmin(),
            'manage_references' => $this->hasRole(self::ROLE_SUPERADMIN, self::ROLE_ADMIN),
            'manage_checklist_items' => $this->hasRole(self::ROLE_SUPERADMIN, self::ROLE_ADMIN),
            'view_reports' => $this->hasRole(self::ROLE_SUPERADMIN, self::ROLE_ADMIN),
            'export_reports' => $this->hasRole(self::ROLE_SUPERADMIN, self::ROLE_ADMIN),
            'delete_records' => $this->isSuperAdmin(),
        ];
    }
}
