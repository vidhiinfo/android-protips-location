<?php

namespace App\Services\Messaging\Providers;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Http;

/** WhatsApp Business Cloud API. Business-initiated messages use an approved template (provider id = template name). */
class MetaWhatsAppSender implements Sender
{
    private function cfg(): array { return config('voters.messaging.meta'); }
    public function id(): string { return 'meta'; }
    public function supports(string $channel): bool { return $channel === 'WHATSAPP'; }
    public function configured(string $channel): bool { return $this->cfg()['phone_number_id'] && $this->cfg()['access_token']; }

    public function send(string $channel, string $e164, string $body, ?MessageTemplate $t, array $params): SendResult
    {
        $c = $this->cfg();
        $to = ltrim($e164, '+');
        if ($t && $t->provider_template_id) {
            $tpl = ['name' => $t->provider_template_id, 'language' => ['code' => $t->language]];
            if ($params) $tpl['components'] = [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], array_values($params))]];
            $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => $tpl];
        } else {
            $payload = ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => $body]];
        }
        try {
            $r = Http::withToken($c['access_token'])->timeout(20)->asJson()
                ->post(rtrim($c['base_url'], '/') . '/' . $c['api_version'] . '/' . $c['phone_number_id'] . '/messages', $payload);
            return SendResult::fromResponse($r, $r->json('messages.0.id'));
        } catch (\Throwable $e) {
            return SendResult::fail(class_basename($e) . ': ' . $e->getMessage(), true);
        }
    }
}
