<?php

namespace App\Http\Controllers;

use App\Services\Reports\AnalyticsService;
use App\Services\Reports\ReportService;
use App\Services\Voters\VoterService;
use Illuminate\Http\Request;

/** Analytics home: key campaign figures, charts and links to every analysis. */
class AnalyticsController extends Controller
{
    public function index(Request $r, VoterService $v, AnalyticsService $an)
    {
        $a = $this->user()->restrict($r->query('assemblyNo')) ?? array_key_first($v->assemblies());
        $w = is_numeric($r->query('wardNo')) ? (int) $r->query('wardNo') : null;
        $an->ward = $w;
        $data = $a ? $an->summary($a) : null;
        $q = array_filter(['assemblyNo' => $a, 'wardNo' => $w], fn ($x) => $x !== null);
        if ($data) {
            foreach ($data['tiles'] as &$t) {
                if ($t[2]) $t[2] = url($t[2]) . (str_contains($t[2], '?') ? '&' : '?') . http_build_query($q);
            }
        }
        $ids = array_values(array_filter(AnalyticsService::IDS, fn ($id) => !in_array($id, ReportService::ADMIN_ONLY, true) || $this->user()->can('REPORT_SENSITIVE')));
        return view('analytics.index', ['assemblyNo' => $a, 'wardNo' => $w, 'assemblies' => $v->assemblies(), 'wards' => $v->wards($a),
            'data' => $data, 'ids' => $ids, 'query' => http_build_query($q)]);
    }
}
