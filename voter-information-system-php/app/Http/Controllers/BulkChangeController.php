<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/** Voters > Bulk changes: history of bulk updates, and undo (values changed again later are kept). */
class BulkChangeController extends Controller
{
    public function index()
    {
        $q = DB::table('bulk_changes')->orderByDesc('id')->limit(100);
        if ($this->user()->scoped()) $q->where('assembly_no', $this->user()->workAssembly());
        if (!$this->user()->can('USER_ADMIN')) $q->where('created_by', $this->user()->username());
        return view('voters.bulk-changes', ['list' => $q->get()]);
    }

    public function undo(int $id)
    {
        $c = DB::table('bulk_changes')->find($id);
        abort_if(!$c || ($this->user()->scoped() && (int) $c->assembly_no !== $this->user()->workAssembly()), 404, t('error.notFound'));
        abort_unless($this->user()->can('USER_ADMIN') || $c->created_by === $this->user()->username(), 403);
        if ($c->status !== 'APPLIED') return redirect('/voters/bulk-changes');
        $restored = 0; $skipped = 0;
        DB::transaction(function () use ($c, &$restored, &$skipped) {
            DB::table('bulk_change_rows')->where('bulk_change_id', $c->id)->orderBy('id')->chunkById(500, function ($rows) use (&$restored, &$skipped) {
                $current = DB::table('voters')->whereIn('id', $rows->pluck('voter_id'))->get()->keyBy('id');
                foreach ($rows as $r) {
                    $v = $current[$r->voter_id] ?? null;
                    if (!$v) { $skipped++; continue; }
                    $before = json_decode($r->before, true) ?: []; $after = json_decode($r->after, true) ?: [];
                    $upd = [];
                    foreach ($before as $f => $old) {
                        // only values still as the bulk update left them; anything edited since is kept
                        if ((string) ($v->$f ?? '') === (string) ($after[$f] ?? '')) $upd[$f] = $old; else $skipped++;
                    }
                    if ($upd) { DB::table('voters')->where('id', $v->id)->update($upd + ['updated_at' => now()]); $restored++; }
                }
            });
            DB::table('bulk_changes')->where('id', $c->id)->update(['status' => 'UNDONE', 'undone_by' => $this->user()->username(), 'undone_at' => now(), 'skipped' => $skipped]);
        });
        $this->audit()->log('UNDO', 'BULK', $c->id, "bulk update #{$c->id}: $restored voters restored, $skipped values kept (changed since)");
        return redirect('/voters/bulk-changes')->with('success', t('bulkUndo.done', $restored, $skipped));
    }
}
