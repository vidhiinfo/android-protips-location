<?php

namespace App\Services\Auth;

use App\Models\AppUser;
use App\Models\Assembly;
use App\Models\Role;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;

/**
 * Username + password + assembly login.
 * user_source=app: tables app_users / user_roles / roles / role_permissions.
 * user_source=external: users and role names from your existing tables (config voters.security.external).
 */
class LoginService
{
    public function __construct(private CurrentUser $current, private AuditService $audit) {}

    /** @return string|null error code: credentials | assembly; 'otp' / 'otp-setup' = password right, second step next; null = logged in */
    public function attempt(string $username, string $password, $assembly): ?string
    {
        $assembly = $assembly === null || $assembly === '' ? null : (int) $assembly;
        $user = config('voters.security.user_source') === 'external' ? $this->external($username) : $this->app($username);

        if (!$user || !$user['enabled'] || !AppUser::checkPassword($password, $user['hash'], $user['encoding'])) {
            $this->audit->log('LOGIN_FAILED', 'USER', null, 'BadCredentials', $username ?: '?');
            return 'credentials';
        }
        // a user locked to an assembly may only log in to that one; the chosen assembly must exist
        if (($user['assembly_no'] !== null && $user['assembly_no'] !== $assembly)
            || ($assembly !== null && !Assembly::where('assembly_no', $assembly)->exists() && !Voter::where('assembly_no', $assembly)->exists())) {
            $this->audit->log('LOGIN_FAILED', 'USER', null, 'AssemblyNotAllowed', $username);
            return 'assembly';
        }
        $payload = [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'roles' => $user['roles'],
            'permissions' => $user['permissions'],
            'locked_assembly' => $user['assembly_no'],
            'work_assembly' => $assembly,
            'work_assembly_name' => $assembly === null ? null : (Assembly::where('assembly_no', $assembly)->value('name') ?? ''),
        ];
        // second step (app users only): code from the authenticator app or by SMS / WhatsApp, unless this device is trusted
        if (isset($user['id']) && \Illuminate\Support\Facades\Schema::hasColumn('app_users', 'otp_method')) {
            $u = AppUser::find($user['id']);
            $two = app(TwoStep::class);
            if ($two->required($u, $user['permissions']) && !($u->otp_method && TwoStep::trusted($u))) {
                session()->regenerate();
                session([TwoStep::PENDING => ['user_id' => $u->id, 'payload' => $payload, 'at' => time(), 'tries' => 0]]);
                $this->audit->log('LOGIN_STEP1', 'USER', $u->id, $u->otp_method ? 'code asked' : 'two-step set-up required', $user['username']);
                return $u->otp_method ? 'otp' : 'otp-setup';
            }
        }
        $this->finish($payload, $user['id'] ?? null);
        return null;
    }

    /** Completes a login (after the password, and the second step when there is one). */
    public function finish(array $payload, ?int $userId, string $how = ''): void
    {
        session()->forget(TwoStep::PENDING);
        $this->current->login($payload);
        if ($userId) AppUser::whereKey($userId)->update(['last_login' => now()]);
        $this->audit->log('LOGIN', 'USER', null, $how ?: null, $payload['username']);
    }

    private function app(string $username): ?array
    {
        $u = AppUser::with('roles')->whereRaw('lower(username) = ?', [mb_strtolower($username)])->first();
        if (!$u) return null;
        return ['id' => $u->id, 'username' => $u->username, 'full_name' => $u->full_name, 'enabled' => $u->enabled,
            'hash' => $u->password_hash, 'encoding' => 'bcrypt', 'assembly_no' => $u->assembly_no === null ? null : (int) $u->assembly_no,
            'roles' => $u->roles->pluck('code')->all(), 'permissions' => $u->permissions()];
    }

    private function external(string $username): ?array
    {
        $cfg = config('voters.security.external');
        $db = $cfg['connection'] ? DB::connection($cfg['connection']) : DB::connection();
        $row = $db->selectOne($cfg['users_query'], [$username]);
        if (!$row) return null;
        $row = array_change_key_case((array) $row, CASE_LOWER);
        $vals = array_values((array) $row);
        $map = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $cfg['role_map']))) as $pair) {
            [$from, $to] = array_map('trim', explode('=', $pair) + [1 => '']);
            if ($from !== '') $map[strtoupper($from)] = strtoupper($to);
        }
        $codes = [];
        foreach ($db->select($cfg['roles_query'], [$username]) as $r) {
            $name = strtoupper(trim((string) array_values((array) $r)[0]));
            $codes[] = $map[$name] ?? $name;
        }
        $roles = Role::whereIn('code', $codes)->get();
        $perms = DB::table('role_permissions')->whereIn('role_id', $roles->pluck('id'))->distinct()->pluck('permission')->all();
        return ['username' => (string) $vals[0], 'full_name' => $row['full_name'] ?? (string) $vals[0],
            'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
            'hash' => (string) $vals[1], 'encoding' => $cfg['password_encoding'] ?? 'bcrypt',
            'assembly_no' => isset($row['assembly_no']) && $row['assembly_no'] !== null ? (int) $row['assembly_no'] : null,
            'roles' => $roles->pluck('code')->all(), 'permissions' => $perms];
    }
}
