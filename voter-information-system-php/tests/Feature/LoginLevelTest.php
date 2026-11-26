<?php

namespace Tests\Feature;

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLevelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
    }

    public function test_assembly_site_keeps_the_usual_login_page(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('login-banner-ls', $html);
        $this->assertStringContainsString(t('login.banner.title'), $html);
        $this->assertStringContainsString(t('login.assembly'), $html);
    }

    public function test_lok_sabha_site_set_on_the_settings_screen(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->post('/admin/settings', ['s' => ['site_level' => 'loksabha', 'constituency' => 'New Delhi (PC 4)',
            'organisation' => 'Ms. Bansuri Swaraj, Member of Parliament, New Delhi Lok Sabha']])->assertRedirect();
        $this->get('/admin/settings')->assertOk()->assertSee(t('settings.s.site_level'))->assertSee('New Delhi (PC 4)');
        $this->post('/logout');
        \App\Support\Settings::applyToConfig();
        $html = html_entity_decode($this->get('/login')->assertOk()->getContent());
        $this->assertStringContainsString('login-banner-ls', $html);
        $this->assertStringContainsString(t('login.level.loksabha'), $html);
        $this->assertStringContainsString('New Delhi (PC 4)', $html);
        $this->assertStringContainsString('Ms. Bansuri Swaraj, Member of Parliament, New Delhi Lok Sabha', $html);
        $this->assertStringContainsString(t('login.banner.titleLs'), $html);
        $this->assertStringContainsString(t('login.segment'), $html);
    }

    public function test_representative_photo_circle_placeholder_then_upload(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->post('/admin/settings', ['s' => ['organisation' => 'Ms. Bansuri Swaraj, Member of Parliament, New Delhi Lok Sabha']]);
        \App\Support\Settings::applyToConfig();
        $html = $this->get('/login')->getContent();
        $this->assertStringContainsString('login-rep-photo', $html);
        $this->assertStringContainsString('bi-person-fill', $html, 'placeholder until a photo is uploaded');
        // upload: any picture becomes a 400 x 400 JPEG under public/uploads/branding
        $img = imagecreatetruecolor(800, 500); imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        $tmp = tempnam(sys_get_temp_dir(), 'p') . '.png'; imagepng($img, $tmp);
        $this->post('/admin/settings', ['photo' => ['rep_photo' => new \Illuminate\Http\UploadedFile($tmp, 'mp.png', 'image/png', null, true)]])->assertRedirect();
        $path = (string) \Illuminate\Support\Facades\DB::table('app_settings')->where('name', 'rep_photo')->value('value');
        $this->assertMatchesRegularExpression('#^uploads/branding/rep_photo-\d{14}\.jpg$#', $path);
        $this->assertSame([400, 400, IMAGETYPE_JPEG], array_slice(getimagesize(public_path($path)), 0, 3));
        \App\Support\Settings::applyToConfig();
        $this->get('/login')->assertSee($path, false);
        // a file that is not a picture is refused
        $bad = tempnam(sys_get_temp_dir(), 'b') . '.jpg'; file_put_contents($bad, '<?php echo 1;');
        $this->post('/admin/settings', ['photo' => ['rep_photo' => new \Illuminate\Http\UploadedFile($bad, 'x.jpg', 'image/jpeg', null, true)]])
            ->assertRedirect()->assertSessionHas('error');
        @unlink(public_path($path));
    }
}
