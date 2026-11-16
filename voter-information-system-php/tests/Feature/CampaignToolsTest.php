<?php

namespace Tests\Feature;

use App\Models\{Election, Grievance, PollMark, Voter, Worker};
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    private function login(string $u = 'admin', string $p = 'admin@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    private function worker(array $a = []): Worker
    {
        return Worker::create($a + ['name' => 'Ramesh', 'mobile' => '9876500001', 'assembly_no' => 45, 'booth_no' => 1, 'role' => 'PANNA_PRAMUKH', 'active' => true, 'created_at' => now()]);
    }

    public function test_worker_crud_assign_range_and_leaderboard(): void
    {
        $this->login();
        $this->post('/workers', ['name' => 'Suresh', 'mobile' => '9876500002', 'assembly_no' => 45, 'booth_no' => 1, 'role' => 'BOOTH_PRESIDENT', 'active' => 1])->assertRedirect();
        $w = Worker::where('name', 'Suresh')->firstOrFail();
        $this->post("/workers/{$w->id}/assign", ['booth_no' => 1, 'from' => 1, 'to' => 10])->assertRedirect();
        $n = Voter::where('assembly_no', 45)->where('booth_no', 1)->whereBetween('serial_no', [1, 10])->count();
        $this->assertSame($n, Voter::where('worker_id', $w->id)->count());
        $this->get('/workers?assemblyNo=45')->assertOk()->assertSee('Suresh');
        $this->get("/workers/{$w->id}")->assertOk()->assertSee((string) $n);
        $this->get("/voters?workerId={$w->id}")->assertOk()->assertSee($n . ' ', false);
        $this->post("/workers/{$w->id}/unassign")->assertRedirect();
        $this->assertSame(0, Voter::where('worker_id', $w->id)->count());
    }

    public function test_panna_split_divides_booth_and_keeps_houses_together(): void
    {
        $a = $this->worker(['name' => 'P1']); $b = $this->worker(['name' => 'P2']); $c = $this->worker(['name' => 'P3']);
        $this->login();
        $this->post('/workers/panna', ['assemblyNo' => 45, 'boothNo' => 1])->assertRedirect()->assertSessionHas('success');
        $total = Voter::where('assembly_no', 45)->where('booth_no', 1)->where(fn ($q) => $q->where('deleted', false)->orWhereNull('deleted'))->count();
        $counts = [$a, $b, $c];
        $this->assertSame($total, Voter::whereIn('worker_id', [$a->id, $b->id, $c->id])->count());
        foreach ($counts as $w) $this->assertGreaterThan(0, Voter::where('worker_id', $w->id)->count());
        $houses = Voter::where('assembly_no', 45)->where('booth_no', 1)->whereNotNull('worker_id')->get()
            ->groupBy(fn ($v) => \App\Support\Text::houseKey($v->house_no) . '|' . mb_strtolower(trim((string) $v->address)));
        foreach ($houses as $k => $members) if (!str_starts_with($k, '|')) $this->assertCount(1, $members->pluck('worker_id')->unique(), "house $k split");
    }

    public function test_worker_login_sees_only_own_voters(): void
    {
        $w = $this->worker(['username' => 'operator']);
        $mine = Voter::where('assembly_no', 45)->where('booth_no', 1)->orderBy('serial_no')->limit(3)->get();
        Voter::whereIn('id', $mine->pluck('id'))->update(['worker_id' => $w->id]);
        $other = Voter::whereNull('worker_id')->first();
        $this->login('operator', 'operator@123');
        $this->get('/voters')->assertOk()->assertSee($mine[0]->epic_no)->assertDontSee($other->epic_no);
        $this->get('/voters/' . $mine[0]->id)->assertOk();
        $this->get('/voters/' . $other->id)->assertNotFound();
        $this->get('/survey?assemblyNo=45&boothNo=1')->assertOk()->assertSee(url('/survey/house/' . $mine[0]->id), false);
    }

    public function test_poll_day_marking_grid_live_and_pending_supporters(): void
    {
        $e = Election::where('current', true)->firstOrFail();
        Voter::where('assembly_no', 45)->where('booth_no', 1)->update(['political_status' => 'Supporter', 'deleted' => false]);
        $v = Voter::where('assembly_no', 45)->where('booth_no', 1)->orderBy('serial_no')->first();
        $this->login();
        $this->get('/pollday?assemblyNo=45&boothNo=1')->assertOk();
        $this->post('/pollday/mark', ['assemblyNo' => 45, 'boothNo' => 1, 'q' => (string) $v->serial_no])->assertSessionHas('success');
        $this->assertTrue(PollMark::where('election_id', $e->id)->where('voter_id', $v->id)->exists());
        $this->post('/pollday/mark', ['assemblyNo' => 45, 'boothNo' => 1, 'q' => $v->epic_no])->assertSessionHas('error');   // already marked
        $this->post('/pollday/mark', ['assemblyNo' => 45, 'boothNo' => 1, 'q' => '99999'])->assertSessionHas('error');
        $other = Voter::where('assembly_no', 45)->where('booth_no', 1)->where('id', '<>', $v->id)->first();
        $this->postJson('/pollday/toggle', ['voter_id' => $other->id])->assertOk()->assertJson(['voted' => true]);
        $this->postJson('/pollday/toggle', ['voter_id' => $other->id])->assertOk()->assertJson(['voted' => false]);
        $this->get('/pollday/grid?assemblyNo=45&boothNo=1')->assertOk()->assertSee('poll-cell voted', false);
        $booth = Voter::where('assembly_no', 45)->where('booth_no', 1)->count();
        $this->get('/pollday/live?assemblyNo=45')->assertOk()->assertSee((string) ($booth - 1));
        $this->get('/voters?assemblyNo=45&boothNo=1&sentiment=S&voted=no')->assertOk()->assertDontSee($v->epic_no)->assertSee($other->epic_no);
        $this->post('/pollday/unmark', ['assemblyNo' => 45, 'boothNo' => 1, 'voter_id' => $v->id])->assertRedirect();
        $this->assertFalse(PollMark::where('voter_id', $v->id)->exists());
    }

    public function test_voter_slips_pdf(): void
    {
        $this->login();
        $pdf = $this->get('/voters/export/slips?assemblyNo=45&boothNo=1')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_grievance_lifecycle_and_report(): void
    {
        $v = Voter::where('assembly_no', 45)->first();
        $w = $this->worker();
        $this->login();
        $this->get('/grievances/new?voterId=' . $v->id)->assertOk()->assertSee($v->name);
        $this->post('/grievances', ['voter_id' => $v->id, 'name' => $v->name, 'mobile' => '9876500003', 'assembly_no' => 45, 'booth_no' => $v->booth_no,
            'category' => 'WATER', 'subject' => 'No water for 3 days', 'priority' => 'HIGH', 'status' => 'OPEN', 'worker_id' => $w->id])->assertRedirect();
        $g = Grievance::firstOrFail();
        $this->assertSame([$v->id, $w->id, 'OPEN'], [$g->voter_id, $g->worker_id, $g->status]);
        $this->get('/grievances')->assertOk()->assertSee('No water for 3 days');
        $this->post("/grievances/{$g->id}/status", ['status' => 'RESOLVED', 'resolution' => 'Tanker sent'])->assertRedirect();
        $g->refresh();
        $this->assertSame('RESOLVED', $g->status);
        $this->assertNotNull($g->resolved_at);
        $this->get('/grievances')->assertOk()->assertDontSee('No water for 3 days');            // active list
        $this->get('/grievances?status=ALL')->assertOk()->assertSee('No water for 3 days');
        $this->get('/voters/' . $v->id)->assertOk()->assertSee('voterId=' . $v->id, false);
        $this->get('/reports/grievances?assemblyNo=45')->assertOk()->assertSee(t('gr.cat.WATER'));
        $this->post('/grievances', ['name' => 'X', 'assembly_no' => 45, 'category' => 'NOPE', 'subject' => 'x', 'priority' => 'NORMAL', 'status' => 'OPEN'])->assertSessionHasErrors('category');
    }

    public function test_viewer_has_no_access_and_existing_roles_get_new_permissions(): void
    {
        $this->login('viewer', 'viewer@123');
        foreach (['/workers', '/pollday', '/grievances'] as $u) $this->get($u)->assertForbidden();
        $admin = \App\Models\Role::where('code', 'ADMIN')->first();
        foreach (['WORKER_MANAGE', 'POLL_DAY', 'GRIEVANCE'] as $p) $this->assertTrue($admin->has($p));
        $this->assertTrue(\App\Models\Role::where('code', 'OPERATOR')->first()->has('POLL_DAY'));
    }
}
