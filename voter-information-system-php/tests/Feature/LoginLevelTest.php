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
}
