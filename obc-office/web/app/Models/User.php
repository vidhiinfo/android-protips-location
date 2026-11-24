<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = [
        'super_admin' => 'Super Admin (National Office)',
        'national_staff' => 'National Office Staff',
        'state_admin' => 'State Admin',
        'district_admin' => 'District Admin',
    ];

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'state_id', 'district_id', 'mobile', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /** National office users see all of India and the President's personal-office modules. */
    public function isNational(): bool
    {
        return in_array($this->role, ['super_admin', 'national_staff'], true);
    }

    /** Effective access to a module: none | view | manage (driven by Settings → Roles & permissions). */
    public function access(string $module): string
    {
        $modules = config('office.modules');
        if (! isset($modules[$module]) || ! (Setting::get('modules_enabled')[$module] ?? true)) {
            return 'none';
        }
        if ($this->isSuperAdmin()) {
            return end($modules[$module]['levels']);
        }

        return Setting::get('permissions')[$this->role][$module] ?? 'none';
    }

    public function canView(string $module): bool
    {
        return $this->access($module) !== 'none';
    }

    public function canManage(string $module): bool
    {
        return $this->access($module) === 'manage';
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** Restrict any query having state_id / district_id columns to this user's jurisdiction. */
    public function applyGeoScope(Builder $query, string $prefix = ''): Builder
    {
        if ($this->role === 'state_admin') {
            $query->where($prefix.'state_id', $this->state_id);
        } elseif ($this->role === 'district_admin') {
            $query->where($prefix.'district_id', $this->district_id);
        }

        return $query;
    }

    public function jurisdictionLabel(): string
    {
        return match ($this->role) {
            'state_admin' => $this->state?->name ?? '—',
            'district_admin' => ($this->district?->name ?? '—').', '.($this->state?->name ?? ''),
            default => 'All India',
        };
    }
}
