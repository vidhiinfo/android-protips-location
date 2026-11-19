<?php

namespace App\Http\Controllers;

use App\Models\{AppUser, Assembly, AuditLog, Role, Voter};
use App\Support\Permission;
use App\Support\Validation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private function external(): bool { return config('voters.security.user_source') === 'external'; }

    // ---------------------------------------------------------------- users
    public function users()
    {
        return view('admin.users', ['items' => AppUser::with('roles')->orderBy('username')->get(), 'externalUsers' => $this->external()]);
    }

    public function userForm(?int $id = null)
    {
        if ($this->external()) return redirect('/admin/users');
        $item = $id ? AppUser::with('roles')->findOrFail($id) : new AppUser(['enabled' => true]);
        return view('admin.user-form', ['item' => $item, 'roleIds' => $item->exists ? $item->roles->pluck('id')->all() : [],
            'roleList' => Role::orderByDesc('system_role')->orderBy('name')->get(), 'assemblyList' => Assembly::orderBy('assembly_no')->get()]);
    }

    public function saveUser(Request $r)
    {
        abort_if($this->external(), 403);
        $item = $r->input('id') ? AppUser::findOrFail($r->input('id')) : new AppUser();
        $isNew = !$item->exists;
        $roleIds = array_map('intval', (array) $r->input('roleIds', []));
        $roles = Role::whereIn('id', $roleIds)->get();
        $data = Validation::clean($r->only(['username', 'full_name', 'assembly_no', 'scope_wards', 'scope_booths', 'scope_serial_from', 'scope_serial_to']), []);
        foreach (['scope_wards', 'scope_booths'] as $k) $data[$k] = implode(', ', \App\Support\Area::ints($data[$k] ?? '')) ?: null;   // "101,102" -> "101, 102"
        foreach (['scope_serial_from', 'scope_serial_to'] as $k) $data[$k] = isset($data[$k]) && $data[$k] !== '' ? $data[$k] : null;
        $data['enabled'] = $r->boolean('enabled');
        $pass = (string) $r->input('newPassword');
        $v = validator($data, ['username' => [$isNew ? 'required' : 'nullable', 'regex:/^[A-Za-z0-9._-]{3,50}$/'], 'full_name' => ['required', 'max:100'],
            'assembly_no' => ['nullable', 'integer', 'min:1'], 'scope_serial_from' => ['nullable', 'integer', 'min:1'], 'scope_serial_to' => ['nullable', 'integer', 'min:1']], Validation::messages());
        $self = !$isNew && strcasecmp($item->username, $this->user()->username()) === 0;
        $v->after(function ($val) use ($isNew, $data, $pass, $roles, $self) {
            if ($isNew && AppUser::whereRaw('lower(username) = ?', [mb_strtolower((string) $data['username'])])->exists()) $val->errors()->add('username', t('validation.duplicate'));
            if (($isNew || $pass !== '') && strlen($pass) < 8) $val->errors()->add('global', t('validation.password'));
            if ($roles->isEmpty()) $val->errors()->add('global', t('admin.error.noRole'));
            // access area: needs an assembly; a serial range needs exactly one booth and from <= to
            $hasArea = ($data['scope_wards'] ?? null) || ($data['scope_booths'] ?? null) || ($data['scope_serial_from'] ?? null) || ($data['scope_serial_to'] ?? null);
            if ($hasArea && empty($data['assembly_no'])) $val->errors()->add('scope_wards', t('area.needsAssembly'));
            $serial = ($data['scope_serial_from'] ?? null) || ($data['scope_serial_to'] ?? null);
            if ($serial && count(\App\Support\Area::ints($data['scope_booths'] ?? '')) !== 1) $val->errors()->add('scope_booths', t('area.oneBooth'));
            if (($data['scope_serial_from'] ?? null) && ($data['scope_serial_to'] ?? null) && (int) $data['scope_serial_from'] > (int) $data['scope_serial_to']) $val->errors()->add('scope_serial_from', t('area.fromTo'));
            if ($self && (!$data['enabled'] || !$roles->contains(fn ($ro) => $ro->has('USER_ADMIN')))) $val->errors()->add('global', t('admin.error.self'));
        });
        if ($v->fails()) {
            $item->fill(array_diff_key($data, ['username' => $isNew ? null : 1]));
            return response()->view('admin.user-form', ['item' => $item, 'roleIds' => $roleIds, 'errors' => $v->errors(),
                'roleList' => Role::orderByDesc('system_role')->orderBy('name')->get(), 'assemblyList' => Assembly::orderBy('assembly_no')->get()]);
        }
        if ($isNew) { $item->username = $data['username']; $item->created_at = now(); }
        $item->full_name = $data['full_name'];
        $item->enabled = $data['enabled'];
        $item->assembly_no = $data['assembly_no'] ?? null;
        foreach (['scope_wards', 'scope_booths', 'scope_serial_from', 'scope_serial_to'] as $k) $item->$k = $data[$k] ?? null;
        if ($pass !== '') $item->password_hash = AppUser::hashPassword($pass);
        $item->save();
        $item->roles()->sync($roles->pluck('id'));
        $this->audit()->log($isNew ? 'CREATE' : 'UPDATE', 'USER', $item->id, $item->username . ' roles=' . $roles->pluck('code')->implode(',')
            . ($item->enabled ? '' : ' disabled') . ($pass !== '' && !$isNew ? ' password reset' : ''));
        return redirect('/admin/users')->with('success', 'flash.saved');
    }

    public function deleteUser(int $id)
    {
        $u = AppUser::findOrFail($id);
        if (strcasecmp($u->username, $this->user()->username()) === 0) return redirect('/admin/users')->with('error', 'admin.error.self');
        $u->roles()->detach();
        $u->delete();
        $this->audit()->log('DELETE', 'USER', $id, $u->username);
        return redirect('/admin/users')->with('success', 'flash.deleted');
    }

    // ---------------------------------------------------------------- roles
    public function roles()
    {
        return view('admin.roles', ['items' => Role::orderByDesc('system_role')->orderBy('name')->get(), 'permissions' => Permission::codes()]);
    }

    public function roleForm(?int $id = null)
    {
        $item = $id ? Role::findOrFail($id) : new Role();
        return view('admin.role-form', ['item' => $item, 'perms' => $item->exists ? $item->permissions() : [], 'groups' => Permission::groups()]);
    }

    public function saveRole(Request $r)
    {
        $item = $r->input('id') ? Role::findOrFail($r->input('id')) : new Role();
        $isNew = !$item->exists;
        $perms = array_values(array_intersect((array) $r->input('perms', []), Permission::codes()));
        $data = Validation::clean($r->only(['code', 'name', 'description']), []);
        $data['code'] = strtoupper((string) ($data['code'] ?? ''));
        $v = validator($data, ['code' => ['required', 'regex:/^[A-Za-z0-9_]{2,40}$/'], 'name' => ['required', 'max:100'], 'description' => ['nullable', 'max:300']], Validation::messages());
        $v->after(function ($val) use ($item, $isNew, $data, $perms) {
            if (Role::where('code', $data['code'])->when(!$isNew, fn ($q) => $q->where('id', '<>', $item->id))->exists()) $val->errors()->add('code', t('validation.duplicate'));
            if (!$isNew && $item->system_role && $item->code !== $data['code']) $val->errors()->add('code', t('admin.error.systemRole'));
            if (!$isNew && $item->code === 'ADMIN' && !in_array('USER_ADMIN', $perms, true)) $val->errors()->add('global', t('admin.error.adminRole'));
        });
        if ($v->fails()) { $item->fill($data); return response()->view('admin.role-form', ['item' => $item, 'perms' => $perms, 'groups' => Permission::groups(), 'errors' => $v->errors()]); }
        $item->fill($data)->save();
        $item->setPermissions($perms);
        $this->audit()->log($isNew ? 'CREATE' : 'UPDATE', 'ROLE', $item->id, $item->code . ': ' . implode(',', $perms));
        return redirect('/admin/roles')->with('success', 'admin.flash.roleSaved');
    }

    public function deleteRole(int $id)
    {
        $role = Role::findOrFail($id);
        if ($role->system_role || $role->userCount() > 0) return redirect('/admin/roles')->with('error', $role->system_role ? 'admin.error.systemRole' : 'flash.inUse');
        $role->setPermissions([]);
        $role->delete();
        $this->audit()->log('DELETE', 'ROLE', $id, $role->code);
        return redirect('/admin/roles')->with('success', 'flash.deleted');
    }

    // ---------------------------------------------------------------- audit & system
    public function auditLog(Request $r)
    {
        $q = AuditLog::query()->orderByDesc('at')->orderByDesc('id');
        if ($u = $r->query('user')) $q->whereRaw('lower(username) like ?', ['%' . mb_strtolower($u) . '%']);
        if ($a = $r->query('action')) $q->where('action', $a);
        if ($e = $r->query('entity')) $q->where('entity', $e);
        if (($ip = trim((string) $r->query('ip'))) !== '') $q->where('ip_address', 'like', $ip . '%');
        return view('admin.audit', ['page' => $q->paginate(50)->withQueryString(), 'user' => $r->query('user'), 'ip' => $r->query('ip'), 'action' => $r->query('action'), 'entity' => $r->query('entity'),
            'actions' => ['CREATE', 'UPDATE', 'DELETE', 'IMPORT', 'RESULT_SAVE', 'LOGIN', 'LOGIN_FAILED', 'PASSWORD_CHANGE', 'MSG_CAMPAIGN', 'MSG_CANCEL', 'MSG_TEST', 'OPT_OUT'],
            'entities' => ['VOTER', 'USER', 'ROLE', 'ASSEMBLY', 'BOOTH', 'PARTY', 'ELECTION', 'CANDIDATE', 'RESULT', 'CAMPAIGN', 'TEMPLATE', 'OPTOUT']]);
    }

    public function system()
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $info = ['admin.sys.db' => $driver . ' ' . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION)];
        if ($driver === 'mysql') {
            $info['admin.sys.charset'] = DB::selectOne("select concat(default_character_set_name, ' / ', default_collation_name) as c from information_schema.schemata where schema_name = database()")->c ?? '';
            $info['admin.sys.tableCharset'] = DB::selectOne("select table_collation as c from information_schema.tables where table_schema = database() and table_name = 'voters'")->c ?? '';
        } else {
            $info['admin.sys.charset'] = 'Unicode (UTF-8)';
        }
        $info += ['admin.sys.userSource' => config('voters.security.user_source'), 'admin.sys.java' => 'PHP ' . PHP_VERSION . ' / Laravel ' . app()->version(),
            'admin.sys.voters' => Voter::count(), 'admin.sys.users' => AppUser::count(), 'admin.sys.roles' => Role::count(), 'admin.sys.audit' => AuditLog::count()];
        return view('admin.system', ['info' => $info]);
    }
}
