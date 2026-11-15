<?php

namespace App\Services\Auth;

use App\Models\AppUser;
use App\Models\Role;
use App\Support\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the system roles and the first administrator when missing. Runs once (cached) on the first request,
 * so shared hosting needs no command line.
 */
class Defaults
{
    public function __construct(private AuditService $audit) {}

    public function ensure(bool $force = false): void
    {
        if (!$force && Cache::get('vis.defaults.ok')) return;
        if (!Schema::hasTable('roles')) return;
        DB::transaction(function () {
            $this->role('ADMIN', 'Administrator', 'Everything, including users, roles, masters and the audit log', Permission::codes());
            $this->role('OPERATOR', 'Operator', 'Voter data entry and import, results entry, messaging', Permission::OPERATOR);
            $this->role('VIEWER', 'Viewer', 'Search, view, reports, export and print', Permission::VIEWER);
            if (config('voters.security.user_source') !== 'external') $this->firstAdmin();
        });
        Cache::forever('vis.defaults.ok', true);
    }

    private function role(string $code, string $name, string $desc, array $perms): void
    {
        if (Role::where('code', $code)->exists()) return;
        $r = Role::create(['code' => $code, 'name' => $name, 'description' => $desc, 'system_role' => true]);
        $r->setPermissions($perms);
    }

    private function firstAdmin(): void
    {
        $hasAdmin = DB::table('app_users as u')->join('user_roles as ur', 'ur.user_id', '=', 'u.id')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'ur.role_id')
            ->where('u.enabled', true)->where('rp.permission', 'USER_ADMIN')->exists();
        $username = config('voters.admin.username');
        if ($hasAdmin || AppUser::where('username', $username)->exists()) return;
        $raw = (string) config('voters.admin.password');
        $u = AppUser::create(['username' => $username, 'full_name' => 'Administrator', 'enabled' => true, 'created_at' => now(),
            'password_hash' => str_starts_with($raw, '{') ? $raw : AppUser::hashPassword($raw)]);
        $u->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->audit->log('CREATE', 'USER', $u->id, 'Initial administrator ' . $username, 'system');
    }
}
