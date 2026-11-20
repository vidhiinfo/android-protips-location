<?php

namespace App\Http\Controllers;

use App\Services\State\StateFeedService;
use App\Services\State\StateSummaryService;
use Illuminate\Http\Request;

/** State summary across division installations: the feed (division side) and the summary page (state side). */
class StateController extends Controller
{
    /** GET /state-feed with "Authorization: Bearer STATE_FEED_TOKEN": totals only, no personal data. */
    public function feed(Request $r, StateFeedService $feed)
    {
        $token = (string) config('voters.state.feed_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $r->bearerToken()), 403);
        return response()->json($feed->build())->header('Cache-Control', 'no-store');
    }

    public function index(Request $r, StateSummaryService $s)
    {
        abort_unless(StateSummaryService::enabled(), 404, t('state.notSetUp'));
        if ($r->query('refresh') === '1') $s->pull(); else $s->pullIfStale();
        $sum = $s->summary();
        $div = $r->query('division');
        $selected = $div !== null ? collect($sum['divisions'])->firstWhere('name', $div) : null;
        return view('state.index', ['sum' => $sum, 'division' => $selected]);
    }

    public function export(Request $r, StateSummaryService $s)
    {
        abort_unless(StateSummaryService::enabled(), 404);
        $sum = $s->summary();
        $head = [t('state.division'), t('col.assemblyNo'), t('col.assemblyName'), t('dash.tile.totalVoters'), t('gender.MALE'), t('gender.FEMALE'), t('state.booths'),
            t('state.mobile'), t('state.surveyed'), t('state.support'), t('state.neutral'), t('state.opposition'), t('state.workers'), t('state.panna'),
            t('state.grievances'), t('state.voted'), t('state.supportersVoted')];
        $rows = [];
        foreach ($sum['divisions'] as $d) foreach ($d['assemblies'] as $a) $rows[] = [$d['name'], $a['assembly_no'], $a['name'], $a['voters'], $a['male'], $a['female'], $a['booths'],
            $a['mobile'], $a['surveyed'], $a['S'], $a['N'], $a['O'], $a['workers'], $a['panna'], $a['grievances'], $a['voted'], $a['supportersVoted']];
        $file = tempnam(sys_get_temp_dir(), 'state') . '.xlsx';
        $w = new \OpenSpout\Writer\XLSX\Writer();
        $w->openToFile($file);
        $w->addRow(\OpenSpout\Common\Entity\Row::fromValues([t('state.title') . ' - ' . now()->format('d-m-Y H:i')], (new \OpenSpout\Common\Entity\Style\Style())->setFontBold()->setFontSize(13)));
        $w->addRow(\OpenSpout\Common\Entity\Row::fromValues($head, (new \OpenSpout\Common\Entity\Style\Style())->setFontBold()));
        foreach ($rows as $row) $w->addRow(\OpenSpout\Common\Entity\Row::fromValues($row));
        $w->close();
        $this->audit()->log('EXPORT', 'STATE', null, count($rows) . ' assemblies');
        return response()->download($file, 'state-summary-' . date('Y-m-d-Hi') . '.xlsx', ['Content-Type' => self::XLSX])->deleteFileAfterSend();
    }
}
