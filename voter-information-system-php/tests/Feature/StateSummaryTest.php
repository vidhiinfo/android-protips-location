<?php

namespace Tests\Feature;

use App\Models\Voter;
use App\Services\State\StateFeedService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StateSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    public function test_division_feed_needs_the_token_and_has_totals_only(): void
    {
        $this->get('/state-feed')->assertNotFound();   // no STATE_FEED_TOKEN: no feed
        config(['voters.state.feed_token' => 'secret-123']);
        $this->get('/state-feed')->assertForbidden();
        $this->withToken('wrong')->get('/state-feed')->assertForbidden();
        Voter::where('assembly_no', 45)->limit(3)->update(['political_status' => 'Supporter', 'mobile' => '9811111111']);
        $j = $this->withToken('secret-123')->get('/state-feed')->assertOk()->json();
        $ac = collect($j['assemblies'])->firstWhere('assembly_no', 45);
        $alive = Voter::where('assembly_no', 45)->where(fn ($w) => $w->where('deleted', false)->orWhereNull('deleted'));
        $this->assertSame([(clone $alive)->count(), (clone $alive)->where('gender', 'MALE')->count(), (clone $alive)->distinct()->count('booth_no')],
            [$ac['voters'], $ac['male'], $ac['booths']]);
        $this->assertGreaterThanOrEqual(3, $ac['S']);
        $raw = json_encode($j);
        $v = Voter::where('assembly_no', 45)->first();
        foreach ([$v->epic_no, $v->name, '9811111111'] as $private) $this->assertStringNotContainsString($private, $raw);
    }

    public function test_state_page_adds_up_divisions_and_marks_offline_ones(): void
    {
        config(['voters.state.feed_token' => 't1']);
        $feed = app(StateFeedService::class)->build();
        $other = ['version' => 1, 'name' => 'Div Two', 'generatedAt' => now()->toIso8601String(), 'election' => null,
            'assemblies' => [['assembly_no' => 175, 'name' => 'Lucknow Cantonment', 'voters' => 1000, 'male' => 600, 'female' => 400, 'booths' => 2, 'S' => 100, 'surveyed' => 300, 'voted' => 50]]];
        config(['voters.state.sources' => 'Div One|https://one.example.in/vis|t1;Div Two|https://two.example.in/vis|t2']);
        $down = false;
        Http::fake(function ($req) use (&$down, $feed, $other) {
            if (str_contains($req->url(), 'one.example.in')) return Http::response($feed);
            return $down ? Http::response('error', 500) : Http::response($other);
        });
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $html = $this->get('/state')->assertOk()->assertSee('Div One')->assertSee('Div Two')->assertSee(t('state.online', 2, 2))->getContent();
        Http::assertSent(fn ($r) => $r->url() === 'https://one.example.in/vis/state-feed' && $r->hasHeader('Authorization', 'Bearer t1'));
        $total = array_sum(array_column($feed['assemblies'], 'voters')) + 1000;
        $this->assertStringContainsString(number_format($total), $html);
        $this->get('/state?division=Div%20Two')->assertOk()->assertSee('175 - Lucknow Cantonment')->assertSee('1,000');
        $this->get('/state/export')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Div Two goes down: its last figures stay, marked offline
        $down = true;
        $this->artisan('vis:state-pull')->expectsOutputToContain('OFFLINE Div Two')->assertSuccessful();
        $this->get('/state')->assertOk()->assertSee(t('state.online', 1, 2))->assertSee(t('state.offline'))->assertSee(t('state.lastKnown'))->assertSee(number_format($total));
        $this->assertSame('HTTP 500', DB::table('state_snapshots')->where('source', 'Div Two')->value('error'));
    }

    public function test_menu_permission_and_not_set_up(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->get('/state')->assertNotFound();   // STATE_SOURCES empty
        $this->get('/dashboard')->assertDontSee('/state"', false);
        config(['voters.state.sources' => 'Div One|https://one.example.in/vis|t1']);
        Http::fake(['*' => Http::response(['assemblies' => []])]);
        $this->get('/dashboard')->assertSee(url('/state'), false);
        $this->post('/logout');
        $op = \App\Models\AppUser::create(['username' => 'op', 'full_name' => 'op', 'enabled' => true, 'created_at' => now(), 'password_hash' => \App\Models\AppUser::hashPassword('secret@123')]);
        $op->roles()->attach(\App\Models\Role::where('code', 'OPERATOR')->value('id'));
        $this->post('/login', ['username' => 'op', 'password' => 'secret@123']);
        $this->get('/state')->assertForbidden();
    }
}
