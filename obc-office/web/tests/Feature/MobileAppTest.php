<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MobileAppTest extends OfficeTestCase
{
    private function apk(): UploadedFile
    {
        return UploadedFile::fake()->create('app-release.apk', 2048, 'application/vnd.android.package-archive');
    }

    public function test_admin_uploads_app_and_public_link_downloads_it(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin())->get('/mobile-app')->assertOk()->assertSee('Not uploaded yet');

        $this->post('/mobile-app/upload', ['apk' => $this->apk(), 'version' => '1.1.0', 'notes' => 'Leader photo on login'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('1.1.0', setting('app_version'));
        $this->get('/mobile-app')->assertOk()->assertSee('version 1.1.0')->assertSee(route('app.public'));

        auth()->logout();
        $this->get('/app')->assertOk()->assertSee('Download for Android')->assertSee('1.1.0');
        $res = $this->get('/app/download')->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('OBC-Office-1.1.0.apk', $res->headers->get('Content-Disposition'));
        $this->assertSame(1, (int) setting('app_downloads'));
    }

    public function test_only_apk_files_are_accepted(): void
    {
        $this->actingAs($this->admin())->post('/mobile-app/upload', ['apk' => UploadedFile::fake()->create('x.pdf', 10), 'version' => '1'])
            ->assertSessionHasErrors('apk');
    }

    public function test_public_page_can_be_switched_off(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/mobile-app/upload', ['apk' => $this->apk(), 'version' => '1.0']);
        $this->post('/mobile-app/settings', ['app_public' => '0']);

        $this->get('/app/download')->assertOk(); // signed-in users can still download
        auth()->logout();
        $this->get('/app')->assertNotFound();
        $this->get('/app/download')->assertNotFound();
    }

    public function test_state_users_can_download_but_not_upload(): void
    {
        $this->actingAs($this->stateAdmin())->get('/mobile-app')->assertOk()->assertDontSee('Upload a new version');
        $this->post('/mobile-app/upload', ['apk' => $this->apk(), 'version' => '9'])->assertForbidden();
    }

    public function test_public_branding_api_for_app_login_screen(): void
    {
        Setting::put('app_version', '1.1.0');
        $this->getJson('/api/v1/branding')->assertOk()
            ->assertJsonPath('leader_name', 'श्री अमरपाल मौर्य')
            ->assertJsonCount(2, 'leader_designations')
            ->assertJsonPath('leader_on_login', true)
            ->assertJsonPath('app_version', '1.1.0')
            ->assertJsonStructure(['org_name', 'theme_color', 'leader_photo_url', 'app_download_url', 'app_page_url']);
    }
}
