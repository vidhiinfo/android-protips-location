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
        $this->post('/admin/settings', ['messaging' => '0'])->assertRedirect()->assertSessionHas('success');
        \App\Support\Settings::flush();
        $this->assertFalse(\App\Support\Permission::messagingOn());
        $this->get('/voters?assemblyNo=45')->assertDontSee('/messages/compose', false);
        $this->get('/messages/campaigns')->assertForbidden();
        // the screen setting wins over .env
        config(['voters.messaging.enabled' => true]);
        $this->get('/messages/campaigns')->assertForbidden();
        $this->post('/admin/settings', ['messaging' => '1'])->assertRedirect();
        $this->get('/messages/campaigns')->assertOk();
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('audit_log')->where('details', 'like', '%switched OFF%')->count());
        // only administrators
        $this->post('/logout');
        $this->post('/login', ['username' => 'viewer', 'password' => 'viewer@123']);
        $this->get('/admin/settings')->assertForbidden();
    }
}
