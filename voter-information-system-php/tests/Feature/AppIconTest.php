<?php

namespace Tests\Feature;

use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

class AppIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_screen_icon_uploaded_in_settings(): void
    {
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(25);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $img = imagecreatetruecolor(700, 600); imagefill($img, 0, 0, imagecolorallocate($img, 230, 90, 10));
        $tmp = tempnam(sys_get_temp_dir(), 'i') . '.png'; imagepng($img, $tmp);
        $this->post('/admin/settings', ['photo' => ['app_icon' => new UploadedFile($tmp, 'icon.png', 'image/png', null, true)]])->assertRedirect()->assertSessionHas('success');
        $p = (string) \Illuminate\Support\Facades\DB::table('app_settings')->where('name', 'app_icon')->value('value');
        $this->assertMatchesRegularExpression('#^uploads/branding/app-icon-\d+-512\.png$#', $p);
        foreach (['512', '192', '180'] as $s) {
            $f = public_path(str_replace('-512.png', "-$s.png", $p));
            $this->assertFileExists($f);
            $this->assertSame([(int) $s, (int) $s], array_slice(getimagesize($f), 0, 2));
        }
        Settings::applyToConfig();
        $m = $this->get('/site.webmanifest')->assertOk()->json();
        $this->assertStringEndsWith(str_replace('-512.png', '-192.png', $p), $m['icons'][0]['src']);
        $this->get('/login')->assertSee('site.webmanifest', false)->assertSee(str_replace('-512.png', '-180.png', $p), false);
        foreach (['512', '192', '180'] as $s) @unlink(public_path(str_replace('-512.png', "-$s.png", $p)));
    }
}
