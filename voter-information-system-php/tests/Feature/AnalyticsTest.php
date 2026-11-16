<?php

namespace Tests\Feature;

use App\Models\Voter;
use App\Services\Reports\AnalyticsService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    private function login(string $u = 'admin', string $p = 'admin@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    public function test_every_analysis_opens_exports_and_filters_by_ward(): void
    {
        $this->login();
        $this->get('/analytics?assemblyNo=45')->assertOk()->assertSee(t('an.kpi.support'))->assertSee('data-chart', false);
        foreach (AnalyticsService::IDS as $id) {
            $this->get("/reports/$id?assemblyNo=45")->assertOk()->assertSee(t('report.' . $id));
            $this->get("/reports/$id?assemblyNo=45&wardNo=1")->assertOk();
            $this->get("/reports/$id/export/xlsx?assemblyNo=45")->assertOk();
            $this->get("/reports/$id/export/pdf?assemblyNo=45")->assertOk();
        }
    }

    public function test_sentiment_counts_match_voter_list_links(): void
    {
        Voter::where('assembly_no', 45)->update(['political_status' => null, 'deleted' => false]);
        $ids = Voter::where('assembly_no', 45)->where('booth_no', 1)->orderBy('id')->limit(5)->pluck('id');
        Voter::whereIn('id', $ids->slice(0, 3))->update(['political_status' => 'Supporter']);
        Voter::where('id', $ids[3])->update(['political_status' => 'Opposition']);
        Voter::where('id', $ids[4])->update(['political_status' => 'Supporter', 'deleted' => true]);   // deleted: left out
        $an = app(AnalyticsService::class);
        $row = $an->sentimentByBooth(45, 1)['45/1'];
        $this->assertSame([3, 1, 0], [$row['S'], $row['O'], $row['N']]);
        $r = $an->build('voter-sentiment', 45, 1, null, null);
        $this->assertStringContainsString('sentiment=S', $r->link(0, 3));
        $this->login();
        $this->get('/voters?assemblyNo=45&boothNo=1&sentiment=S&deleted=exclude')->assertOk()->assertSee('3 ');
    }

    public function test_scorecard_grades_and_worker_flags(): void
    {
        Voter::where('assembly_no', 45)->update(['political_status' => null, 'membership_no' => null, 'organisational_status' => null, 'deleted' => false]);
        Voter::where('assembly_no', 45)->where('booth_no', 1)->update(['political_status' => 'Supporter']);
        Voter::where('assembly_no', 45)->where('booth_no', 2)->update(['political_status' => 'Opposition']);
        $r = app(AnalyticsService::class)->build('booth-scorecard', 45, null, null, null);
        $byBooth = collect($r->rows)->keyBy(1);
        $this->assertSame('A', $byBooth[1][13]);
        $this->assertSame('C', $byBooth[2][13]);
        $this->assertSame(100.0, $byBooth[1][8]);
        $w = app(AnalyticsService::class)->build('worker-coverage', 45, null, null, null);
        $this->assertSame(t('an.needsWorkers'), $w->rows[0][11]);
    }

    public function test_beneficiaries_union_and_household_insights(): void
    {
        Voter::where('assembly_no', 45)->update(['beneficiary1' => null, 'beneficiary2' => null, 'beneficiary3' => null]);
        $v = Voter::where('assembly_no', 45)->orderBy('id')->limit(2)->get();
        $v[0]->update(['beneficiary1' => 'Ujjwala', 'beneficiary2' => 'PM Kisan']);
        $v[1]->update(['beneficiary3' => 'ujjwala ']);
        $r = app(AnalyticsService::class)->build('beneficiaries', 45, null, null, null);
        $rows = collect($r->rows)->keyBy(fn ($x) => mb_strtolower($x[0]));
        $this->assertSame(2, $rows['ujjwala'][1]);
        $this->assertSame(1, $rows['pm kisan'][1]);
        $h = app(AnalyticsService::class)->build('household-insights', 45, null, null, null);
        $this->assertSame(Voter::where('assembly_no', 45)->whereNotNull('house_no')->where('house_no', '<>', '')->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'))->count(), $h->totals[2]);
    }

    public function test_sensitive_analyses_need_permission(): void
    {
        $this->login('viewer', 'viewer@123');
        $this->get('/reports/booth-scorecard')->assertOk();
        $this->get('/reports/caste-booth')->assertForbidden();
        $this->get('/reports/team-activity')->assertForbidden();
        $this->get('/analytics')->assertOk()->assertDontSee(t('report.caste-booth'));
    }
}
