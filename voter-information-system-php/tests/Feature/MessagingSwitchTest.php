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
}
