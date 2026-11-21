<?php

namespace App\Services\Auth;

use App\Models\AppUser;
use App\Models\Role;
use App\Support\Permission;
use Illuminate\Support\Facades\Artisan;
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

    /** Bump when a release adds database changes, so existing installations re-check once. */
    private const VERSION = 'vis.defaults.2026-11-privacy';

    public function ensure(bool $force = false): void
    {
        if (!$force && Cache::get(self::VERSION)) return;
        if (!Schema::hasTable('roles')) return;
        // database created from an older db/schema.sql: add the new columns (no SSH / phpMyAdmin step needed)
        if (Schema::hasTable('voters') && (!Schema::hasColumn('voters', 'caste') || !Schema::hasColumn('voters', 'ward_no') || !Schema::hasColumn('voters', 'sub_caste') || !Schema::hasTable('castes') || !Schema::hasColumn('audit_log', 'ip_address') || !Schema::hasTable('grievances') || !Schema::hasColumn('voters', 'worker_id') || !Schema::hasTable('political_statuses') || !Schema::hasColumn('app_users', 'scope_booths') || !Schema::hasTable('beneficiaries') || !Schema::hasTable('roll_updates') || !Schema::hasIndex('voters', 'idx_voter_list') || !Schema::hasColumn('voters', 'house_key') || !Schema::hasTable('state_snapshots') || !Schema::hasColumn('app_users', 'otp_method') || !Schema::hasTable('bulk_changes') || !Schema::hasTable('privacy_requests'))) {
            Artisan::call('migrate', ['--force' => true]);
        }
        DB::transaction(function () {
            $this->role('ADMIN', 'Administrator', 'Everything, including users, roles, masters and the audit log', Permission::codes());
            $this->role('OPERATOR', 'Operator', 'Voter data entry and import, results entry, messaging', Permission::OPERATOR);
            $this->role('VIEWER', 'Viewer', 'Search, view, reports, export and print', Permission::VIEWER);
            if (config('voters.security.user_source') !== 'external') $this->firstAdmin();
            $this->grantAdded();
            if (Schema::hasTable('castes')) \App\Models\Caste::seedDefaults();   // empty caste masters: Delhi list
        });
        Cache::forever(self::VERSION, true);
    }

    private function role(string $code, string $name, string $desc, array $perms): void
    {
        if (Role::where('code', $code)->exists()) return;
        $r = Role::create(['code' => $code, 'name' => $name, 'description' => $desc, 'system_role' => true]);
        $r->setPermissions($perms);
    }

    /** Permissions added by a newer version go to the built-in roles once (later edits by the admin are kept). */
    private function grantAdded(): void
    {
        $flag = 'vis.perm.added.2026-11-privacy';
        if (Cache::get($flag)) return;
        foreach (Permission::ADDED as $code => $perms) {
            $r = Role::where('code', $code)->first();
            if (!$r) continue;
            foreach (array_diff($perms, $r->permissions()) as $p) DB::table('role_permissions')->insert(['role_id' => $r->id, 'permission' => $p]);
        }
        Cache::forever($flag, true);
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
