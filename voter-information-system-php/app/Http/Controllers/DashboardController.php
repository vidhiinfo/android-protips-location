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
            'data' => $a ? $d->build($a, $e, $r->query('party'), $this->ward($r)) : null]);
    }

    public function data(Request $r, VoterService $v, DashboardService $d)
    {
        $a = $this->assembly($r, $v);
        return response()->json($a ? $d->build($a, $this->election($r), $r->query('party'), $this->ward($r)) : []);
    }
}
