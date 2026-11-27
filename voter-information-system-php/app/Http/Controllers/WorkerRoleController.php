<?php

namespace App\Http\Controllers;

use App\Models\Worker;
use App\Support\WorkerRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Masters > Worker roles: rename, hide and reorder roles, add own roles that work like a built-in one. */
class WorkerRoleController extends Controller
{
    private function ready()
    {
        if (!Schema::hasTable('worker_roles')) { app(\App\Services\Auth\Defaults::class)->ensure(true); WorkerRoles::flush(); }
        abort_unless(Schema::hasTable('worker_roles'), 503);
        WorkerRoles::seed();
    }

    public function index()
    {
        $this->ready();
        $counts = Worker::selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role')->all();
        return view('masters.worker-roles', ['roles' => WorkerRoles::all(), 'counts' => $counts, 'bases' => Worker::ROLES]);
    }

    public function save(Request $r)
    {
        $this->ready();
        $d = $r->validate(['r' => 'array', 'r.*.name' => 'nullable|string|max:60', 'r.*.name_hi' => 'nullable|string|max:60', 'r.*.name_pa' => 'nullable|string|max:60',
            'r.*.sort' => 'nullable|integer|min:0|max:9999', 'r.*.base' => 'nullable|in:' . implode(',', Worker::ROLES)]);
        $n = 0;
        foreach ($d['r'] ?? [] as $code => $v) {
            $row = DB::table('worker_roles')->where('code', $code)->first();
            if (!$row) continue;
            $upd = ['name' => trim((string) ($v['name'] ?? '')) ?: null, 'name_hi' => trim((string) ($v['name_hi'] ?? '')) ?: null,
                'name_pa' => trim((string) ($v['name_pa'] ?? '')) ?: null, 'sort' => (int) ($v['sort'] ?? $row->sort),
                'active' => $r->boolean("r.$code.active")];
            if (!$row->builtin && !empty($v['base'])) $upd['base'] = $v['base'];   // built-in roles always work as themselves
            if (!$row->builtin && !$upd['name']) $upd['name'] = $row->name;          // an own role keeps an English name
            DB::table('worker_roles')->where('id', $row->id)->update($upd);
            $n++;
        }
        WorkerRoles::flush();
        if (!array_filter(WorkerRoles::all(), fn ($x) => $x['active'])) {   // at least one role must stay selectable
            DB::table('worker_roles')->where('code', 'VOLUNTEER')->update(['active' => true]); WorkerRoles::flush();
        }
        $this->audit()->log('UPDATE', 'WORKER_ROLE', null, "$n roles saved");
        return redirect('/masters/worker-roles')->with('success', 'common.saved');
    }

    public function add(Request $r)
    {
        $this->ready();
        $d = $r->validate(['name' => 'required|string|max:60', 'name_hi' => 'nullable|string|max:60', 'name_pa' => 'nullable|string|max:60',
            'base' => 'required|in:' . implode(',', Worker::ROLES)]);
        $code = WorkerRoles::newCode($d['name']);
        $sort = (int) (collect(WorkerRoles::all())->where('base', $d['base'])->max('sort') ?? 0) + 1;
        DB::table('worker_roles')->insert(['code' => $code, 'base' => $d['base'], 'name' => trim($d['name']), 'name_hi' => trim((string) ($d['name_hi'] ?? '')) ?: null,
            'name_pa' => trim((string) ($d['name_pa'] ?? '')) ?: null, 'sort' => $sort, 'active' => true, 'builtin' => false, 'created_at' => now()]);
        WorkerRoles::flush();
        $this->audit()->log('CREATE', 'WORKER_ROLE', null, "$code ({$d['name']}) works like {$d['base']}");
        return redirect('/masters/worker-roles')->with('success', t('wrole.added', trim($d['name'])));
    }

    public function delete(string $code)
    {
        $this->ready();
        $row = DB::table('worker_roles')->where('code', $code)->first();
        abort_unless($row && !$row->builtin, 404);
        if (Worker::where('role', $code)->exists()) return redirect('/masters/worker-roles')->with('error', t('wrole.inUse', $row->name));
        DB::table('worker_roles')->where('id', $row->id)->delete();
        WorkerRoles::flush();
        $this->audit()->log('DELETE', 'WORKER_ROLE', null, "$code ({$row->name})");
        return redirect('/masters/worker-roles')->with('success', 'common.deleted');
    }
}
