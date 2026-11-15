<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** A role = a named set of permissions (table role_permissions). */
class Role extends Model
{
    protected $table = 'roles';
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['system_role' => 'boolean'];

    public function permissions(): array
    {
        return DB::table('role_permissions')->where('role_id', $this->id)->pluck('permission')->all();
    }

    public function setPermissions(array $perms): void
    {
        DB::table('role_permissions')->where('role_id', $this->id)->delete();
        foreach (array_unique($perms) as $p) DB::table('role_permissions')->insert(['role_id' => $this->id, 'permission' => $p]);
    }

    public function has(string $p): bool { return in_array($p, $this->permissions(), true); }

    public function userCount(): int { return DB::table('user_roles')->where('role_id', $this->id)->count(); }

    public function setCodeAttribute($v): void { $this->attributes['code'] = strtoupper(trim((string) $v)); }
}
