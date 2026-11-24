<?php

namespace Tests\Feature;

use App\Models\Member;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ApiTest extends OfficeTestCase
{
    private function token($user): string
    {
        return $this->postJson('/api/v1/login', ['username' => $user->username, 'password' => 'Secret123', 'device_name' => 'test'])
            ->assertOk()->json('token');
    }

    public function test_login_returns_token_permissions_and_branding(): void
    {
        $sa = $this->stateAdmin();
        $res = $this->postJson('/api/v1/login', ['username' => $sa->username, 'password' => 'Secret123', 'device_name' => 'Pixel']);
        $res->assertOk()->assertJsonPath('user.role', 'state_admin')
            ->assertJsonPath('user.permissions.members', 'manage')
            ->assertJsonPath('user.permissions.petitions', 'none')
            ->assertJsonStructure(['token', 'user' => ['branding' => ['org_name', 'theme_color']]]);

        $this->postJson('/api/v1/login', ['username' => $sa->username, 'password' => 'wrong', 'device_name' => 'x'])->assertUnprocessable();
    }

    public function test_members_api_is_scoped_and_supports_photo_upload(): void
    {
        Storage::fake('local');
        Member::create(['name' => 'RJ Person', 'level' => 'state', 'state_id' => $this->rj->id, 'type' => 'bearer']);
        $sa = $this->stateAdmin();
        $h = ['Authorization' => 'Bearer '.$this->token($sa), 'Accept' => 'application/json'];

        $this->get('/api/v1/members', $h)->assertOk()->assertJsonMissing(['name' => 'RJ Person']);

        $res = $this->post('/api/v1/members', $this->memberPayload(['photo' => UploadedFile::fake()->image('cam.jpg')]), $h)
            ->assertCreated()->assertJsonPath('data.status', 'proposed');
        $id = $res->json('data.id');
        $this->get($res->json('data.photo_url'), $h)->assertOk();

        // Partial update keeps other fields.
        $this->post("/api/v1/members/$id", ['address' => 'Hazratganj'], $h)->assertOk()
            ->assertJsonPath('data.address', 'Hazratganj')->assertJsonPath('data.name', 'Test Person');
    }

    public function test_meta_dashboard_and_forbidden_modules(): void
    {
        $h = ['Authorization' => 'Bearer '.$this->token($this->stateAdmin()), 'Accept' => 'application/json'];
        $this->get('/api/v1/meta', $h)->assertOk()->assertJsonStructure(['states', 'districts', 'designations', 'lookups' => ['level'], 'member_custom_fields']);
        $this->get('/api/v1/dashboard', $h)->assertOk()->assertJsonStructure(['totals' => ['bearers'], 'breakdown' => ['rows']]);
        $this->get('/api/v1/petitions', $h)->assertForbidden();
        $this->get('/api/v1/schedule', $h)->assertForbidden();
    }

    public function test_disabled_user_token_is_rejected(): void
    {
        $sa = $this->stateAdmin();
        $h = ['Authorization' => 'Bearer '.$this->token($sa), 'Accept' => 'application/json'];
        $sa->update(['active' => false]);
        $this->app['auth']->forgetGuards(); // each real request resolves the user afresh
        $this->get('/api/v1/me', $h)->assertForbidden();
    }

    public function test_greetings_use_token_photo_urls_and_circular_attachment_downloads(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->admin();
        Member::forceCreate(['name' => 'Bday', 'level' => 'state', 'state_id' => $this->up->id, 'type' => 'bearer', 'status' => 'active',
            'mobile' => '9876543210', 'dob' => now()->subYears(40)->toDateString(), 'photo_path' => 'photos/members/x.jpg']);
        \Illuminate\Support\Facades\Storage::disk('local')->put('circulars/c.pdf', '%PDF-1.4 test');
        $c = \App\Models\Circular::create(['title' => 'With file']);
        $c->forceFill(['attachment_path' => 'circulars/c.pdf'])->save();

        $h = ['Authorization' => 'Bearer '.$this->token($admin), 'Accept' => 'application/json'];
        $g = $this->get('/api/v1/greetings?days=0', $h)->assertOk()->json('0');
        $this->assertStringContainsString('/api/v1/members/', $g['photo_url']);
        $this->get("/api/v1/circulars/{$c->id}/attachment", $h)->assertOk()->assertDownload();
    }
}
