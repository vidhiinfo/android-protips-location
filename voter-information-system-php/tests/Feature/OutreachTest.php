<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\MessageTemplate;
use App\Models\OutboxMessage;
use App\Models\Voter;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OutreachTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-webhook-token-1234567890';

    protected function setUp(): void
    {
        parent::setUp();
        config(['voters.admin.username' => 'admin', 'voters.admin.password' => 'admin@123', 'voters.messaging.webhook_token' => self::TOKEN,
            'voters.messaging.enforce_silence_period' => false]);
        (new DemoSeeder())->run(25);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        config(['voters.security.office_session_minutes' => 0]);   // these tests jump days ahead while logged in
    }

    private function login(string $u = 'admin', string $p = 'admin@123') { return $this->post('/login', ['username' => $u, 'password' => $p]); }

    private function template(string $channel = 'SMS'): MessageTemplate
    {
        return MessageTemplate::create(['name' => 'Bday', 'channel' => $channel, 'language' => 'en', 'body' => 'Happy birthday {name}!', 'active' => true, 'created_at' => now()]);
    }

    public function test_birthday_greetings_are_queued_once_a_day(): void
    {
        $vs = Voter::where('assembly_no', 45)->where('deleted', false)->orderBy('id')->limit(3)->get();
        $today = now(config('voters.messaging.time_zone'));
        $vs[0]->update(['dob' => $today->copy()->subYears(30)->toDateString(), 'mobile' => '9810000001']);
        $vs[1]->update(['dob' => $today->copy()->subYears(41)->toDateString(), 'mobile' => null]);              // no mobile: skipped
        $vs[2]->update(['dob' => $today->copy()->subYears(30)->addDay()->toDateString(), 'mobile' => '9810000003']); // tomorrow
        $t = $this->template();
        $this->login();
        $this->post('/messages/greetings', ['kind' => 'BIRTHDAY', 'template_id' => $t->id, 'assembly_no' => 45, 'send_at' => '00:00'])->assertRedirect();
        $this->get('/messages/greetings')->assertOk()->assertSee('Birthday');
        $res = app(\App\Services\Messaging\GreetingService::class)->run(true);
        $cid = array_values($res)[0];
        $this->assertIsInt($cid);
        $out = OutboxMessage::where('campaign_id', $cid)->get();
        $this->assertSame(['+919810000001'], $out->pluck('mobile')->all());
        $this->assertSame('Happy birthday ' . $vs[0]->name . '!', $out[0]->body);
        $this->assertSame([], app(\App\Services\Messaging\GreetingService::class)->run(true));   // already ran today
        // Feb 29 birthdays are greeted on Feb 28 in other years
        $leap = Voter::find($vs[2]->id);
        $leap->update(['dob' => '1992-02-29']);
        $this->assertSame(1, app(\App\Services\Messaging\GreetingService::class)->voters('BIRTHDAY', 45, \Carbon\Carbon::parse('2027-02-28'))->where('id', $leap->id)->count());
    }

    public function test_delivery_receipts_from_twilio_and_meta(): void
    {
        $t = $this->template();
        $camp = Campaign::create(['name' => 'x', 'channel' => 'SMS', 'template_id' => $t->id, 'status' => 'COMPLETED', 'total' => 2, 'sent' => 2, 'created_at' => now()]);
        $a = OutboxMessage::create(['campaign_id' => $camp->id, 'mobile' => '+919810000001', 'body' => 'b', 'status' => 'SENT', 'provider_id' => 'SM123', 'attempts' => 1, 'created_at' => now()]);
        $b = OutboxMessage::create(['campaign_id' => $camp->id, 'mobile' => '+919810000002', 'body' => 'b', 'status' => 'SENT', 'provider_id' => 'wamid.X', 'attempts' => 1, 'created_at' => now()]);
        $this->post('/hooks/wrong-token-0000000000/twilio/status', ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'])->assertNotFound();
        $this->post('/hooks/' . self::TOKEN . '/twilio/status', ['MessageSid' => 'SM123', 'MessageStatus' => 'delivered'])->assertNoContent();
        $this->assertSame('DELIVERED', $a->fresh()->delivery);
        $meta = fn ($status) => ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.X', 'status' => $status, 'timestamp' => (string) time()]]]]]]]];
        $this->postJson('/hooks/' . self::TOKEN . '/meta', $meta('read'))->assertOk();
        $this->postJson('/hooks/' . self::TOKEN . '/meta', $meta('delivered'))->assertOk();   // late "delivered" does not undo "read"
        $this->assertSame('READ', $b->fresh()->delivery);
        $this->assertNotNull($b->fresh()->delivered_at);
        // Meta signature is checked when the app secret is set
        config(['voters.messaging.meta.app_secret' => 'shh']);
        $this->postJson('/hooks/' . self::TOKEN . '/meta', $meta('read'))->assertForbidden();
        $body = json_encode($meta('read'));
        $this->call('POST', '/hooks/' . self::TOKEN . '/meta', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, 'shh')], $body)->assertOk();
        // webhook verification
        config(['voters.messaging.meta.verify_token' => 'vt']);
        $this->get('/hooks/' . self::TOKEN . '/meta?hub.mode=subscribe&hub.verify_token=vt&hub.challenge=42')->assertOk()->assertSee('42');
        $this->login();
        $this->get('/messages/campaigns/' . $camp->id)->assertOk()->assertSee('Delivered');
    }

    public function test_inbox_receives_replies_handles_stop_and_sends_answers(): void
    {
        $v = Voter::where('assembly_no', 45)->where('deleted', false)->first();
        $v->update(['mobile' => '9810000009']);
        $this->post('/hooks/' . self::TOKEN . '/twilio/inbound', ['From' => 'whatsapp:+919810000009', 'Body' => 'Road near my house is broken', 'MessageSid' => 'SMin1'])->assertOk();
        $this->post('/hooks/' . self::TOKEN . '/twilio/inbound', ['From' => 'whatsapp:+919810000009', 'Body' => 'Road near my house is broken', 'MessageSid' => 'SMin1']);   // retry
        $conv = DB::table('conversations')->first();
        $this->assertSame([$v->id, 'WHATSAPP', 1], [(int) $conv->voter_id, $conv->channel, (int) $conv->unread]);
        $this->assertSame(1, DB::table('conversation_messages')->count());
        $this->login();
        $this->get('/messages/inbox')->assertOk()->assertSee($v->name);
        $this->get('/messages/inbox/' . $conv->id)->assertOk()->assertSee('Road near my house is broken');
        $this->assertSame(0, (int) DB::table('conversations')->value('unread'));
        $this->post('/messages/inbox/' . $conv->id . '/reply', ['body' => 'We will send someone tomorrow'])->assertRedirect()->assertSessionHas('success');
        $out = DB::table('conversation_messages')->where('direction', 'OUT')->first();
        $this->assertSame(['SENT', 'admin'], [$out->status, $out->by_user]);
        // after 24 hours a free-text WhatsApp reply is refused
        $this->travel(25)->hours();
        $this->post('/messages/inbox/' . $conv->id . '/reply', ['body' => 'Hello again'])->assertSessionHas('error');
        // STOP opts the number out
        $this->post('/hooks/' . self::TOKEN . '/sms/inbound', ['from' => '9810000009', 'text' => 'STOP', 'id' => 'in-2'])->assertOk();
        $this->assertTrue(app(\App\Services\Messaging\MessagingService::class)->isOptedOut('9810000009', 'SMS'));
    }

    public function test_phone_bank_hands_out_each_voter_once_and_logs_calls(): void
    {
        Voter::where('assembly_no', 45)->where('booth_no', 1)->update(['mobile' => null]);
        $vs = Voter::where('assembly_no', 45)->where('booth_no', 1)->where('deleted', false)->orderBy('serial_no')->limit(2)->get();
        $vs[0]->update(['mobile' => '9810000011', 'political_status' => null]);
        $vs[1]->update(['mobile' => '9810000012']);
        $this->login();
        $this->get('/phonebank?assemblyNo=45&boothNo=1')->assertOk()->assertSee('tel:+919810000011', false);
        // another caller gets the second voter
        $u = \App\Models\AppUser::create(['username' => 'caller2', 'full_name' => 'Caller', 'enabled' => true, 'created_at' => now(), 'password_hash' => \App\Models\AppUser::hashPassword('Call@2027')]);
        $u->roles()->attach(\App\Models\Role::where('code', 'OPERATOR')->value('id'));
        $this->post('/logout');
        $this->login('caller2', 'Call@2027');
        $this->get('/phonebank?assemblyNo=45&boothNo=1')->assertOk()->assertSee('tel:+919810000012', false)->assertDontSee('tel:+919810000011', false);
        $this->post('/phonebank/' . $vs[1]->id, ['outcome' => 'NO_ANSWER', 'boothNo' => 1])->assertRedirect();
        $this->post('/logout');
        $this->login();
        $this->post('/phonebank/' . $vs[0]->id, ['outcome' => 'ANSWERED', 'leaning' => 'S', 'set_status' => 1, 'notes' => 'Wants water connection', 'boothNo' => 1])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($vs[0]->fresh()->political_status);
        $this->get('/phonebank?assemblyNo=45&boothNo=1')->assertSee('Nobody left to call');
        // no answer comes back after a few hours
        $this->travel(4)->hours();
        $this->get('/phonebank?assemblyNo=45&boothNo=1&mode=callback')->assertSee('tel:+919810000012', false);
        $this->get('/voters/' . $vs[0]->id)->assertOk()->assertSee('Wants water connection');
        $this->assertSame(2, DB::table('call_logs')->count());
    }
}
