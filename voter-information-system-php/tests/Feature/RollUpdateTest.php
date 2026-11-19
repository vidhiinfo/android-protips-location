<?php

namespace Tests\Feature;

use App\Models\{AppUser, RollUpdate, Role, Voter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RollUpdateTest extends TestCase
{
    use RefreshDatabase;

    private int $worker;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        app(\App\Services\Auth\Defaults::class)->ensure(true);
        $this->worker = DB::table('workers')->insertGetId(['name' => 'Panna One', 'assembly_no' => 77, 'booth_no' => 1, 'role' => 'PANNA_PRAMUKH', 'active' => true, 'created_at' => now()]);
        $v = fn ($epic, $booth, $serial, $name, array $extra = []) => Voter::create(array_merge(['assembly_no' => 77, 'booth_no' => $booth, 'serial_no' => $serial,
            'epic_no' => $epic, 'name' => $name, 'relative_name' => 'Father ' . $serial, 'gender' => 'MALE', 'age' => 30, 'house_no' => (string) $serial], $extra));
        $v('RU0000001', 1, 1, 'Ram Kumar', ['mobile' => '9811111111', 'caste' => 'Saini', 'political_status' => 'Supporter']);
        $v('RU0000002', 1, 2, 'Sita Devi', ['gender' => 'FEMALE', 'mobile' => '9822222222']);
        $v('RU0000003', 1, 3, 'Shyam Lal', ['worker_id' => $this->worker, 'beneficiary1' => 'Ration Card']);
        $v('RU0000004', 2, 1, 'Gone Person', ['caste' => 'Jat']);
        $v('RU0000005', 2, 2, 'Back Again', ['deleted' => true, 'mobile' => '9855555555']);
        $v('RU0000006', 2, 3, 'Mohan Lal', ['relative_name' => 'Hari Ram', 'age' => 40, 'remarks' => 'met twice']);
        $v('RU0000007', 3, 1, 'Other Booth');
    }

    private function login(string $u = 'admin', string $p = 'admin@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    /** New roll for booths 1 and 2 of AC 77 (booth 3 not in the file). */
    private function roll(array $extra = []): UploadedFile
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray(array_merge([
            ['AC No', 'Part No', 'Serial No', 'EPIC No', 'Name', "Relative's Name", 'Gender', 'Age', 'House No', 'Mobile'],
            ['77', '1', '1', 'RU0000001', 'Ram Kumar', 'Father 1', 'M', '31', '1', '9000000000'],   // same (age + 1); mobile in the file is ignored
            ['77', '1', '2', 'RU0000002', 'Seeta Devi', 'Father 2', 'F', '31', '2', ''],              // name corrected
            ['77', '2', '5', 'RU0000003', 'Shyam Lal', 'Father 3', 'M', '31', '3', ''],               // shifted to booth 2
            ['77', '2', '2', 'RU0000005', 'Back Again', 'Father 5', 'M', '31', '5', ''],              // back on the roll
            ['77', '2', '3', 'RU9999996', 'Mohan Lal', 'Hari Ram', 'M', '41', '6', ''],               // EPIC changed
            ['77', '2', '4', 'RU0000008', 'New Voter', 'Father 8', 'F', '18', '8', ''],               // new
            ['77', '2', '6', 'RU0000008', 'Duplicate', 'X', 'F', '20', '9', ''],                      // same EPIC twice
        ], $extra));
        $path = tempnam(sys_get_temp_dir(), 'roll') . '.xlsx';
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book, 'Xlsx')->save($path);
        return new UploadedFile($path, 'final-roll-77.xlsx', null, null, true);
    }

    private function v(string $epic): Voter { return Voter::where('epic_no', $epic)->firstOrFail(); }

    public function test_compare_apply_and_undo_keep_survey_data(): void
    {
        $this->login();
        $this->get('/roll-update')->assertOk()->assertSee(t('roll.title'));
        $this->post('/roll-update', ['assemblyNo' => 77, 'file' => $this->roll()])->assertRedirect();
        $u = RollUpdate::firstOrFail();
        $a = $u->countsData()['actions'];
        $this->assertSame(['NEW' => 1, 'DELETED' => 1, 'SHIFTED' => 1, 'CHANGED' => 1, 'NAMEMATCH' => 1, 'RESTORED' => 1, 'SAME' => 1], $a);
        $this->assertSame([1, 2], $u->boothList());
        $this->assertSame(1, $u->countsData()['surveyDeleted']);       // Gone Person has a caste
        $this->assertSame(5, $u->countsData()['surveyKept']);
        $this->assertSame(['now' => 2, 'roll' => 4, 'NEW' => 1, 'DELETED' => 1, 'in' => 1, 'out' => 0, 'CHANGED' => 0, 'RESTORED' => 1, 'NAMEMATCH' => 1], $u->countsData()['booths'][2]);
        $this->assertSame(1, $u->countsData()['booths'][1]['out']);
        $this->assertStringContainsString('RU0000008', implode(' ', $u->errorList()));
        // preview only: nothing changed yet
        $this->assertSame('Sita Devi', $this->v('RU0000002')->name);
        $this->assertFalse(Voter::where('epic_no', 'RU0000008')->exists());
        $page = $this->get('/roll-update/' . $u->id)->assertOk()->assertSee('Seeta Devi')->assertSee(t('roll.action.NAMEMATCH'))->getContent();
        $this->assertStringContainsString(t('roll.previewNote'), $page);
        $this->get('/roll-update/' . $u->id . '?action=DELETED&survey=1')->assertOk()->assertSee('Gone Person')->assertDontSee('Seeta Devi');
        $this->get('/roll-update/' . $u->id . '/report')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // a second file for the same assembly waits until this one is applied or discarded
        $this->post('/roll-update', ['assemblyNo' => 77, 'file' => $this->roll()])->assertOk()->assertSee(t('roll.error.pending'));

        $this->post('/roll-update/' . $u->id . '/apply')->assertRedirect();
        $this->assertSame('APPLIED', $u->fresh()->status);
        $ram = $this->v('RU0000001');
        $this->assertSame([31, '9811111111', 'Saini', 'Supporter'], [$ram->age, $ram->mobile, $ram->caste, $ram->political_status]);
        $this->assertSame(['Seeta Devi', '9822222222'], [$this->v('RU0000002')->name, $this->v('RU0000002')->mobile]);
        $shyam = $this->v('RU0000003');
        $this->assertSame([2, 5, null, 'Ration Card'], [$shyam->booth_no, $shyam->serial_no, $shyam->worker_id, $shyam->beneficiary1]);
        $this->assertTrue($this->v('RU0000004')->deleted);
        $this->assertSame('Jat', $this->v('RU0000004')->caste);           // marked deleted, never removed
        $this->assertSame([false, '9855555555'], [$this->v('RU0000005')->deleted, $this->v('RU0000005')->mobile]);
        $mohan = $this->v('RU9999996');
        $this->assertSame([41, 'met twice'], [$mohan->age, $mohan->remarks]);
        $this->assertFalse(Voter::where('epic_no', 'RU0000006')->exists());
        $this->assertSame([2, 4, 'FEMALE', 18], [$this->v('RU0000008')->booth_no, $this->v('RU0000008')->serial_no, $this->v('RU0000008')->gender, $this->v('RU0000008')->age]);
        $this->assertFalse($this->v('RU0000007')->deleted);               // booth 3 was not in the file
        $this->assertSame(7, DB::table('roll_update_rows')->where('roll_update_id', $u->id)->count());   // the SAME row is kept: its age changed
        $this->get('/roll-update/' . $u->id)->assertOk()->assertSee(t('roll.undo'));
        $this->assertSame(1, DB::table('audit_log')->where('action', 'APPLY')->where('entity', 'ROLL')->count());

        $this->post('/roll-update/' . $u->id . '/undo')->assertRedirect();
        $this->assertSame('UNDONE', $u->fresh()->status);
        $this->assertSame(['Sita Devi', '9822222222'], [$this->v('RU0000002')->name, $this->v('RU0000002')->mobile]);
        $this->assertSame([1, 3, $this->worker], [$this->v('RU0000003')->booth_no, $this->v('RU0000003')->serial_no, $this->v('RU0000003')->worker_id]);
        $this->assertFalse($this->v('RU0000004')->deleted);
        $this->assertTrue($this->v('RU0000005')->deleted);
        $this->assertSame(40, $this->v('RU0000006')->age);
        $this->assertSame(30, $this->v('RU0000001')->age);
        $this->assertFalse(Voter::where('epic_no', 'RU0000008')->exists());
    }

    public function test_discard_changes_nothing_and_only_latest_can_be_undone(): void
    {
        $this->login();
        $this->post('/roll-update', ['assemblyNo' => 77, 'file' => $this->roll()]);
        $u = RollUpdate::firstOrFail();
        $this->post('/roll-update/' . $u->id . '/discard')->assertRedirect('/roll-update');
        $this->assertSame('DISCARDED', $u->fresh()->status);
        $this->assertSame(0, DB::table('roll_update_rows')->count());
        $this->assertSame('Sita Devi', $this->v('RU0000002')->name);

        $this->post('/roll-update', ['assemblyNo' => 77, 'file' => $this->roll()]);
        $first = RollUpdate::where('status', 'PREVIEW')->firstOrFail();
        $this->post('/roll-update/' . $first->id . '/apply');
        $this->post('/roll-update', ['assemblyNo' => 77, 'file' => $this->roll()]);
        $second = RollUpdate::where('status', 'PREVIEW')->firstOrFail();
        $this->assertSame(['NEW' => 0, 'DELETED' => 0, 'SHIFTED' => 0, 'CHANGED' => 0, 'NAMEMATCH' => 0, 'RESTORED' => 0, 'SAME' => 6], $second->countsData()['actions']);
        $this->post('/roll-update/' . $second->id . '/apply');
        $this->post('/roll-update/' . $first->id . '/undo')->assertRedirect();
        $this->assertSame('APPLIED', $first->fresh()->status);          // an older update cannot be undone
    }

    public function test_needs_permission_and_whole_assembly_login(): void
    {
        $this->login();   // creates the roles
        $this->post('/logout');
        $op = AppUser::create(['username' => 'op', 'full_name' => 'op', 'enabled' => true, 'created_at' => now(), 'password_hash' => AppUser::hashPassword('secret@123')]);
        $op->roles()->attach(Role::where('code', 'OPERATOR')->value('id'));
        $this->login('op', 'secret@123');
        $this->get('/roll-update')->assertForbidden();
        $this->post('/logout');
        $adm = AppUser::create(['username' => 'boothadmin', 'full_name' => 'b', 'enabled' => true, 'created_at' => now(), 'scope_booths' => '1',
            'password_hash' => AppUser::hashPassword('secret@123')]);
        $adm->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->login('boothadmin', 'secret@123');
        $this->get('/roll-update')->assertOk()->assertSee(t('roll.error.area'));
        $this->post('/roll-update', ['assemblyNo' => 77, 'file' => $this->roll()])->assertOk()->assertSee(t('roll.error.area'));
        $this->assertSame(0, RollUpdate::count());
    }

    public function test_serial_access_areas_and_grievances_follow_the_new_roll(): void
    {
        $this->login();   // creates the roles
        $mk = function ($name, $booth, $from, $to) {
            $u = AppUser::create(['username' => $name, 'full_name' => $name, 'enabled' => true, 'assembly_no' => 77, 'created_at' => now(), 'scope_booths' => (string) $booth,
                'scope_serial_from' => $from, 'scope_serial_to' => $to, 'password_hash' => AppUser::hashPassword('secret@123')]);
            $u->roles()->attach(Role::where('code', 'OPERATOR')->value('id'));
            return $u;
        };
        $page = $mk('pp1', 1, 1, 2);     // Ram Kumar and Sita Devi
        $gone = $mk('pp2', 2, 1, 1);     // only Gone Person, who is not in the new roll
        $other = $mk('pp3', 3, 1, 5);    // booth 3 is not in the file
        $grv = DB::table('grievances')->insertGetId(['voter_id' => $this->v('RU0000003')->id, 'assembly_no' => 77, 'booth_no' => 1, 'name' => 'Shyam Lal',
            'category' => 'OTHER', 'subject' => 'Road', 'status' => 'OPEN', 'priority' => 'NORMAL', 'created_at' => now()]);
        // the new roll renumbers booth 1: Ram Kumar 11, Sita Devi 12, a new voter 13
        $this->post('/roll-update', ['assemblyNo' => 77, 'file' => $this->roll([
            ['77', '1', '13', 'RU0000013', 'New In Booth One', 'X', 'M', '19', '13', ''],
        ])]);
        $u = RollUpdate::firstOrFail();
        DB::table('roll_update_rows')->where('roll_update_id', $u->id)->where('epic_no', 'RU0000001')->update(['data' => json_encode(['assembly_no' => 77, 'booth_no' => 1, 'serial_no' => 11, 'epic_no' => 'RU0000001', 'name' => 'Ram Kumar']), 'changes' => '{"serial_no":[1,11]}']);
        DB::table('roll_update_rows')->where('roll_update_id', $u->id)->where('epic_no', 'RU0000002')->update(['data' => json_encode(['assembly_no' => 77, 'booth_no' => 1, 'serial_no' => 12, 'epic_no' => 'RU0000002', 'name' => 'Seeta Devi'])]);
        $this->post('/roll-update/' . $u->id . '/apply')->assertRedirect();
        $this->assertSame([11, 12], [(int) $page->fresh()->scope_serial_from, (int) $page->fresh()->scope_serial_to]);
        $this->assertSame([1, 1], [(int) $gone->fresh()->scope_serial_from, (int) $gone->fresh()->scope_serial_to]);   // nothing left: unchanged, flagged
        $this->assertSame([1, 5], [(int) $other->fresh()->scope_serial_from, (int) $other->fresh()->scope_serial_to]);
        $this->assertSame(2, (int) DB::table('grievances')->where('id', $grv)->value('booth_no'));
        $this->get('/roll-update/' . $u->id)->assertOk()->assertSee(t('roll.areas'))
            ->assertSee(t('roll.areaMoved', 'pp1', 1, 1, 2, 11, 12))->assertSee(t('roll.areaEmpty', 'pp2', 2, 1, 1));
        // the panna pramukh login now sees its voters at their new serial numbers
        $this->post('/logout');
        $this->post('/login', ['username' => 'pp1', 'password' => 'secret@123', 'assemblyNo' => 77]);
        $this->get('/voters')->assertOk()->assertSee('RU0000001')->assertSee('RU0000002')->assertDontSee('RU0000013');
        $this->post('/logout');
        $this->login();

        $this->post('/roll-update/' . $u->id . '/undo')->assertRedirect();
        $this->assertSame([1, 2], [(int) $page->fresh()->scope_serial_from, (int) $page->fresh()->scope_serial_to]);
        $this->assertSame(1, (int) DB::table('grievances')->where('id', $grv)->value('booth_no'));
    }
}
