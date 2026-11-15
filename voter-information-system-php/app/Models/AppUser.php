<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Application user (table app_users). Passwords are stored as "{bcrypt}$2y$..." - the same format the
 * Java version uses, so both versions can share the table.
 */
class AppUser extends Model
{
    protected $table = 'app_users';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['password_hash'];
    protected $casts = ['enabled' => 'boolean', 'assembly_no' => 'integer', 'last_login' => 'datetime', 'created_at' => 'datetime'];

    public function roles() { return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id'); }

    public function permissions(): array
    {
        $ids = $this->roles()->pluck('roles.id');
        return DB::table('role_permissions')->whereIn('role_id', $ids)->distinct()->pluck('permission')->all();
    }

    public static function hashPassword(string $raw): string
    {
        return '{bcrypt}' . password_hash($raw, PASSWORD_BCRYPT);
    }

    public static function checkPassword(string $raw, string $stored, string $defaultEncoding = 'bcrypt'): bool
    {
        if (preg_match('/^\{([^}]+)\}(.*)$/s', $stored, $m)) { $enc = strtolower($m[1]); $hash = $m[2]; }
        else { $enc = strtolower($defaultEncoding); $hash = $stored; }
        return match ($enc) {
            'bcrypt' => password_verify($raw, $hash),
            'noop' => hash_equals($hash, $raw),
            'sha256', 'sha-256' => hash_equals(strtolower($hash), hash('sha256', $raw)),
            'md5' => hash_equals(strtolower($hash), md5($raw)),
            'argon2' => password_verify($raw, $hash),
            default => false,
        };
    }
}
