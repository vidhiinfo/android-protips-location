<?php

namespace Tests\Feature;

use App\Models\CustomField;
use App\Models\Designation;
use App\Models\District;
use App\Models\Member;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MemberTest extends OfficeTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/members')->assertRedirect('/login');
    }

    public function test_login_with_username(): void
    {
        $u = $this->admin();
        $this->post('/login', ['username' => $u->username, 'password' => 'Secret123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
    }

    public function test_disabled_user_cannot_login(): void
    {
        $u = $this->makeUser('national_staff', ['active' => false]);
        $this->post('/login', ['username' => $u->username, 'password' => 'Secret123'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_admin_creates_member_with_photo_stored_privately(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin())
            ->post('/members', $this->memberPayload(['photo' => UploadedFile::fake()->image('p.jpg', 1200, 1600)]))
            ->assertRedirect();

        $m = Member::firstWhere('name', 'Test Person');
        $this->assertSame('active', $m->status);
        $this->assertNotNull($m->photo_path);
        Storage::disk('local')->assertExists($m->photo_path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($m->photo_path));
        $this->assertLessThanOrEqual(600, max($w, $h), 'photo is downscaled');

        $this->get(route('members.photo', $m))->assertOk();
        auth()->logout();
        $this->get(route('members.photo', $m))->assertRedirect('/login');
    }

    public function test_consent_is_required_when_configured(): void
    {
        $this->actingAs($this->admin())->post('/members', $this->memberPayload(['consent' => null]))->assertSessionHasErrors('consent');
        Setting::put('require_consent', false);
        $this->post('/members', $this->memberPayload(['consent' => null]))->assertSessionHasNoErrors();
    }

    public function test_required_fields_follow_settings(): void
    {
        $admin = $this->admin();
        Setting::put('member_required_fields', ['name', 'level', 'dob']);
        $this->actingAs($admin)->post('/members', $this->memberPayload())->assertSessionHasErrors('dob');
        $this->post('/members', $this->memberPayload(['dob' => '1980-01-01']))->assertSessionHasNoErrors();
    }

    public function test_duplicate_mobile_is_flagged(): void
    {
        $this->actingAs($this->admin());
        $this->post('/members', $this->memberPayload(['mobile' => '9999900000']))->assertSessionHasNoErrors();
        $this->post('/members', $this->memberPayload(['mobile' => '9999900000']))->assertSessionHasErrors('mobile');
        $this->post('/members', $this->memberPayload(['mobile' => '9999900000', 'allow_duplicate' => '1']))->assertSessionHasNoErrors();
    }

    public function test_state_admin_is_confined_to_own_state_and_entries_need_approval(): void
    {
        $sa = $this->stateAdmin();
        $rjMember = Member::create(['name' => 'Rajasthan Person', 'level' => 'state', 'state_id' => $this->rj->id, 'type' => 'bearer']);

        $this->actingAs($sa)->get('/members')->assertOk()->assertDontSee('Rajasthan Person');
        $this->get(route('members.show', $rjMember))->assertForbidden();
        $this->get(route('members.edit', $rjMember))->assertForbidden();

        // Tries to put a member into Rajasthan: forced back to own state, and marked proposed.
        $this->post('/members', $this->memberPayload(['level' => 'state', 'state_id' => $this->rj->id, 'district_id' => null]))->assertRedirect();
        $m = Member::firstWhere('name', 'Test Person');
        $this->assertSame($this->up->id, $m->state_id);
        $this->assertSame('proposed', $m->status);

        // Cannot create national-level members or approve.
        $this->post('/members', $this->memberPayload(['name' => 'X', 'level' => 'national']))->assertSessionHasErrors('level');
        $this->post(route('members.approve', $m))->assertForbidden();

        // National office approves.
        $this->actingAs($this->admin())->post(route('members.approve', $m), ['decision' => 'approve']);
        $this->assertSame('active', $m->fresh()->status);
    }

    public function test_approval_workflow_can_be_switched_off(): void
    {
        Setting::put('state_entries_need_approval', false);
        $this->actingAs($this->stateAdmin())->post('/members', $this->memberPayload());
        $this->assertSame('active', Member::firstWhere('name', 'Test Person')->status);
    }

    public function test_district_admin_is_confined_to_own_district(): void
    {
        $da = $this->makeUser('district_admin', ['state_id' => $this->up->id, 'district_id' => $this->lucknow->id]);
        $agra = District::where('name', 'Agra')->first();
        $other = Member::create(['name' => 'Agra Person', 'level' => 'district', 'state_id' => $this->up->id, 'district_id' => $agra->id, 'type' => 'bearer']);

        $this->actingAs($da)->get('/members')->assertDontSee('Agra Person');
        $this->get(route('members.show', $other))->assertForbidden();
        $this->post('/members', $this->memberPayload(['district_id' => $agra->id]));
        $this->assertSame($this->lucknow->id, Member::firstWhere('name', 'Test Person')->district_id);
        $this->post('/members', $this->memberPayload(['name' => 'Y', 'level' => 'state']))->assertSessionHasErrors('level');
    }

    public function test_custom_fields_are_validated_and_saved(): void
    {
        CustomField::create(['entity' => 'member', 'key' => 'voter_id', 'label' => 'Voter ID', 'type' => 'text', 'required' => true, 'active' => true]);
        CustomField::create(['entity' => 'member', 'key' => 'blood_group', 'label' => 'Blood group', 'type' => 'select', 'options' => "A+\nB+\nO+", 'active' => true]);

        $this->actingAs($this->admin())->post('/members', $this->memberPayload())->assertSessionHasErrors('extra.voter_id');
        $this->post('/members', $this->memberPayload(['extra' => ['voter_id' => 'ABC1234567', 'blood_group' => 'Z']]))->assertSessionHasErrors('extra.blood_group');
        $this->post('/members', $this->memberPayload(['extra' => ['voter_id' => 'ABC1234567', 'blood_group' => 'O+']]))->assertSessionHasNoErrors();
        $this->assertSame('ABC1234567', Member::firstWhere('name', 'Test Person')->extra['voter_id']);
    }

    public function test_csv_import_creates_members_and_reports_errors(): void
    {
        $csv = "name,mobile,level,state,district,designation,consent\n"
            ."Import One,9811111111,district,Uttar Pradesh,Lucknow,President,yes\n"
            ."Import Two,9822222222,district,Uttar Pradesh,Bareilly,Secretary,yes\n"   // new district → auto-created for national
            ."Bad Row,9833333333,district,Atlantis,X,President,yes\n";
        $file = UploadedFile::fake()->createWithContent('m.csv', $csv);

        $this->actingAs($this->admin())->post('/members/import', ['file' => $file, 'mode' => 'skip'])
            ->assertSessionHas('import_result', fn ($r) => $r['created'] === 2 && count($r['errors']) === 1);
        $this->assertDatabaseHas('districts', ['name' => 'Bareilly', 'state_id' => $this->up->id]);
    }

    public function test_export_respects_jurisdiction(): void
    {
        Member::create(['name' => 'UP Person', 'level' => 'state', 'state_id' => $this->up->id, 'type' => 'bearer']);
        Member::create(['name' => 'RJ Person', 'level' => 'state', 'state_id' => $this->rj->id, 'type' => 'bearer']);
        $csv = $this->actingAs($this->stateAdmin())->get('/members/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('UP Person', $csv);
        $this->assertStringNotContainsString('RJ Person', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export']);
    }

    public function test_directory_lists_by_designation_seniority(): void
    {
        $this->actingAs($this->admin());
        foreach (['Secretary' => 'Zed Secretary', 'President' => 'Amy President'] as $post => $name) {
            Member::create(['name' => $name, 'level' => 'state', 'state_id' => $this->up->id, 'type' => 'bearer', 'status' => 'active',
                'designation_id' => Designation::where(['level' => 'state', 'title' => $post])->value('id')]);
        }
        $this->get('/directory?state_id='.$this->up->id.'&level=state')->assertOk()->assertSeeInOrder(['Amy President', 'Zed Secretary']);
    }

    public function test_password_edited_outside_the_portal_gives_a_clear_message_not_a_crash(): void
    {
        $u = $this->admin();
        DB::table('users')->where('id', $u->id)->update(['password' => md5('Secret123')]);
        $this->post('/login', ['username' => $u->username, 'password' => 'Secret123'])
            ->assertSessionHasErrors(['username' => "This account's password must be reset by the administrator (it was changed outside the portal)."]);
        $this->postJson('/api/v1/login', ['username' => $u->username, 'password' => 'Secret123', 'device_name' => 't'])->assertUnprocessable();
    }
}
