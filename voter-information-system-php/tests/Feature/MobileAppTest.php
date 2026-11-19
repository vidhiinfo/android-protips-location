<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppTest extends TestCase
{
    use RefreshDatabase;
    public function test_install_page_is_public_and_pages_link_the_manifest(): void
    {
        $this->get('/mobile-app')->assertOk()->assertSee('manifest.webmanifest', false)->assertSee('Add to Home Screen')->assertSee('apple-touch-icon', false);
        $this->get('/login')->assertOk()->assertSee(url('/mobile-app'), false)->assertSee('theme-color', false);
    }

    public function test_manifest_service_worker_and_icons_exist(): void
    {
        $m = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('standalone', $m['display']);
        foreach ($m['icons'] as $i) $this->assertFileExists(public_path($i['src']));
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));
        $this->assertFileExists(public_path('images/app/apple-touch-icon.png'));
    }

    public function test_expired_security_token_goes_to_login_instead_of_419_page(): void
    {
        $this->withMiddleware();
        app('router')->aliasMiddleware('t419', fn ($r, $n) => throw new \Illuminate\Session\TokenMismatchException());
        \Illuminate\Support\Facades\Route::middleware('web')->post('/_test419', fn () => 'ok')->middleware('t419');
        $this->post('/_test419')->assertRedirect('/login?error=expired');
        $this->postJson('/_test419')->assertStatus(419)->assertJson(['reload' => true]);
        $this->get('/login?error=expired')->assertOk()->assertSee(t('login.error.expired'))->assertHeader('Cache-Control');
        $this->getJson('/csrf-token')->assertOk()->assertJsonStructure(['token']);
    }

    public function test_javascript_files_have_no_syntax_errors(): void
    {
        exec('node --version 2>/dev/null', $o, $rc);
        if ($rc !== 0) $this->markTestSkipped('node is not installed');
        foreach (glob(public_path('js/*.js')) as $f) {
            exec('node --check ' . escapeshellarg($f) . ' 2>&1', $out, $code);
            $this->assertSame(0, $code, basename($f) . ': ' . implode("\n", $out));
        }
    }
}
