<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Services\Messaging\Gateway;
use App\Services\Messaging\TemplateRenderer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MessagingProvidersTest extends TestCase
{
    public function test_twilio_sms_and_whatsapp_template(): void
    {
        config(['voters.messaging.sms_provider' => 'twilio', 'voters.messaging.whatsapp_provider' => 'twilio', 'voters.messaging.twilio' => [
            'account_sid' => 'AC123', 'auth_token' => 'tok', 'sms_from' => '+15550001', 'whatsapp_from' => '+15550002', 'base_url' => 'https://api.twilio.test']]);
        Http::fake(['api.twilio.test/*' => Http::response(['sid' => 'SM42'], 201)]);
        $g = app(Gateway::class);
        $r = $g->send('SMS', '+919876543210', 'नमस्ते', null, []);
        $this->assertTrue($r->success);
        $this->assertSame('SM42', $r->providerId);
        $t = new MessageTemplate(['channel' => 'WHATSAPP', 'provider_template_id' => 'HX99', 'params' => 'name,boothNo']);
        $g->send('WHATSAPP', '+919876543210', 'x', $t, ['Ravi', '12']);
        Http::assertSent(fn (Request $q) => str_contains($q->url(), '/Accounts/AC123/Messages.json') && $q['To'] === 'whatsapp:+919876543210'
            && $q['ContentSid'] === 'HX99' && json_decode($q['ContentVariables'], true) === ['1' => 'Ravi', '2' => '12']);
        Http::assertSent(fn (Request $q) => ($q['Body'] ?? null) === 'नमस्ते' && $q['From'] === '+15550001' && $q->hasHeader('Authorization'));
    }

    public function test_meta_whatsapp_template_message(): void
    {
        config(['voters.messaging.whatsapp_provider' => 'meta', 'voters.messaging.meta' => [
            'phone_number_id' => '777', 'access_token' => 'secret', 'api_version' => 'v21.0', 'base_url' => 'https://graph.test']]);
        Http::fake(['graph.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $t = new MessageTemplate(['channel' => 'WHATSAPP', 'language' => 'hi', 'provider_template_id' => 'voter_slip', 'params' => 'name']);
        $r = app(Gateway::class)->send('WHATSAPP', '+919876543210', 'x', $t, ['रमेश']);
        $this->assertTrue($r->success);
        $this->assertSame('wamid.1', $r->providerId);
        Http::assertSent(fn (Request $q) => $q->url() === 'https://graph.test/v21.0/777/messages' && $q['to'] === '919876543210'
            && $q['template']['name'] === 'voter_slip' && $q['template']['language']['code'] === 'hi'
            && $q['template']['components'][0]['parameters'][0]['text'] === 'रमेश' && $q->hasHeader('Authorization', 'Bearer secret'));
    }

    public function test_generic_gateway_get_and_errors(): void
    {
        config(['voters.messaging.sms_provider' => 'http', 'voters.messaging.http' => ['method' => 'GET',
            'url' => 'https://sms.test/send?to={mobile}&text={message}&sender={senderId}&tpl={templateId}', 'body' => '', 'content_type' => 'application/x-www-form-urlencoded',
            'auth_header' => 'X-Api-Key: k', 'sender_id' => 'VOTERS', 'entity_id' => '', 'success_contains' => 'OK']]);
        Http::fakeSequence('sms.test/*')->push('OK 123')->push('ERROR balance')->push('down', 503);
        $g = app(Gateway::class);
        $this->assertTrue($g->send('SMS', '+919876543210', 'Vote & win', null, [])->success);
        Http::assertSent(fn (Request $q) => str_contains($q->url(), 'to=9876543210&text=Vote%20%26%20win&sender=VOTERS') && $q->hasHeader('X-Api-Key', 'k'));
        $bad = $g->send('SMS', '+919876543210', 'x', null, []);
        $this->assertFalse($bad->success);
        $this->assertFalse($bad->retryable);
        $down = $g->send('SMS', '+919876543210', 'x', null, []);
        $this->assertFalse($down->success);
        $this->assertTrue($down->retryable);
    }

    public function test_unicode_sms_uses_seventy_character_parts(): void
    {
        $r = new TemplateRenderer();
        $this->assertSame(1, $r->smsSegments(str_repeat('a', 160)));
        $this->assertSame(2, $r->smsSegments(str_repeat('a', 161)));
        $this->assertSame(1, $r->smsSegments(str_repeat('क', 70)));
        $this->assertSame(2, $r->smsSegments(str_repeat('क', 71)));
    }
}
