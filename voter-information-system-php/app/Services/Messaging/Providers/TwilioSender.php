<?php

namespace App\Services\Messaging\Providers;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Http;

/** Twilio SMS and WhatsApp. WhatsApp templates: provider id = Content SID (HX...), params fill {{1}}, {{2}} ... */
class TwilioSender implements Sender
{
    private function cfg(): array { return config('voters.messaging.twilio'); }
    public function id(): string { return 'twilio'; }
    public function supports(string $channel): bool { return true; }

    public function configured(string $channel): bool
    {
        $c = $this->cfg();
        return $c['account_sid'] && $c['auth_token'] && ($channel === 'SMS' ? $c['sms_from'] : $c['whatsapp_from']);
    }

    public function send(string $channel, string $e164, string $body, ?MessageTemplate $t, array $params): SendResult
    {
        $c = $this->cfg();
        $f = [];
        if ($channel === 'WHATSAPP') { $f['To'] = 'whatsapp:' . $e164; $f['From'] = 'whatsapp:' . $c['whatsapp_from']; }
        else { $f['To'] = $e164; if (str_starts_with($c['sms_from'], 'MG')) $f['MessagingServiceSid'] = $c['sms_from']; else $f['From'] = $c['sms_from']; }
        $sid = $t?->provider_template_id;
        if ($channel === 'WHATSAPP' && $sid && str_starts_with($sid, 'HX')) {
            $f['ContentSid'] = $sid;
            $vars = [];
            foreach (array_values($params) as $i => $p) $vars[(string) ($i + 1)] = $p;
            $f['ContentVariables'] = json_encode((object) $vars, JSON_UNESCAPED_UNICODE);
        } else {
            $f['Body'] = $body;
        }
        try {
            $r = Http::asForm()->withBasicAuth($c['account_sid'], $c['auth_token'])->timeout(20)
                ->post(rtrim($c['base_url'], '/') . '/2010-04-01/Accounts/' . $c['account_sid'] . '/Messages.json', $f);
            return SendResult::fromResponse($r, $r->json('sid'));
        } catch (\Throwable $e) {
            return SendResult::fail(class_basename($e) . ': ' . $e->getMessage(), true);
        }
    }
}
