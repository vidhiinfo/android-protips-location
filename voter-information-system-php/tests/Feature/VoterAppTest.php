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

    public function test_house_numbers_match_ignoring_spaces_hyphens_and_dots(): void
    {
        $this->login();
        $mk = fn ($epic, $house) => Voter::create(['epic_no' => $epic, 'name' => 'House ' . $epic, 'gender' => 'MALE', 'age' => 40, 'assembly_no' => 46,
            'booth_no' => 99, 'house_no' => $house, 'address' => 'Gali 1', 'village_ward' => 'Test Ward']);
        $a = $mk('HSE0000001', 'RZ-154'); $mk('HSE0000002', 'R Z-154'); $mk('HSE0000003', 'rz.154'); $mk('HSE0000004', 'RZ-154/FF');
        $html = $this->get("/voters/{$a->id}/household")->assertOk()->getContent();
        foreach (['HSE0000001', 'HSE0000002', 'HSE0000003'] as $e) $this->assertStringContainsString($e, $html);
        $this->assertStringNotContainsString('HSE0000004', $html);   // another floor = another household
        $before = collect($this->get('/dashboard/data?assemblyNo=46')->json('tiles'))->firstWhere('label', t('dash.tile.households'))['value'];
        $mk('HSE0000005', 'R Z - 1 5 4');
        $after = collect($this->get('/dashboard/data?assemblyNo=46')->json('tiles'))->firstWhere('label', t('dash.tile.households'))['value'];
        $this->assertSame($before, $after);   // same house, no new household
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
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($x->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray();
        $this->assertSame(Voter::where('assembly_no', 45)->count() + 4, count($book));   // title, 2 blank, header
        $v = Voter::where('assembly_no', 45)->whereNotNull('mobile')->orderBy('booth_no')->orderBy('serial_no')->first();
        $this->assertContains($v->name_local, array_merge(...$book));
        $this->assertContains($v->mobile, array_merge(...$book));
        $p = $this->get('/voters/export/pdf?assemblyNo=45&lang=hi')->assertOk();
        $this->assertStringStartsWith('%PDF', $p->getContent());
        $this->get('/voters/print?assemblyNo=45&autoprint=1')->assertOk()->assertSee('data-autoprint', false);
        // big lists: PDF / print refused with a message, Excel still works
        config(['voters.export.pdf_max_rows' => 100]);
        $this->get('/voters/export/pdf')->assertRedirect()->assertSessionHas('error', 'export.pdf.tooMany');
        $this->get('/voters/print')->assertRedirect();
        $this->get('/reports/phone-bank/export/pdf')->assertRedirect()->assertSessionHas('error', 'export.pdf.tooMany');
        $this->get('/reports/phone-bank/export/excel')->assertOk();
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

    public function test_import_ward_list_with_customer_columns(): void
    {
        $this->login();
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $book->getActiveSheet();
        $ws->fromArray([
            ['Ward', 'Booth', 'V.SNo.', 'VoterID', 'Name', 'Gender', "Relative's Name", 'Age', 'Houseno', 'Address', 'Mobile(Original)', 'Mobile1',
             'Membershipno', 'DOB', 'Marriage Anniv.', 'Deleted', 'Shift1', 'Shift2', 'Place of Origin', 'Current Place', 'Linked With',
             'Social Status', 'Political Status', 'Organisational Status', 'Beneficiary1', 'Beneficiary2', 'Beneficiary3', 'Caste', 'Poling Station'],
            ['Ward 12', '7', '101', 'xyz9876543', 'Mohan Lal', 'M', 'Hari Ram', '', '22/B', 'Gandhi Path', '+91 98290 12345', '9414012345',
             'BJM-5521', '05/03/1975', '1998-11-20', 'No', 'Day', '', 'Sikar', 'Jaipur', 'Ward President', 'Influencer', 'Supporter',
             'Booth Committee', 'PM Awas', 'Ujjwala', '', 'Saini', 'Govt. School, Gandhi Nagar'],
            ['Ward 12', '7', '102', 'XYZ9876544', 'Kamla Devi', 'F', 'Mohan Lal', '45', '22/B', 'Gandhi Path', '', '', '', '', '', 'Yes', '', '', '', '', '', '', '', '', '', '', '', 'Saini', ''],
            ['Ward 12', '7', '103', 'XYZ9876545', 'Bad Date', 'M', 'X', '30', '', '', '', '', '', '31/02/1990', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
        ]);
        $ws->setCellValue('N4', '31/02/1990');
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx')->save($path);
        $html = $this->post('/voters/import', ['file' => new UploadedFile($path, 'ward12.xlsx', null, null, true), 'defaultAssembly' => 46])->assertOk()->getContent();
        $v = Voter::where('epic_no', 'XYZ9876543')->firstOrFail();
        $this->assertSame([46, 7, 101, 'Ward 12', 'MALE', 'Hari Ram', '22/B', '9829012345', '9414012345'],
            [$v->assembly_no, $v->booth_no, $v->serial_no, $v->village_ward, $v->gender, $v->relative_name, $v->house_no, $v->mobile, $v->alt_mobile]);
        $this->assertSame(['BJM-5521', '1975-03-05', '1998-11-20', false, 'Day', 'Sikar', 'Jaipur', 'Ward President', 'Influencer', 'Supporter',
            'Booth Committee', 'PM Awas', 'Ujjwala', 'Saini', 'Govt. School, Gandhi Nagar'],
            [$v->membership_no, $v->dob->format('Y-m-d'), $v->anniversary->format('Y-m-d'), $v->deleted, $v->shift1, $v->place_of_origin, $v->current_place,
             $v->linked_with, $v->social_status, $v->political_status, $v->organisational_status, $v->beneficiary1, $v->beneficiary2, $v->caste, $v->booth_name]);
        $this->assertGreaterThan(40, $v->age);   // calculated from DOB
        $this->assertTrue(Voter::where('epic_no', 'XYZ9876544')->value('deleted'));
        $this->assertFalse(Voter::where('epic_no', 'XYZ9876545')->exists());   // 31 Feb rejected with a row error
        $this->assertStringContainsString(t('validation.date'), $html);

        // new fields: filters, details page, export, edit form
        $this->get('/voters?caste=Saini&deleted=exclude')->assertOk()->assertSee('XYZ9876543')->assertDontSee('XYZ9876544');
        $this->get('/voters?beneficiary=awas')->assertOk()->assertSee('XYZ9876543');
        $this->get('/voters?deleted=only')->assertOk()->assertSee('XYZ9876544')->assertDontSee('XYZ9876543');
        $this->get('/voters/' . $v->id)->assertOk()->assertSee('05-03-1975')->assertSee('Booth Committee');
        $this->get('/voters/' . $v->id . '/edit')->assertOk()->assertSee('value="1975-03-05"', false);
        $this->post('/voters/' . $v->id, array_merge($v->only(['epic_no', 'name', 'gender', 'assembly_no', 'booth_no']), ['dob' => '2999-01-01', 'deleted' => '1']))
            ->assertOk()->assertSee(t('validation.date'));
        $this->post('/voters/' . $v->id, array_merge($v->only(['epic_no', 'name', 'gender', 'assembly_no', 'booth_no']), ['dob' => '1975-03-06', 'deleted' => '1', 'caste' => 'Zz-Test-Caste']))
            ->assertRedirect();
        $this->assertSame(['1975-03-06', true, 'Zz-Test-Caste'], [$v->fresh()->dob->format('Y-m-d'), $v->fresh()->deleted, $v->fresh()->caste]);
        $x = $this->get('/voters/export/excel?caste=Zz-Test-Caste')->assertOk();
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($x->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray();
        $this->assertContains(t('col.caste'), $rows[3]);
        $this->assertContains('06-03-1975', $rows[4]);
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

    public function test_age_group_counts_link_to_the_voter_list(): void
    {
        $this->login();
        $html = $this->get('/reports/age-gender?assemblyNo=45')->assertOk()->getContent();
        $this->assertStringContainsString('/voters?assemblyNo=45&amp;ageMin=26&amp;ageMax=35&amp;gender=FEMALE', $html);
        $this->assertStringContainsString('/voters?assemblyNo=45&amp;ageMin=81&amp;gender=MALE', $html);     // 81+ has no upper limit
        $women = Voter::where('assembly_no', 45)->whereBetween('age', [26, 35])->where('gender', 'FEMALE')->count();
        $list = $this->get('/voters?assemblyNo=45&ageMin=26&ageMax=35&gender=FEMALE')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/' . preg_quote(t('list.showing', $women ? 1 : 0, min(25, $women), $women), '/') . '/', $list);
        $this->assertStringStartsWith('PK', file_get_contents($this->get('/voters/export/excel?assemblyNo=45&ageMin=26&ageMax=35&gender=FEMALE')->baseResponse->getFile()->getPathname()));
        $this->get('/reports/age-gender?assemblyNo=45&print=1')->assertOk()->assertDontSee('ageMin=26', false);   // no links when printing
    }

    public function test_ward_filter_on_every_screen(): void
    {
        $this->login();
        $inWard2 = Voter::where('assembly_no', 45)->where('ward_no', 2)->distinct()->orderBy('booth_no')->pluck('booth_no')->all();
        $this->assertNotEmpty($inWard2);
        $this->assertEmpty(array_diff($inWard2, [5, 6, 7, 8]));   // demo: booths 5-8 form ward 2
        $n = Voter::where('assembly_no', 45)->where('ward_no', 2)->count();
        // voter list: ward drop-down, ward -> booth map for the Booth drop-down, filtered rows
        $html = $this->get('/voters?assemblyNo=45&wardNo=2')->assertOk()->getContent();
        $this->assertStringContainsString('name="wardNo"', $html);
        $this->assertStringContainsString('"2":' . json_encode($inWard2), html_entity_decode($html));
        $this->assertStringContainsString(t('list.showing', 1, min(25, $n), $n), $html);
        // the Booth list itself is already limited on the server (works even with an old cached app.js)
        preg_match('/<select name="boothNo"[^>]*>(.*?)<\/select>/s', $html, $m);
        preg_match_all('/<option value="(\d+)"/', $m[1], $opts);
        $this->assertSame(array_map('strval', $inWard2), $opts[1]);
        // a booth picked before switching to another ward is dropped instead of giving an empty list
        $this->assertStringContainsString(t('list.showing', 1, min(25, $n), $n), $this->get('/voters?assemblyNo=45&wardNo=2&boothNo=1')->getContent());
        $this->assertStringContainsString('?v=', $html);   // versioned app.js / app.css
        // export and print follow the filter
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->get('/voters/export/excel?assemblyNo=45&wardNo=2')->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray();
        $this->assertSame($n + 4, count($book));
        $this->assertContains(t('col.wardNo'), $book[3]);
        // reports: only the ward's booths
        $rep = $this->get('/reports/booth-summary?assemblyNo=45&wardNo=2')->assertOk()->getContent();
        $this->assertStringContainsString(t('col.wardNo') . ' 2', $rep);
        $this->assertStringContainsString('name="wardNo"', $rep);
        $rs = app(\App\Services\Reports\ReportService::class); $rs->ward = 2;
        $this->assertSame($inWard2, array_map(fn ($r) => $r[1], $rs->build('booth-summary', 45, null, null)->rows));
        $rs->ward = null;
        // dashboard: booth charts of the ward only
        $this->assertSame(array_map('strval', $inWard2), $this->get('/dashboard/data?assemblyNo=45&wardNo=2')->assertOk()->json('boothVoters.labels'));
        $this->get('/dashboard?assemblyNo=45&wardNo=2')->assertOk()->assertSee('name="wardNo"', false);
        // results: seat result and entry limited to the ward's booths
        $e = Election::first();
        $c = app(\App\Services\Results\ResultService::class)->contest($e, 45, 2);
        $this->assertEmpty(array_diff(array_column($c['booths'], 'booth_no'), $inWard2));
        $this->assertSame($n, $c['electors']);
        $this->get("/results/contest?electionId={$e->id}&assemblyNo=45&wardNo=2")->assertOk()->assertSee(t('col.wardNo') . ' 2');
        $entry = $this->get("/results/entry?electionId={$e->id}&assemblyNo=45&wardNo=2")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/wardNo=2&(amp;)?boothNo=' . $inWard2[0] . '"/', $entry);
        $this->assertStringNotContainsString('boothNo=9"', $entry);
        // masters: booths of the ward
        $this->get('/masters/booths?assemblyNo=45&wardNo=2')->assertOk()->assertSee('Govt. School Room 1, ')->assertSee('name="wardNo"', false);
        // import: a numeric "Ward" column becomes the ward number
        $book2 = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book2->getActiveSheet()->fromArray([['Ward', 'Booth', 'V.SNo.', 'VoterID', 'Name', 'Gender'], ['102', '3', '9', 'WRD0000001', 'Ward Test', 'F']]);
        $path = tempnam(sys_get_temp_dir(), 'w') . '.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book2, 'Xlsx')->save($path);
        $this->post('/voters/import', ['file' => new UploadedFile($path, 'w.xlsx', null, null, true), 'defaultAssembly' => 45])->assertOk();
        $this->assertSame(102, Voter::where('epic_no', 'WRD0000001')->value('ward_no'));
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
