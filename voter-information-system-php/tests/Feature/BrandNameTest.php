<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_name_and_static_manifest(): void
    {
        $this->get('/login')->assertOk()->assertSee('Voter Information System')->assertSee('manifest.webmanifest', false)->assertDontSee('All rights reserved');
    }

    public function test_product_name_from_env_on_pages_and_phone_app(): void
    {
        config(['voters.branding.app_name' => 'Booth Management Solution (Uttar Pradesh)', 'voters.branding.app_name_local' => 'बूथ प्रबंधन समाधान (उत्तर प्रदेश)',
            'voters.branding.short_name' => 'Booth UP', 'voters.branding.owner' => 'Vidhi Info Solutions']);
        $this->get('/login')->assertOk()->assertSee('Booth Management Solution (Uttar Pradesh)')->assertDontSee('Voter Information System')
            ->assertSee('app.webmanifest', false)->assertSee(t('brand.by', 'Vidhi Info Solutions'))->assertSee('© ' . date('Y') . ' Vidhi Info Solutions');
        $this->get('/login?lang=hi')->assertOk()->assertSee('बूथ प्रबंधन समाधान (उत्तर प्रदेश)');
        $m = $this->get('/app.webmanifest')->assertOk()->assertHeader('content-type', 'application/manifest+json')->json();
        $this->assertSame(['Booth Management Solution (Uttar Pradesh)', 'Booth UP'], [$m['name'], $m['short_name']]);
        $this->assertStringEndsWith('/dashboard?source=app', $m['start_url']);
        $this->assertStringEndsWith('/images/app/icon-192.png', $m['icons'][0]['src']);
        $this->get('/mobile-app')->assertOk()->assertSee('Booth Management Solution (Uttar Pradesh)')->assertSee('Vidhi Info Solutions');
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->get('/voters')->assertOk()->assertSee('<footer', false)->assertSee('Vidhi Info Solutions');
    }
}
