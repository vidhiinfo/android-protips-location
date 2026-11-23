<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123']);
        (new DemoSeeder())->run(10);
    }

    public function test_switching_messaging_off_hides_it_and_on_brings_it_back(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        config(['voters.messaging.enabled' => false]);
        $this->get('/voters?assemblyNo=45')->assertOk()->assertDontSee('/messages/compose', false)->assertDontSee('/messages/campaigns', false);
        $this->get('/messages/campaigns')->assertForbidden();
        $this->get('/messages/inbox')->assertForbidden();
        $this->get('/messages/greetings')->assertForbidden();
        $this->assertFalse(app(\App\Services\Messaging\Gateway::class)->send('SMS', '+919810000001', 'x', null, [])->success);
        $this->assertSame([], app(\App\Services\Messaging\GreetingService::class)->run(true));
        // role editor hides the messaging permissions but saving keeps them
        $op = Role::where('code', 'OPERATOR')->first();
        $this->assertContains('MESSAGE_SEND', $op->permissions());
        $this->get('/admin/roles/' . $op->id . '/edit')->assertOk()->assertDontSee('value="MESSAGE_SEND"', false);
        $keep = array_values(array_diff($op->permissions(), ['MESSAGE_SEND', 'MESSAGE_ADMIN']));
        $this->post('/admin/roles', ['id' => $op->id, 'code' => 'OPERATOR', 'name' => $op->name, 'perms' => $keep])->assertRedirect();
        $this->assertContains('MESSAGE_SEND', $op->fresh()->permissions());
        // back on: everything is there again
        config(['voters.messaging.enabled' => true]);
        $this->get('/voters?assemblyNo=45')->assertSee('/messages/compose', false);
        $this->get('/messages/campaigns')->assertOk();
    }

    public function test_admin_switches_messaging_from_the_settings_page(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->get('/admin/settings')->assertOk()->assertSee('SMS / WhatsApp');
        $this->post('/admin/settings', ['s' => ['messaging_enabled' => '0']])->assertRedirect()->assertSessionHas('success');
        $this->assertFalse(\App\Support\Permission::messagingOn());
        $this->get('/voters?assemblyNo=45')->assertDontSee('/messages/compose', false);
        $this->get('/messages/campaigns')->assertForbidden();
        // the screen setting wins over .env (applied again at every start-up)
        config(['voters.messaging.enabled' => true]);
        \App\Support\Settings::flush(); \App\Support\Settings::applyToConfig();
        $this->get('/messages/campaigns')->assertForbidden();
        $this->post('/admin/settings', ['s' => ['messaging_enabled' => '1']])->assertRedirect();
        $this->get('/messages/campaigns')->assertOk();
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('audit_log')->where('details', 'like', '%messaging_enabled = 0%')->count());
        // only administrators
        $this->post('/logout');
        $this->post('/login', ['username' => 'viewer', 'password' => 'viewer@123']);
        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_every_setting_can_be_changed_on_screen_and_reset(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->get('/admin/settings')->assertOk()->assertSee('s[field_max_booths]', false)->assertSee('s[show_ward_no]', false)->assertSee('s[app_name]', false);
        $this->post('/admin/settings', ['s' => ['field_max_booths' => '2', 'offline_keep_days' => '99', 'show_ward_no' => '0', 'app_name' => 'Booth Test (Bihar)',
            'otp_required' => 'admins', 'messaging_enabled' => '1']])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(2, config('voters.survey.field_max_booths'));
        $this->assertSame(30, config('voters.survey.offline_days'));            // kept inside the allowed range
        $this->assertFalse(\App\Models\Voter::showWard());
        $this->assertSame('admins', config('voters.security.otp_required'));
        $this->post('/admin/settings', ['reset' => 'otp_required'])->assertRedirect();   // back to none, so the admin can log in again below
        $this->assertSame('none', config('voters.security.otp_required'));
        $this->assertCount(2, $this->getJson('/field/pack?assemblyNo=45&booths[]=1&booths[]=2&booths[]=3')->json('booths'));
        $this->post('/logout');
        $this->get('/login')->assertSee('Booth Test (Bihar)');
        // a fresh start reads them back from the database
        config(['voters.survey.field_max_booths' => 5, 'voters.show_ward_no' => true]);
        \App\Support\Settings::flush(); \App\Support\Settings::applyToConfig();
        $this->assertSame([2, false], [config('voters.survey.field_max_booths'), config('voters.show_ward_no')]);
        // reset one back to the server value
        $this->post('/login', ['username' => 'admin', 'password' => 'admin@123']);
        $this->post('/admin/settings', ['reset' => 'show_ward_no'])->assertRedirect('/admin/settings');
        $this->assertTrue(\App\Models\Voter::showWard());
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('audit_log')->where('details', 'like', '%field_max_booths = 2%')->count());
    }
}
