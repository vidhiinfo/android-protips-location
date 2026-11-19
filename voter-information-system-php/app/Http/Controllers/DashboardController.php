<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Services\Reports\DashboardService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function assembly(Request $r, VoterService $v): ?int
    {
        return $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($v->assemblies());
    }

    private function election(Request $r): ?Election
    {
        return $r->query('electionId') ? Election::find($r->query('electionId')) : Election::where('current', true)->first();
    }

    private function ward(Request $r): ?int { return is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null; }

    public function page(Request $r, VoterService $v, DashboardService $d)
    {
        $a = $this->assembly($r, $v);
        $e = $this->election($r);
        return view('dashboard', ['assemblyNo' => $a, 'assemblies' => $v->assemblies(), 'elections' => Election::orderByDesc('election_year')->orderBy('name')->get(),
            'election' => $e, 'wards' => $v->wards($a), 'wardNo' => $this->ward($r),
            'data' => $a ? $this->build($r, $a, $d) : null]);
    }

    public function data(Request $r, VoterService $v, DashboardService $d)
    {
        $a = $this->assembly($r, $v);
        return response()->json($a ? $this->build($r, $a, $d) : []);
    }

    /** The dashboard counts the whole assembly: kept for up to 2 minutes per assembly / election / party / ward / access area / language. */
    private function build(Request $r, int $a, DashboardService $d): array
    {
        $ttl = min(120, (int) config('voters.lookup_cache_seconds', 300));
        $e = $this->election($r); $party = $r->query('party'); $ward = $this->ward($r);
        $make = fn () => $d->build($a, $e, $party, $ward);
        if ($ttl <= 0) return $make();
        $key = 'vis.dash.' . md5(json_encode([$a, $e?->id, $party, $ward, \App\Support\Area::current(), $this->user()->workerId(), \App\Support\Lang::current()]));
        return \App\Support\SharedCache::remember($key, $ttl, $make);
    }
}
