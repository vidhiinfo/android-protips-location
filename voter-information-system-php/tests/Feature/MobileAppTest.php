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
}
