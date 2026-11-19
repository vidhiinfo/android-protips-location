<?php

namespace Tests\Feature;

use App\Models\{AppUser, Grievance, Role, Voter};
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);   // creates roles
        $this->post('/logout');
    }

    private function user(string $name, array $area): AppUser
    {
        $u = AppUser::create(['username' => $name, 'full_name' => $name, 'enabled' => true, 'assembly_no' => 45, 'created_at' => now(),
            'password_hash' => AppUser::hashPassword('secret@123')] + $area);
        $u->roles()->attach(Role::where('code', 'OPERATOR')->value('id'));
        return $u;
    }

    private function as(string $name) { $this->post('/logout'); return $this->post('/login', ['username' => $name, 'password' => 'secret@123', 'assemblyNo' => 45]); }

    private function matching(string $html): int { preg_match('/' . preg_quote(t('list.matching'), '/') . '<\/div><div class="num text-primary">(\d+)/', $html, $m); return (int) ($m[1] ?? -1); }

    public function test_booth_area_limits_lists_pages_reports_and_exports(): void
    {
        $this->user('bp3', ['scope_booths' => '3']);
        $in = Voter::where('assembly_no', 45)->where('booth_no', 3)->count();
        $out = Voter::where('assembly_no', 45)->where('booth_no', 4)->first();
        $this->as('bp3')->assertRedirect();
        $this->assertSame($in, $this->matching($this->get('/voters')->assertOk()->getContent()));
        $this->get('/voters?boothNo=4')->assertOk()->assertDontSee($out->epic_no);
        $this->get('/voters/' . $out->id)->assertNotFound();
        $this->get('/dashboard')->assertOk()->assertSee(t('col.boothNo') . ' 3', false);
        $tiles = collect($this->get('/dashboard/data?assemblyNo=45')->json('tiles'))->keyBy('label');
        $this->assertSame($in, (int) str_replace(',', '', $tiles[t('dash.tile.totalVoters')]['value'] ?? $tiles->first()['value']));
        $rep = $this->get('/reports/booth-summary?assemblyNo=45')->assertOk()->getContent();
        $this->assertStringNotContainsString('>4</td>', $rep);
        $this->get('/reports/booth-scorecard?assemblyNo=45')->assertOk();
        $this->get('/pollday?assemblyNo=45&boothNo=4')->assertOk()->assertDontSee('name="q"', false);   // booth 4 is not offered
        $file = $this->get('/voters/export/excel')->baseResponse->getFile()->getPathname();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $this->assertSame($in, $book->getActiveSheet()->getHighestRow() - 4);   // title, 2 blank rows, header
    }

    public function test_serial_range_area_and_saving_outside_is_refused(): void
    {
        $serials = Voter::where('assembly_no', 45)->where('booth_no', 3)->orderBy('serial_no')->limit(5)->pluck('serial_no');
        $this->user('pp3', ['scope_booths' => '3', 'scope_serial_from' => $serials->first(), 'scope_serial_to' => $serials->last()]);
        $mine = Voter::where('assembly_no', 45)->where('booth_no', 3)->whereBetween('serial_no', [$serials->first(), $serials->last()])->get();
        $this->as('pp3');
        $this->assertSame($mine->count(), $this->matching($this->get('/voters')->getContent()));
        $v = $mine->first();
        $form = $v->only(['epic_no', 'name', 'gender', 'age', 'assembly_no', 'booth_no', 'serial_no', 'relation_type']);
        $this->post('/voters/' . $v->id, ['booth_no' => 4] + $form)->assertOk()->assertSee(t('area.outside'));   // move out of the area: refused
        $this->assertSame(3, $v->fresh()->booth_no);
        $this->post('/voters/' . $v->id, ['age' => 44] + $form)->assertRedirect();
        $this->assertSame(44, $v->fresh()->age);
        $this->get('/survey?assemblyNo=45&boothNo=3')->assertOk();
    }

    public function test_ward_area_and_grievances(): void
    {
        $ward = Voter::where('assembly_no', 45)->whereNotNull('ward_no')->value('ward_no');
        $this->user('wi', ['scope_wards' => (string) $ward]);
        $in = Voter::where('assembly_no', 45)->where('ward_no', $ward)->count();
        $g1 = Grievance::create(['assembly_no' => 45, 'ward_no' => $ward, 'booth_no' => 1, 'name' => 'Inside', 'category' => 'WATER', 'subject' => 'In ward', 'priority' => 'NORMAL', 'status' => 'OPEN']);
        $g2 = Grievance::create(['assembly_no' => 45, 'ward_no' => $ward + 100, 'booth_no' => 1, 'name' => 'Outside', 'category' => 'ROAD', 'subject' => 'Other ward', 'priority' => 'NORMAL', 'status' => 'OPEN']);
        $this->as('wi');
        $this->assertSame($in, $this->matching($this->get('/voters')->getContent()));
        $this->get('/grievances')->assertOk()->assertSee('In ward')->assertDontSee('Other ward');
        $this->get('/grievances/' . $g2->id)->assertNotFound();
    }

    public function test_admin_form_saves_and_validates_the_area(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $u = $this->user('x1', []);
        $role = Role::where('code', 'OPERATOR')->value('id');
        $this->post('/admin/users', ['id' => $u->id, 'full_name' => 'X', 'assembly_no' => 45, 'enabled' => 1, 'roleIds' => [$role],
            'scope_booths' => '3,4', 'scope_serial_from' => 1, 'scope_serial_to' => 30])->assertSessionHasNoErrors()->assertOk()->assertSee(t('area.oneBooth'));
        $this->post('/admin/users', ['id' => $u->id, 'full_name' => 'X', 'assembly_no' => 45, 'enabled' => 1, 'roleIds' => [$role],
            'scope_booths' => '3', 'scope_serial_from' => 31, 'scope_serial_to' => 60])->assertRedirect();
        $this->post('/admin/users', ['id' => $u->id, 'full_name' => 'X', 'assembly_no' => 45, 'enabled' => 1, 'roleIds' => [$role],
            'scope_booths' => '999'])->assertOk()->assertSee(t('area.notInAssembly'));                       // not a booth of assembly 45
        $this->get('/admin/users/' . $u->id . '/edit')->assertOk()->assertSee('data-area-picker', false)->assertSee('data-area-dd="scope_booths"', false);
        $u->refresh();
        $this->assertSame(['3', 31, 60], [$u->scope_booths, (int) $u->scope_serial_from, (int) $u->scope_serial_to]);
        $this->get('/admin/users')->assertSee(t('col.boothNo') . ' 3 · ' . t('area.serials', 31, 60), false);
    }

    public function test_master_rename_by_area_user_updates_all_voters(): void
    {
        Voter::where('assembly_no', 45)->update(['social_status' => 'Teacher']);
        $u = $this->user('m3', ['scope_booths' => '3']);
        $u->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->as('m3');
        $t = \App\Models\SocialStatus::where('name', 'Teacher')->first();
        $this->post('/masters/socialstatuses', ['id' => $t->id, 'name' => 'Teacher (school)', 'group_name' => 'PROFESSION', 'sort_order' => 70, 'active' => 1]);
        $this->assertSame(0, Voter::withoutGlobalScope('area')->where('social_status', 'Teacher')->count());
    }

    private function saveTmp(string $data): string { $p = tempnam(sys_get_temp_dir(), 'x') . '.xlsx'; file_put_contents($p, $data); return $p; }
}
