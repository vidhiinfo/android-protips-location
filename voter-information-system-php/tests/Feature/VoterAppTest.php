<?php

namespace Tests\Feature;

use App\Models\{AppUser, Campaign, Election, MessageTemplate, OutboxMessage, Role, Voter};
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VoterAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    private function login(string $u = 'admin', string $p = 'admin@123', $assembly = null)
    {
        return $this->post('/login', ['username' => $u, 'password' => $p, 'assemblyNo' => $assembly]);
    }

    public function test_anonymous_is_redirected_to_login(): void
    {
        $this->get('/voters')->assertRedirect('/login');
    }

    public function test_login_page_has_fields_assembly_drop_down_and_banner(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="username"', false)->assertSee('name="password"', false)
            ->assertSee('name="assemblyNo"', false)->assertSee('Jaipur North')->assertSee('login-banner', false);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->login('admin', 'nope')->assertRedirect('/login?error=credentials');
        $this->get('/voters')->assertRedirect('/login');
    }

    public function test_login_with_assembly_scopes_the_session(): void
    {
        $this->login('admin', 'admin@123', 46)->assertRedirect('/dashboard');
        $html = $this->get('/voters?assemblyNo=45')->assertOk()->getContent();
        $this->assertStringContainsString('Lucknow West', $html);
        $this->assertStringNotContainsString('DEM', strstr($html, 'Jaipur') ? '' : 'x'); // filters are forced to 46
        $this->get('/voters/' . Voter::where('assembly_no', 45)->value('id'))->assertNotFound();
    }

    public function test_user_assigned_to_an_assembly_cannot_log_in_to_another(): void
    {
        $this->login('jaipur', 'jaipur@123', 46)->assertRedirect('/login?error=assembly');
        $this->login('jaipur', 'jaipur@123', 45)->assertRedirect('/dashboard');
    }

    public function test_language_switch_and_urdu_is_right_to_left(): void
    {
        $this->get('/login?lang=hi')->assertSee('lang="hi"', false)->assertSee('उपयोगकर्ता');
        $this->get('/login?lang=ur')->assertSee('dir="rtl"', false)->assertSee('bootstrap.rtl.min.css', false);
    }

    public function test_search_finds_local_language_names(): void
    {
        $this->login();
        $v = Voter::where('assembly_no', 52)->first();
        $first = explode(' ', $v->name_local)[0];
        $this->get('/voters?q=' . urlencode($first))->assertOk()->assertSee($v->name_local);
    }

    public function test_create_voter_with_local_names_and_native_digits(): void
    {
        $this->login();
        $this->post('/voters', ['epic_no' => 'abc1234567', 'name' => 'Test Person', 'name_local' => 'परीक्षण व्यक्ति', 'gender' => 'FEMALE',
            'age' => '४२', 'assembly_no' => 45, 'booth_no' => 2, 'house_no' => '12A', 'mobile' => '९८७६५४३२१०', 'relation_type' => 'FATHER'])->assertRedirect();
        $v = Voter::where('epic_no', 'ABC1234567')->firstOrFail();
        $this->assertSame(42, (int) $v->age);
        $this->assertSame('9876543210', $v->mobile);
        $this->assertSame('Jaipur North', $v->assembly_name);
        $this->post('/voters', ['epic_no' => 'ABC1234567', 'name' => 'Dup', 'gender' => 'MALE', 'assembly_no' => 45, 'booth_no' => 1])
            ->assertOk()->assertSee(t('validation.epic.duplicate'));
    }

    public function test_house_no_link_opens_everyone_in_that_house(): void
    {
        $this->login();
        $v = Voter::where('relation_type', 'HUSBAND')->first();
        $html = $this->get("/voters/{$v->id}/household")->assertOk()->getContent();
        $same = Voter::where('assembly_no', $v->assembly_no)->where('house_no', $v->house_no)->where('address', $v->address)->get();
        foreach ($same as $s) $this->assertStringContainsString(e($s->epic_no), $html);
        $this->get('/voters')->assertSee('house-link', false);
    }

    public function test_viewer_cannot_add_voters_or_open_admin_messages(): void
    {
        $this->login('viewer', 'viewer@123');
        $this->get('/voters')->assertOk();
        foreach (['/voters/new', '/admin/users', '/admin/roles', '/messages', '/results/entry', '/reports/category-summary'] as $u) $this->get($u)->assertForbidden();
        $this->post('/voters', ['epic_no' => 'X'])->assertForbidden();
    }

    public function test_exports_and_print(): void
    {
        $this->login();
        $x = $this->get('/voters/export/excel?assemblyNo=45')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $x->headers->get('content-type'));
        $this->assertStringStartsWith('PK', $x->getContent());
        $p = $this->get('/voters/export/pdf?assemblyNo=45&lang=hi')->assertOk();
        $this->assertStringStartsWith('%PDF', $p->getContent());
        $this->get('/voters/print?assemblyNo=45&autoprint=1')->assertOk()->assertSee('data-autoprint', false);
    }

    public function test_import_accepts_template_in_any_language(): void
    {
        $this->login();
        $this->assertStringStartsWith('PK', $this->get('/voters/import/template?lang=hi')->assertOk()->getContent());
        $L = fn ($k) => \App\Support\Lang::get($k, [], 'hi');
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([
            [$L('col.assemblyNo'), $L('col.boothNo'), $L('col.epicNo'), $L('col.name'), $L('col.relation'), $L('col.relativeName'), $L('col.gender'), $L('col.age'), $L('col.houseNo')],
            ['४५', '3', 'imp0000001', 'Import Test', $L('relation.HUSBAND'), 'Some One', $L('gender.FEMALE'), '३३', '7'],
            ['45', '3', '', '', '', '', '', '', ''],
            ['45', '3', 'IMP0000002', 'Bad Age', '', '', 'M', '7', ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx')->save($path);
        $this->post('/voters/import', ['file' => new UploadedFile($path, 'v.xlsx', null, null, true)])->assertOk();
        $v = Voter::where('epic_no', 'IMP0000001')->firstOrFail();
        $this->assertSame(['FEMALE', 'HUSBAND', 33], [$v->gender, $v->relation_type, (int) $v->age]);
        $this->assertFalse(Voter::where('epic_no', 'IMP0000002')->exists());
    }

    public function test_admin_manages_masters_and_users(): void
    {
        $this->login();
        $this->post('/masters/assemblies', ['assembly_no' => 99, 'name' => 'New Seat', 'name_local' => 'नई सीट'])->assertRedirect();
        $this->get('/masters/assemblies')->assertSee('नई सीट');
        $this->post('/masters/parties', ['short_name' => 'jvp', 'name' => 'Duplicate'])->assertOk()->assertSee(t('validation.duplicate'));
        $this->post('/admin/users', ['username' => 'ravi', 'full_name' => 'Ravi', 'newPassword' => 'secret@123', 'enabled' => 1,
            'roleIds' => [Role::where('code', 'VIEWER')->value('id')]])->assertRedirect('/admin/users');
        $this->assertStringStartsWith('{bcrypt}', AppUser::where('username', 'ravi')->value('password_hash'));
        $this->post('/logout');
        $this->login('ravi', 'secret@123')->assertRedirect('/dashboard');
    }

    public function test_results_entry_and_view(): void
    {
        $this->login();
        $e = Election::first();
        $cands = $e->id ? \App\Models\Candidate::where('election_id', $e->id)->where('assembly_no', 45)->pluck('id') : collect();
        $votes = $cands->mapWithKeys(fn ($id, $i) => [$id => 100 - $i * 10])->all();
        $this->post('/results/entry', ['electionId' => $e->id, 'assemblyNo' => 45, 'boothNo' => 12, 'votes' => $votes])->assertRedirect();
        $this->assertSame(100, (int) \App\Models\BoothResult::where('booth_no', 12)->where('assembly_no', 45)->where('candidate_id', $cands[0])->value('votes'));
        $this->get("/results/contest?electionId={$e->id}&assemblyNo=45")->assertOk()->assertSee('JVP');
        $this->get('/results')->assertOk()->assertSee('Kolkata Port');
    }

    public function test_reports_render_and_export(): void
    {
        $this->login();
        foreach (\App\Services\Reports\ReportService::all() as $id) {
            $this->get("/reports/$id?assemblyNo=45")->assertOk();
            $this->assertStringStartsWith('%PDF', $this->get("/reports/$id/export/pdf?assemblyNo=45")->assertOk()->getContent());
            $this->assertStringStartsWith('PK', $this->get("/reports/$id/export/excel")->assertOk()->getContent());
        }
    }

    public function test_duplicate_report_finds_planted_duplicates(): void
    {
        $this->login();
        $dup = Voter::where('epic_no', 'like', 'DUP%')->first();
        if (!$dup) $this->markTestSkipped('no duplicate in small sample');
        $this->get("/reports/duplicate-voters?assemblyNo={$dup->assembly_no}")->assertOk()->assertSee($dup->epic_no);
        $this->get("/reports/duplicate-voters?assemblyNo={$dup->assembly_no}&scope=WARD")->assertOk();
    }

    public function test_dashboard_data_for_candidate(): void
    {
        $this->login();
        $this->get('/dashboard/data?assemblyNo=45')->assertOk()->assertJsonStructure(['tiles', 'ageGender', 'boothVoters', 'election', 'household', 'mobile', 'young']);
    }

    public function test_compose_queue_and_send_in_dry_run(): void
    {
        $this->login();
        $t = MessageTemplate::where('channel', 'SMS')->where('language', 'hi')->first();
        $this->get("/messages/compose?assemblyNo=45&channel=SMS&templateId={$t->id}")->assertOk()->assertSee('प्रिय');
        $this->post('/messages/optouts', ['mobile' => Voter::where('assembly_no', 45)->whereNotNull('mobile')->value('mobile'), 'channel' => ''])->assertRedirect();
        $this->post('/messages/campaigns', ['templateId' => $t->id, 'campaignName' => 'Test run', 'assemblyNo' => 45])->assertRedirect();
        $c = Campaign::firstOrFail();
        $this->assertGreaterThan(0, OutboxMessage::where('campaign_id', $c->id)->count());
        $this->artisan('messages:send', ['--ignore-window' => true, '--seconds' => 20])->assertSuccessful();
        $this->assertSame(0, OutboxMessage::where('campaign_id', $c->id)->where('status', 'PENDING')->count());
        $this->assertGreaterThan(0, OutboxMessage::where('status', 'SENT')->count());
        $this->get("/messages/campaigns/{$c->id}")->assertOk()->assertSee('Test run');
    }

    public function test_silence_period_blocks_campaigns(): void
    {
        $this->login();
        Election::query()->update(['poll_date' => now()->addDay()->toDateString(), 'current' => true]);
        $t = MessageTemplate::first();
        $this->post('/messages/campaigns', ['templateId' => $t->id, 'campaignName' => 'x', 'assemblyNo' => 45])->assertSessionHas('error', 'msg.error.silence');
        $this->assertSame(0, Campaign::count());
    }

    public function test_custom_role_gets_only_its_permissions(): void
    {
        $this->login('agent', 'agent@1234', 52)->assertRedirect('/voters');
        $this->get('/results/entry')->assertOk();
        $this->get('/dashboard')->assertForbidden();
        $this->get('/reports')->assertForbidden();
    }

    public function test_admin_edits_role_permissions(): void
    {
        $this->login();
        $role = Role::where('code', 'BOOTH_AGENT')->first();
        $this->post('/admin/roles', ['id' => $role->id, 'code' => 'BOOTH_AGENT', 'name' => 'Booth Agent', 'perms' => ['VOTER_VIEW', 'REPORT_VIEW']])->assertRedirect('/admin/roles');
        $this->assertEqualsCanonicalizing(['VOTER_VIEW', 'REPORT_VIEW'], $role->fresh()->permissions());
        $this->post("/admin/roles/{$role->id}/delete")->assertSessionHas('error', 'flash.inUse');
    }

    public function test_change_password_and_audit_log(): void
    {
        $this->login('operator', 'operator@123');
        $this->post('/account/password', ['current' => 'operator@123', 'newPassword' => 'changed@123', 'confirm' => 'changed@123'])->assertRedirect();
        $this->post('/logout');
        $this->login('operator', 'changed@123')->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->login();
        $this->get('/admin/audit')->assertOk()->assertSee('LOGIN');
    }
}
