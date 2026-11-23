<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Voter;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FieldOfflineTest extends TestCase
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

    private function election(): Election
    {
        return Election::where('current', true)->first() ?? Election::create(['name' => 'Test 2027', 'type' => 'ASSEMBLY', 'election_year' => 2027, 'current' => true]);
    }

    private function agent(string $name): void
    {
        $u = \App\Models\AppUser::create(['username' => $name, 'full_name' => $name, 'enabled' => true, 'created_at' => now(), 'password_hash' => \App\Models\AppUser::hashPassword('Agent@2027')]);
        $u->roles()->attach(\App\Models\Role::where('code', 'OPERATOR')->value('id'));
    }

    public function test_booth_pack_has_the_chosen_booths_and_todays_marks(): void
    {
        $e = $this->election();
        $v = Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->orderBy('serial_no')->first();
        DB::table('poll_marks')->insert(['election_id' => $e->id, 'voter_id' => $v->id, 'assembly_no' => 45, 'booth_no' => 1, 'marked_by' => 'x', 'marked_at' => now()]);
        $this->login();
        $this->get('/field')->assertOk()->assertSee('data-pack', false);
        $j = $this->getJson('/field/pack?assemblyNo=45&booths[]=1&booths[]=2&booths[]=999')->assertOk()->json();
        $this->assertSame([1, 2], array_column($j['booths'], 'no'));   // booth 999 does not exist: ignored
        $this->assertSame($e->id, $j['election']['id']);
        $first = collect($j['booths'][0]['voters'])->firstWhere('id', $v->id);
        $this->assertSame([1, $v->epic_no], [$first['v'], $first['epic']]);
        $this->assertSame(Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->count(), count($j['booths'][0]['voters']));
        $this->assertNotNull(DB::table('field_syncs')->where('username', 'admin')->where('booth_no', 2)->value('downloaded_at'));
        config(['voters.survey.field_max_booths' => 1]);
        $this->assertCount(1, $this->getJson('/field/pack?assemblyNo=45&booths[]=1&booths[]=2')->json('booths'));
    }

    public function test_offline_marks_sync_once_and_unmark_respects_order(): void
    {
        $e = $this->election();
        [$a, $b] = Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->orderBy('serial_no')->limit(2)->get()->all();
        $this->login();
        $t0 = now()->subMinutes(30);
        $items = [
            ['uid' => 'u1', 'type' => 'mark', 'voter_id' => $a->id, 'election_id' => $e->id, 'at' => $t0->toIso8601String()],
            ['uid' => 'u2', 'type' => 'mark', 'voter_id' => $b->id, 'election_id' => $e->id, 'at' => $t0->copy()->addMinute()->toIso8601String()],
            ['uid' => 'u3', 'type' => 'unmark', 'voter_id' => $b->id, 'election_id' => $e->id, 'at' => $t0->copy()->addMinutes(2)->toIso8601String()],
            ['uid' => 'u4', 'type' => 'mark', 'voter_id' => $a->id, 'election_id' => 999999, 'at' => $t0->toIso8601String()],
        ];
        $res = $this->postJson('/field/sync', ['items' => $items, 'booths' => [['a' => 45, 'b' => 1]]])->assertOk()->json();
        $this->assertSame(['u1', 'u2', 'u3'], $res['done']);
        $this->assertSame('election', $res['rejected'][0]['why']);
        $this->assertSame([$a->id], $res['marks']['45|1']);
        // the phone keeps the phone's time of the vote
        $this->assertSame($t0->format('H:i'), \Carbon\Carbon::parse(DB::table('poll_marks')->where('voter_id', $a->id)->value('marked_at'))->format('H:i'));
        // the same batch sent again (signal lost before the reply): nothing is counted twice
        $this->postJson('/field/sync', ['items' => $items, 'booths' => [['a' => 45, 'b' => 1]]])->assertOk();
        $this->assertSame(1, DB::table('poll_marks')->where('election_id', $e->id)->count());
        // an old "unmark" arriving late does not remove a newer mark
        $this->postJson('/field/sync', ['items' => [['uid' => 'u5', 'type' => 'mark', 'voter_id' => $b->id, 'election_id' => $e->id, 'at' => now()->toIso8601String()],
            ['uid' => 'u6', 'type' => 'unmark', 'voter_id' => $b->id, 'election_id' => $e->id, 'at' => now()->subHour()->toIso8601String()]]])->assertOk();
        $this->assertTrue(DB::table('poll_marks')->where('voter_id', $b->id)->exists());
        $this->assertNotNull(DB::table('field_syncs')->where('username', 'admin')->where('booth_no', 1)->value('synced_at'));
        $this->get('/pollday/live?assemblyNo=45')->assertOk()->assertSee(t('field.lastSync'));
    }

    public function test_two_agents_on_one_booth_see_each_others_marks(): void
    {
        $e = $this->election();
        [$a, $b] = Voter::where('assembly_no', 45)->where('booth_no', 2)->where('deleted', false)->orderBy('serial_no')->limit(2)->get()->all();
        $this->agent('agent1'); $this->agent('agent2');
        $this->login('agent1', 'Agent@2027');
        $this->postJson('/field/sync', ['items' => [['uid' => 'a1', 'type' => 'mark', 'voter_id' => $a->id, 'election_id' => $e->id, 'at' => now()->toIso8601String()]]])->assertOk();
        $this->post('/logout');
        $this->login('agent2', 'Agent@2027');
        $res = $this->postJson('/field/sync', ['items' => [
            ['uid' => 'b1', 'type' => 'mark', 'voter_id' => $a->id, 'election_id' => $e->id, 'at' => now()->toIso8601String()],   // same voter, also marked offline
            ['uid' => 'b2', 'type' => 'mark', 'voter_id' => $b->id, 'election_id' => $e->id, 'at' => now()->toIso8601String()]], 'booths' => [['a' => 45, 'b' => 2]]])->json();
        $this->assertSame(['b1', 'b2'], $res['done']);
        sort($res['marks']['45|2']);
        $this->assertSame(collect([$a->id, $b->id])->sort()->values()->all(), $res['marks']['45|2']);
        $this->assertSame(2, DB::table('poll_marks')->where('election_id', $e->id)->count());
        $this->assertSame('agent1', DB::table('poll_marks')->where('voter_id', $a->id)->value('marked_by'));
    }

    public function test_permissions_and_login(): void
    {
        $e = $this->election();
        $v = Voter::where('assembly_no', 45)->first();
        $this->postJson('/field/sync', ['items' => []])->assertStatus(401);
        $this->getJson('/field/pack?assemblyNo=45&booths[]=1')->assertStatus(401);
        // a viewer can search offline but cannot mark votes
        $this->login('viewer', 'viewer@123');
        $this->getJson('/field/pack?assemblyNo=45&booths[]=1')->assertOk()->assertJsonPath('canPoll', false);
        $res = $this->postJson('/field/sync', ['items' => [['uid' => 'x', 'type' => 'mark', 'voter_id' => $v->id, 'election_id' => $e->id, 'at' => now()->toIso8601String()]]])->json();
        $this->assertSame('permission', $res['rejected'][0]['why']);
        $this->assertSame(0, DB::table('poll_marks')->count());
    }

    public function test_field_workers_stay_logged_in_but_administrators_time_out(): void
    {
        $this->assertGreaterThanOrEqual(3 * 1440, (int) config('session.lifetime'));
        config(['voters.security.office_session_minutes' => 60]);
        $this->agent('agent9');
        $this->login('agent9', 'Agent@2027');
        $this->travel(5)->hours();
        $this->get('/field')->assertOk();                 // field worker: still logged in after 5 hours
        $this->post('/logout');
        $this->login();
        $this->get('/field')->assertOk();
        $this->travel(2)->hours();
        $this->get('/field')->assertRedirect('/login');   // administrator: logged out after 60 idle minutes
    }
}
