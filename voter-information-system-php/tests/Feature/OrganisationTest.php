<?php

namespace Tests\Feature;

use App\Models\AppUser;
use App\Models\Mandal;
use App\Models\Voter;
use App\Models\Worker;
use App\Services\Org\LokSabhaService;
use App\Services\Org\OrganisationService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganisationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        Worker::query()->delete();
    }

    private function login() { return $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']); }

    private function w(string $role, ?int $booth = null, array $extra = []): Worker
    {
        return Worker::create(['name' => $role . ($booth ? " $booth" : ''), 'assembly_no' => 45, 'booth_no' => $booth, 'role' => $role, 'active' => true, 'created_at' => now()] + $extra);
    }

    public function test_chain_gaps_and_who_works_under_whom(): void
    {
        $this->login();
        $this->post('/organisation/45/mandals', ['name' => 'Mandal East', 'booths' => '1-2, 999'])->assertRedirect();
        $m = Mandal::where('name', 'Mandal East')->firstOrFail();
        $this->assertSame([1, 2], $m->boothList(), 'ranges expanded, unknown booths dropped');
        $ls = Worker::create(['name' => 'LS head', 'assembly_no' => Worker::ALL_ASSEMBLIES, 'role' => 'LS_INCHARGE', 'active' => true]);
        $ac = $this->w('AC_INCHARGE');
        $mi = $this->w('MANDAL_INCHARGE', null, ['mandal_id' => $m->id]);
        $p1 = $this->w('BOOTH_PRESIDENT', 1);
        $pp = $this->w('PANNA_PRAMUKH', 1);
        $p3 = $this->w('BOOTH_PRESIDENT', 3);   // booth 3 is outside the mandal
        $ids = Voter::where('assembly_no', 45)->where('booth_no', 1)->orderBy('serial_no')->limit(10)->pluck('id');
        Voter::whereIn('id', $ids)->update(['worker_id' => $pp->id]);

        $org = app(OrganisationService::class);
        $under = fn (Worker $w) => $org->under($w)->pluck('id')->sort()->values()->all();
        $this->assertEqualsCanonicalizing([$p1->id, $pp->id], $under($mi));
        $this->assertEqualsCanonicalizing([$mi->id, $p1->id, $pp->id, $p3->id], $under($ac));
        $this->assertContains($ac->id, $under($ls));
        $this->assertSame([$pp->id], $under($p1));
        $this->assertSame([], $under($pp));

        $s = $org->summary([45])[45];
        $booths = count(app(\App\Services\Voters\VoterService::class)->booths(45));
        $this->assertSame([1, 0, $booths, 2, 1, 10], [$s['mandals'], $s['mandalsNoHead'], $s['booths'], $s['withPresident'], $s['withPanna'], $s['pannaVoters']]);
        $this->assertSame($booths - 2, $s['boothsNoMandal']);

        $this->get('/organisation')->assertOk()->assertSee('LS head')->assertSee(t('org.gapPresident', $booths - 2));
        $this->get('/organisation/45')->assertOk()->assertSee('Mandal East')->assertSee($mi->name)->assertSee(t('org.teamTask'));
    }

    public function test_team_task_and_login_access_follow_the_chain(): void
    {
        $this->login();
        $m = Mandal::create(['assembly_no' => 45, 'name' => 'M1', 'booths' => '1, 2', 'active' => true]);
        $mi = $this->w('MANDAL_INCHARGE', null, ['mandal_id' => $m->id]);
        $this->w('BOOTH_PRESIDENT', 1); $this->w('BOOTH_AGENT', 2); $this->w('BOOTH_PRESIDENT', 3);
        $this->post('/organisation/team-task', ['leader_id' => $mi->id, 'title' => 'Door to door', 'kind' => 'VISIT'])->assertRedirect();
        $this->assertSame(2, DB::table('worker_tasks')->where('title', 'Door to door')->count());
        $this->post('/organisation/team-task', ['leader_id' => $mi->id, 'title' => 'Presidents only', 'kind' => 'MEETING', 'roles' => ['BOOTH_PRESIDENT']]);
        $this->assertSame(1, DB::table('worker_tasks')->where('title', 'Presidents only')->count());

        $u = AppUser::create(['username' => 'mandal1', 'password_hash' => bcrypt('x12345678'), 'full_name' => 'Mandal One', 'enabled' => true, 'created_at' => now()]);
        $mi->update(['username' => 'mandal1']);
        $this->post('/organisation/person/' . $mi->id . '/access')->assertRedirect();
        $u->refresh();
        $this->assertSame([45, '1, 2'], [(int) $u->assembly_no, $u->scope_booths]);
    }

    public function test_worker_form_new_roles_and_lok_sabha_overview(): void
    {
        $this->login();
        $this->post('/workers', ['name' => 'Big Boss', 'assembly_no' => 45, 'role' => 'LS_INCHARGE', 'active' => 1])->assertRedirect();
        $this->assertSame(Worker::ALL_ASSEMBLIES, Worker::where('name', 'Big Boss')->value('assembly_no'));
        $this->post('/workers', ['name' => 'No Mandal', 'assembly_no' => 45, 'role' => 'MANDAL_INCHARGE', 'active' => 1])->assertSessionHas('error', 'org.pickMandal');
        $this->w('BOOTH_PRESIDENT', 1);

        $data = app(LokSabhaService::class)->overview([45 => 'Demo', 46 => 'Other']);
        $row = collect($data['rows'])->keyBy('no')[45];
        $this->assertSame(Voter::where('assembly_no', 45)->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))->count(), $row['voters']);
        $this->assertSame(1, $row['withPresident']);
        $this->assertSame('red', $row['flag'], 'almost no survey and presidents yet');
        $this->assertSame($data['total']['voters'], array_sum(array_column($data['rows'], 'voters')));

        config(['voters.branding.level' => 'loksabha']);
        $this->get('/dashboard?assemblyNo=45')->assertSee(url('/loksabha'), false);
        $this->get('/loksabha')->assertOk()->assertSee(t('ls.title'))->assertSee('Big Boss')->assertSee(t('ls.flag.red'));
        config(['voters.branding.level' => 'assembly']);
        $this->get('/dashboard?assemblyNo=45')->assertDontSee(url('/loksabha') . '"', false);
    }

    public function test_worker_picked_from_the_voter_list(): void
    {
        $this->login();
        $v = Voter::where('assembly_no', 45)->whereNotNull('booth_no')->orderBy('id')->first();
        $v->update(['mobile' => '9876501234']);
        // "Make worker" on the voter's page opens a filled-in form
        $this->get('/voters/' . $v->id)->assertOk()->assertSee(t('worker.makeWorker'));
        $this->get('/workers/new?epic=' . $v->epic_no)->assertOk()->assertSee($v->name)->assertSee('9876501234')->assertSee(t('worker.linkedTo'));
        // saving with only the EPIC takes name, mobile, assembly and booth from the roll
        $this->post('/workers', ['epic_no' => strtolower($v->epic_no), 'name' => '', 'assembly_no' => 46, 'role' => 'BOOTH_PRESIDENT', 'active' => 1])->assertRedirect();
        $w = Worker::where('voter_id', $v->id)->firstOrFail();
        $this->assertSame([$v->name, '9876501234', 45, (int) $v->booth_no], [$w->name, $w->mobile, (int) $w->assembly_no, (int) $w->booth_no]);
        $this->get('/voters/' . $v->id)->assertSee(t('worker.role.BOOTH_PRESIDENT'))->assertDontSee(t('worker.makeWorker'));
        $this->get('/workers/' . $w->id)->assertSee($v->epic_no);
        // the same voter cannot be two workers; an unknown EPIC is refused
        $this->post('/workers', ['epic_no' => $v->epic_no, 'assembly_no' => 45, 'role' => 'VOLUNTEER', 'active' => 1])->assertSessionHas('error', t('worker.voterTaken', $v->name));
        $this->post('/workers', ['epic_no' => 'ZZZ9999999', 'assembly_no' => 45, 'role' => 'VOLUNTEER', 'active' => 1])->assertSessionHas('error', t('worker.epicNotFound', 'ZZZ9999999'));
        // clearing the EPIC unlinks, the worker stays
        $this->post('/workers/' . $w->id, ['epic_no' => '', 'name' => $w->name, 'assembly_no' => 45, 'booth_no' => $w->booth_no, 'role' => 'BOOTH_PRESIDENT', 'active' => 1])->assertRedirect();
        $this->assertNull($w->fresh()->voter_id);
    }

    public function test_voter_search_and_add_from_organisation_gaps(): void
    {
        $this->login();
        $v = Voter::where('assembly_no', 45)->whereNotNull('booth_no')->orderBy('id')->first();
        $word = explode(' ', $v->name)[0];
        // search by EPIC and by name; only the chosen assembly
        $this->getJson('/voters/pick?q=' . $v->epic_no . '&assemblyNo=45')->assertOk()->assertJsonFragment(['epic' => $v->epic_no, 'name' => $v->name, 'taken' => false]);
        $res = $this->getJson('/voters/pick?q=' . urlencode($word) . '&assemblyNo=45&boothNo=' . $v->booth_no)->assertOk()->json();
        $this->assertNotEmpty($res);
        $this->assertTrue(collect($res)->every(fn ($x) => $x['assembly'] == 45 && stripos($x['name'], $word) !== false));
        $this->assertEquals($v->booth_no, $res[0]['booth'], 'the booth being filled comes first');
        $this->getJson('/voters/pick?q=a')->assertExactJson([]);
        // "+ Add" on a booth without a president opens the form with role and booth chosen and returns to the page
        $this->get('/organisation/45')->assertSee('role=BOOTH_PRESIDENT&boothNo=', false);
        $this->get('/workers/new?assemblyNo=45&role=BOOTH_PRESIDENT&boothNo=' . $v->booth_no, ['referer' => url('/organisation/45')])
            ->assertOk()->assertSee('value="BOOTH_PRESIDENT" data-base="BOOTH_PRESIDENT" selected', false)->assertSee('name="return_to" value="/organisation/45"', false);
        // a typed name never overrides the roll name once a voter is picked
        $this->post('/workers', ['epic_no' => $v->epic_no, 'name' => 'Misspelt Name', 'assembly_no' => 45, 'booth_no' => $v->booth_no, 'role' => 'BOOTH_PRESIDENT',
            'active' => 1, 'return_to' => '/organisation/45'])->assertRedirect(url('/organisation/45'));
        $this->assertSame($v->name, Worker::where('voter_id', $v->id)->value('name'));
        $this->getJson('/voters/pick?q=' . $v->epic_no)->assertJsonFragment(['taken' => true]);
    }

    public function test_grievance_complainant_picked_from_the_voter_list(): void
    {
        $this->login();
        $v = Voter::where('assembly_no', 45)->whereNotNull('booth_no')->orderBy('id')->first();
        $this->get('/grievances/new?assemblyNo=45')->assertOk()->assertSee(t('gr.pickVoter'))->assertSee('js/worker-pick.js', false);
        $this->getJson('/voters/pick?for=grievance&q=' . $v->epic_no)->assertOk()->assertJsonFragment(['id' => $v->id, 'taken' => false]);
        $this->post('/grievances', ['voter_id' => $v->id, 'name' => 'typo name', 'assembly_no' => 45, 'booth_no' => $v->booth_no, 'category' => \App\Models\Grievance::CATEGORIES[0],
            'subject' => 'Water', 'priority' => 'NORMAL', 'status' => 'OPEN'])->assertRedirect();
        $this->assertSame($v->name, \App\Models\Grievance::where('voter_id', $v->id)->latest('id')->value('name'));
    }
}
