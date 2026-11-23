<?php

namespace Tests\Feature;

use App\Models\Assembly;
use App\Models\Booth;
use App\Models\BoothResult;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Party;
use App\Models\Voter;
use App\Services\Results\PlannerService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function login(string $u = 'admin', string $p = 'admin@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    /** Seat 77, two booths of 100 voters: booth 1 leans to us, booth 2 against. */
    private function seat(): Election
    {
        Assembly::create(['assembly_no' => 77, 'name' => 'Testpur', 'state' => 'Punjab']);
        $serial = 0;
        foreach ([1 => [40, 20, 20, 20], 2 => [5, 2, 78, 15]] as $b => [$s, $n, $o, $u]) {
            Booth::create(['assembly_no' => 77, 'booth_no' => $b, 'name' => "$b - School $b"]);
            $statuses = array_merge(array_fill(0, $s, 'Supporter'), array_fill(0, $n, 'Neutral / Undecided'), array_fill(0, $o, 'Opposition Supporter'), array_fill(0, $u, null));
            foreach ($statuses as $i => $st) Voter::create(['assembly_no' => 77, 'booth_no' => $b, 'serial_no' => ++$serial, 'epic_no' => sprintf('PLN%07d', $serial),
                'name' => "Voter $serial", 'gender' => 'MALE', 'age' => 30, 'political_status' => $st, 'deleted' => false]);
        }
        $e = Election::create(['name' => 'Assembly 2022', 'type' => 'ASSEMBLY', 'election_year' => 2022, 'current' => false]);
        $aaa = Party::create(['name' => 'Alpha', 'short_name' => 'AAA']);
        $bbb = Party::create(['name' => 'Beta', 'short_name' => 'BBB']);
        $c = [Candidate::create(['election_id' => $e->id, 'assembly_no' => 77, 'name' => 'Asha Rani', 'party_id' => $aaa->id, 'ballot_no' => 1]),
            Candidate::create(['election_id' => $e->id, 'assembly_no' => 77, 'name' => 'Vikram', 'party_id' => $bbb->id, 'ballot_no' => 2]),
            Candidate::create(['election_id' => $e->id, 'assembly_no' => 77, 'name' => 'NOTA', 'ballot_no' => 3])];
        foreach ([1 => [40, 30, 2], 2 => [20, 50, 90]] as $b => $votes) {   // NOTA tops booth 2 on purpose: it must never be "the rival"
            foreach ($votes as $i => $v) BoothResult::create(['election_id' => $e->id, 'assembly_no' => 77, 'booth_no' => $b, 'candidate_id' => $c[$i]->id, 'votes' => $v]);
        }
        return $e;
    }

    public function test_votes_needed_per_booth_from_last_result_and_survey(): void
    {
        $e = $this->seat();
        $p = app(PlannerService::class)->plan(77, $e, 'AAA', ['margin' => 3, 'supporter' => 80]);
        // seat: AAA 60 of 232 (25.9%), best rival BBB 80 (34.5%); NOTA (92) is ignored as a rival
        $this->assertSame('BBB', $p['past']['rival_party']);
        $this->assertEqualsWithDelta(100 * 80 / 232 + 3, $p['target_share'], 0.001);
        $this->assertFalse($p['past']['won']);
        $rows = collect($p['rows'])->keyBy('booth_no');
        // booth 1: 72 votes last time from 100 electors -> 72% turnout; our share 40/72 + swing
        $swing = $p['target_share'] - 100 * 60 / 232;
        $b1 = $rows[1];
        $this->assertSame(72, $b1['expected']);
        $this->assertSame((int) ceil(72 * (100 * 40 / 72 + $swing) / 100), $b1['target']);
        $this->assertSame((int) round(40 * 0.8 + 20 * 0.72 * (40 / 72)), $b1['projected']);
        $this->assertSame('swing', $b1['status']);   // gap 9, neutral + not surveyed can give more
        // booth 2: 160 votes from 100 electors is capped at 95% turnout; few supporters, gap bigger than neutral + not surveyed -> tough
        $b2 = $rows[2];
        $this->assertSame(95, $b2['expected']);
        $this->assertSame('tough', $b2['status']);
        $this->assertGreaterThan($b2['reachable'], $b2['gap']);
        $this->assertSame($rows->sum('target'), $p['sum']['target']);
        // what-if: typed turnout and target override last time
        $w = app(PlannerService::class)->plan(77, $e, 'AAA', ['turnout' => 50, 'target' => 30]);
        $this->assertSame(50, collect($w['rows'])->firstWhere('booth_no', 1)['expected']);
        $this->assertEqualsWithDelta(30.0, $w['target_share'], 0.001);
        // no party given: last time's winner; no past election: survey only with the default target
        $this->assertSame('BBB', app(PlannerService::class)->plan(77, $e, null)['party']);
        $none = app(PlannerService::class)->plan(77, null, null);
        $this->assertNull($none['past']);
        $this->assertEqualsWithDelta(PlannerService::DEFAULT_TARGET, $none['target_share'], 0.001);
    }

    public function test_planner_page_exports_and_settings(): void
    {
        $e = $this->seat();
        $this->login();
        $this->get('/planner?assemblyNo=77')->assertOk()->assertSee('Assembly 2022')->assertSee('BBB')->assertSee('Votes needed to win');
        $this->get('/planner?assemblyNo=77&party=AAA&status=tough')->assertOk()->assertSee('Hold the 5 supporters');
        $this->get('/planner/export/xlsx?assemblyNo=77&party=AAA')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get('/planner/export/pdf?assemblyNo=77&party=AAA')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/planner/form20-template?assemblyNo=77')->assertOk();
        // Admin > Settings: our party and safety margin are used by default
        $this->get('/admin/settings')->assertOk()->assertSee('Votes-to-win planner');
        $this->post('/admin/settings', ['s' => ['planner_our_party' => 'AAA', 'planner_safety_margin' => 10, 'planner_supporter_turnout' => 90]])->assertRedirect();
        $this->assertSame('AAA', config('voters.planner.our_party'));
        $p = app(PlannerService::class)->plan(77, $e, config('voters.planner.our_party'));
        $this->assertEqualsWithDelta(100 * 80 / 232 + 10, $p['target_share'], 0.001);
        $this->assertEquals(90, $p['supporter_turnout']);
        $this->get('/planner?assemblyNo=77')->assertOk()->assertSee('AAA</b> got', false);
        // viewers may look but not load results
        $this->post('/logout');
        $this->login('viewer', 'viewer@123');
        $this->get('/planner?assemblyNo=45')->assertOk();
        $this->post('/planner/form20', ['assemblyNo' => 45, 'electionId' => $e->id])->assertForbidden();
    }

    public function test_form20_csv_and_excel_import(): void
    {
        Assembly::create(['assembly_no' => 77, 'name' => 'Testpur', 'state' => 'Punjab']);
        foreach ([1, 2, 3] as $b) Booth::create(['assembly_no' => 77, 'booth_no' => $b, 'name' => "School $b"]);
        Party::create(['name' => 'Alpha', 'short_name' => 'AAA']);
        $this->login();
        $csv = "Form 20 - Testpur 2022\n"
            . "Sl No,Booth No,Polling Station Name,Asha Rani (AAA),Vikram - BBB,NOTA,Total Valid Votes,Rejected,Total\n"
            . "1,1,School 1,\"1,240\",300,12,1552,3,1555\n"
            . "2,2,School 2,200,410,8,618,0,618\n"
            . ",Total,,1440,710,20,2170,3,2173\n";
        $f = UploadedFile::fake()->createWithContent('form20.csv', $csv);
        $this->post('/planner/form20', ['assemblyNo' => 77, 'electionId' => 'new', 'newName' => 'Assembly 2022', 'newYear' => 2022, 'file' => $f])
            ->assertRedirect()->assertSessionHas('success');
        $e = Election::where('name', 'Assembly 2022')->firstOrFail();
        $c = Candidate::with('party')->where('election_id', $e->id)->orderBy('ballot_no')->get();
        $this->assertSame(['Asha Rani', 'Vikram', 'NOTA'], $c->pluck('name')->all());
        $this->assertSame('AAA', $c[0]->party->short_name);      // existing party linked
        $this->assertSame('BBB', $c[1]->party->short_name);       // BBB was not a party yet: added
        $this->assertContains($c[2]->party?->short_name, [null, 'NOTA']);   // NOTA: the NOTA party if there is one
        $this->assertSame(1240, (int) BoothResult::where('candidate_id', $c[0]->id)->where('booth_no', 1)->value('votes'));
        $this->assertSame(6, BoothResult::where('election_id', $e->id)->count());   // the Total row and total columns are not booths / candidates
        // loading again (an Excel file, headings found below a title row) updates instead of adding
        $rep = new \App\Services\Reports\Report('Form 20', ['Booth No', 'Asha Rani (AAA)', 'Vikram', 'NOTA', 'Total']);
        $rep->add([1, 1250, 300, 12, 1562]); $rep->add([3, 90, 95, 1, 186]);
        $path = tempnam(sys_get_temp_dir(), 'f20') . '.xlsx';
        file_put_contents($path, app(\App\Services\Export\ExcelExporter::class)->report($rep));
        $this->post('/planner/form20', ['assemblyNo' => 77, 'electionId' => $e->id, 'file' => new UploadedFile($path, 'form20.xlsx', null, null, true)])
            ->assertSessionHas('success');
        $this->assertSame(3, Candidate::where('election_id', $e->id)->count());
        $this->assertSame(1250, (int) BoothResult::where('candidate_id', $c[0]->id)->where('booth_no', 1)->value('votes'));
        $this->assertSame(9, BoothResult::where('election_id', $e->id)->count());
        // a file for another seat is refused
        $other = UploadedFile::fake()->createWithContent('o.csv', "Form 20 - AC 033 Kartarpur\nBooth No,X (AAA)\n1,5\n");
        $this->post('/planner/form20', ['assemblyNo' => 77, 'electionId' => $e->id, 'file' => $other])->assertSessionHas('error');
        $this->assertSame(9, BoothResult::where('election_id', $e->id)->count());
        // a sheet without a booth column is refused with a reason
        $bad = UploadedFile::fake()->createWithContent('x.csv', "Name,Votes\nA,1\n");
        $this->post('/planner/form20', ['assemblyNo' => 77, 'electionId' => $e->id, 'file' => $bad])->assertSessionHas('error');
        $this->get('/planner?assemblyNo=77&electionId=' . $e->id)->assertOk()->assertSee('AAA')->assertDontSee('Total Valid');
    }
}
