<?php

namespace Tests\Feature;

use App\Http\Controllers\FieldSurveyController;
use App\Models\Voter;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FieldToolsTest extends TestCase
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

    private function first(): Voter { return Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->orderBy('serial_no')->first(); }

    public function test_house_location_is_saved_with_the_survey_and_shown_on_the_map(): void
    {
        $v = $this->first();
        $this->login();
        $this->get('/survey/house/' . $v->id)->assertOk()->assertSee('data-geo', false);
        $this->post('/survey/house/' . $v->id, ['go' => 'stay', 'lat' => '28.6400001', 'lng' => '77.0900002', 'acc' => '12.4'])->assertRedirect();
        $loc = DB::table('house_locations')->first();
        $this->assertSame([45, 1, FieldSurveyController::houseRef($v), 12], [(int) $loc->assembly_no, (int) $loc->booth_no, $loc->house_ref, (int) $loc->accuracy_m]);
        // a point outside India is ignored
        $this->post('/survey/house/' . $v->id, ['go' => 'stay', 'lat' => '51.5', 'lng' => '-0.1'])->assertRedirect();
        $this->assertEqualsWithDelta(28.64, (float) DB::table('house_locations')->value('lat'), 0.001);
        $res = $this->get('/survey/map?assemblyNo=45&boothNo=1')->assertOk()->assertSee('id="houseMap"', false)->assertSee('77.0900002', false);
        $this->assertStringContainsString('tile.openstreetmap.org', $res->headers->get('Content-Security-Policy'));
        $this->get('/survey/map?assemblyNo=45&boothNo=2')->assertOk()->assertDontSee('id="houseMap"', false);
    }

    public function test_offline_download_and_sync_with_conflicts(): void
    {
        $this->login();
        $this->get('/survey/offline?assemblyNo=45&boothNo=1')->assertOk()->assertSee('data-off-download', false);
        $j = $this->getJson('/survey/offline/data?assemblyNo=45&boothNo=1')->assertOk()->json();
        $this->assertSame(1, $j['booth']);
        $members = collect($j['houses'])->pluck('members')->flatten(1);
        $this->assertSame(Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->count(), $members->count());
        [$a, $b] = [$members[0], $members[1]];
        // someone changes b's caste on the server after the download
        Voter::whereKey($b['id'])->update(['caste' => 'ServerCaste']);
        $res = $this->postJson('/survey/offline/sync', [
            'changes' => [
                ['id' => $a['id'], 'v' => ['political_status' => 'Supporter', 'mobile' => '+91 98100 11122'], 'base' => ['political_status' => $a['v']['political_status'], 'mobile' => $a['v']['mobile']]],
                ['id' => $b['id'], 'v' => ['caste' => 'PhoneCaste', 'remarks' => 'met at door'], 'base' => ['caste' => $b['v']['caste'], 'remarks' => $b['v']['remarks']]],
                ['id' => $a['id'] + 999999, 'v' => ['remarks' => 'x'], 'base' => []],
            ],
            'locations' => [['id' => $a['id'], 'lat' => 28.61, 'lng' => 77.2, 'acc' => 8]],
        ])->assertOk()->json();
        $this->assertSame([2, 1, 1, []], [$res['saved'], $res['located'], $res['conflicts'], $res['errors']]);
        $this->assertContains($a['id'], $res['done']);
        $this->assertSame(['Supporter', '9810011122'], [Voter::find($a['id'])->political_status, Voter::find($a['id'])->mobile]);
        $this->assertSame(['ServerCaste', 'met at door'], [Voter::find($b['id'])->caste, Voter::find($b['id'])->remarks]);
        // a bad mobile is reported, not saved, and stays on the phone (not in done)
        $res = $this->postJson('/survey/offline/sync', ['changes' => [['id' => $a['id'], 'v' => ['mobile' => '12'], 'base' => ['mobile' => '9810011122']]]])->json();
        $this->assertCount(1, $res['errors']);
        $this->assertNotContains($a['id'], $res['done']);
    }

    public function test_offline_endpoints_need_login_and_respect_the_access_area(): void
    {
        $this->getJson('/survey/offline/data?assemblyNo=45&boothNo=1')->assertStatus(401);
        $this->postJson('/survey/offline/sync', ['changes' => []])->assertStatus(401);
        $this->login('viewer', 'viewer@123');
        $this->getJson('/survey/offline/data?assemblyNo=45&boothNo=1')->assertForbidden();
    }

    private function workerLogin(): \App\Models\Worker
    {
        $u = \App\Models\AppUser::create(['username' => 'field1', 'full_name' => 'Field One', 'enabled' => true, 'created_at' => now(),
            'password_hash' => \App\Models\AppUser::hashPassword('Field@2027'), 'mobile' => '9811122233']);
        $u->roles()->attach(\App\Models\Role::where('code', 'OPERATOR')->value('id'));
        return \App\Models\Worker::create(['name' => 'Field One', 'assembly_no' => 45, 'booth_no' => 1, 'role' => 'VOLUNTEER', 'username' => 'field1', 'mobile' => '9811122233', 'active' => true]);
    }

    public function test_tasks_targets_progress_and_reminders(): void
    {
        $w = $this->workerLogin();
        $this->login();
        $this->travel(1)->minutes();   // voters were seeded before the task was given
        $this->post('/workers/tasks', ['assembly_no' => 45, 'title' => 'Survey booth 1', 'kind' => 'SURVEY', 'worker_id' => $w->id, 'target' => 4, 'due_date' => now()->subDay()->toDateString()])
            ->assertRedirect()->assertSessionHas('success');
        $this->post('/workers/tasks', ['assembly_no' => 45, 'title' => 'Call 50 voters', 'kind' => 'CALL', 'worker_id' => $w->id, 'target' => 50, 'due_date' => now()->toDateString()])->assertRedirect();
        $t = DB::table('worker_tasks')->where('kind', 'SURVEY')->first();
        $this->assertSame(['field1', 1], [$t->username, (int) $t->booth_no]);
        // two voters of booth 1 get surveyed after the task was given
        $this->travel(1)->minutes();
        Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->orderBy('id')->limit(2)->get()->each(fn ($v) => $v->update(['political_status' => 'Supporter']));
        $p = app(\App\Services\Workers\TaskService::class)->progress(DB::table('worker_tasks')->get());
        $this->assertSame(['done' => 2, 'pct' => 50], $p[$t->id]);
        $this->get('/workers/tasks?assemblyNo=45')->assertOk()->assertSee('Survey booth 1')->assertSee('2 / 4');
        // the worker sees both, with the due badge, and reports progress on the call task
        $this->post('/logout');
        $this->login('field1', 'Field@2027');
        $this->get('/my/tasks')->assertOk()->assertSee('Call 50 voters')->assertSee('Overdue');
        $this->assertSame(2, app(\App\Services\Workers\TaskService::class)->dueFor('field1'));
        $call = DB::table('worker_tasks')->where('kind', 'CALL')->first();
        $this->post('/my/tasks/' . $call->id, ['done_count' => 20])->assertRedirect();
        $this->assertSame(20, (int) DB::table('worker_tasks')->find($call->id)->done_count);
        $this->post('/my/tasks/' . $call->id, ['status' => 'DONE'])->assertRedirect();
        $this->assertSame('DONE', DB::table('worker_tasks')->find($call->id)->status);
        // reminder: one message for the open overdue task, not twice the same day
        config(['voters.tasks.reminder_channel' => 'sms', 'voters.messaging.sms_provider' => 'log']);
        $svc = app(\App\Services\Workers\TaskService::class);
        $this->assertSame(1, $svc->remind(true));
        $this->assertSame(0, $svc->remind(true));
        $this->assertSame(now()->toDateString(), (string) DB::table('worker_tasks')->find($t->id)->reminded_on);
    }

    public function test_attendance_check_in_and_out(): void
    {
        $this->workerLogin();
        $this->login('field1', 'Field@2027');
        $this->get('/my/attendance')->assertOk()->assertSee('Check in');
        $this->post('/my/attendance', ['lat' => '28.6', 'lng' => '77.1', 'note' => 'Booth 1'])->assertRedirect()->assertSessionHas('success', 'Checked in.');
        $this->post('/my/attendance', ['lat' => '99', 'lng' => '77.1'])->assertSessionHas('success', 'Checked out.');
        $this->post('/my/attendance')->assertSessionHas('error', 'You have already checked out today.');
        $a = DB::table('attendance')->first();
        $this->assertSame([45, 'Booth 1', null], [(int) $a->worker_id > 0 ? 45 : 0, $a->note, $a->out_lat]);
        $this->post('/logout');
        $this->login();
        $this->get('/workers/attendance?assemblyNo=45')->assertOk()->assertSee('Field One')->assertSee('1 / 7');
    }

    public function test_duplicates_are_found_merged_and_undone(): void
    {
        $base = Voter::where('assembly_no', 45)->where('deleted', false)->orderBy('id')->first();
        $keep = Voter::create(array_merge($base->only(['assembly_no', 'assembly_name', 'booth_no', 'gender', 'house_no', 'address', 'village_ward']),
            ['epic_no' => 'DUP0000001', 'name' => 'Ramkishan Yadav', 'relative_name' => 'Shyamlal Yadav', 'age' => 40, 'mobile' => null, 'caste' => 'Yadav', 'political_status' => null, 'deleted' => false]));
        $drop = Voter::create(array_merge($keep->only(['assembly_no', 'assembly_name', 'booth_no', 'gender', 'house_no', 'address', 'village_ward']),
            ['epic_no' => 'DUP0000002', 'name' => 'RAMKISHAN  yadav', 'relative_name' => 'Shyam-lal Yadav', 'age' => 41, 'mobile' => '9876543210', 'caste' => 'Other', 'political_status' => 'Supporter', 'deleted' => false]));
        // same names but 30 years apart: not offered by default
        Voter::create(array_merge($keep->only(['assembly_no', 'assembly_name', 'booth_no', 'gender']), ['epic_no' => 'DUP0000003', 'name' => 'Ramkishan Yadav', 'relative_name' => 'Shyamlal Yadav', 'age' => 71, 'deleted' => false]));
        $gid = DB::table('grievances')->insertGetId(['voter_id' => $drop->id, 'assembly_no' => 45, 'name' => 'x', 'category' => 'WATER', 'subject' => 'Water', 'created_at' => now()]);
        $this->login();
        $page = $this->get('/voters/duplicates?assemblyNo=45')->assertOk()->assertSee('DUP0000001')->assertSee('DUP0000002')->assertDontSee('DUP0000003');
        $this->get('/voters/duplicates?assemblyNo=45&anyAge=1')->assertSee('DUP0000003');
        $this->post('/voters/duplicates/merge', ['keep' => $keep->id, 'drop' => [$drop->id]])->assertRedirect()->assertSessionHas('success');
        $k = $keep->fresh();
        $this->assertSame(['9876543210', 'Yadav', 'Supporter'], [$k->mobile, $k->caste, $k->political_status]);   // caste kept, empty fields filled
        $this->assertTrue((bool) $drop->fresh()->deleted);
        $this->assertSame($keep->id, (int) DB::table('grievances')->find($gid)->voter_id);
        // the mobile is corrected after the merge; undo keeps it but clears the status copied from the duplicate
        $k->update(['mobile' => '9999988888']);
        $mid = DB::table('voter_merges')->value('id');
        $this->post('/voters/duplicates/' . $mid . '/undo')->assertRedirect()->assertSessionHas('success');
        $k = $keep->fresh();
        $this->assertSame(['9999988888', null], [$k->mobile, $k->political_status]);
        $this->assertFalse((bool) $drop->fresh()->deleted);
        $this->assertSame($drop->id, (int) DB::table('grievances')->find($gid)->voter_id);
        $this->post('/voters/duplicates/' . $mid . '/undo')->assertSessionHas('error');
        // viewers cannot merge
        $this->post('/logout');
        $this->login('viewer', 'viewer@123');
        $this->get('/voters/duplicates')->assertForbidden();
    }
}
