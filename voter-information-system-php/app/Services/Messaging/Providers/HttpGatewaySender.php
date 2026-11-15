<?php

namespace App\Services\Messaging\Providers;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Http;

/**
 * Generic HTTP SMS gateway for DLT-registered Indian providers (MSG91, Textlocal, Fast2SMS ...).
 * URL / body may contain {mobile} {e164} {message} {templateId} {senderId} {entityId}.
 */
class HttpGatewaySender implements Sender
{
    private function cfg(): array { return config('voters.messaging.http'); }
    public function id(): string { return 'http'; }
    public function supports(string $channel): bool { return $channel === 'SMS'; }
    public function configured(string $channel): bool { return (bool) $this->cfg()['url']; }

    public function send(string $channel, string $e164, string $body, ?MessageTemplate $t, array $params): SendResult
    {
        $c = $this->cfg();
        $json = str_contains($c['content_type'], 'json');
        $vars = ['mobile' => substr($e164, -10), 'e164' => $e164, 'message' => $body, 'templateId' => (string) $t?->provider_template_id,
            'senderId' => $c['sender_id'], 'entityId' => $c['entity_id']];
        $fill = function (string $tpl, bool $asJson) use ($vars) {
            foreach ($vars as $k => $v) $tpl = str_replace('{' . $k . '}', $asJson ? substr(json_encode($v, JSON_UNESCAPED_UNICODE), 1, -1) : rawurlencode($v), $tpl);
            return $tpl;
        };
        $req = Http::timeout(20);
        if ($c['auth_header'] && str_contains($c['auth_header'], ':')) {
            [$h, $v] = array_map('trim', explode(':', $c['auth_header'], 2));
            $req = $req->withHeaders([$h => $v]);
        }
        try {
            $url = $fill($c['url'], false);
            $r = strtoupper($c['method']) === 'POST'
                ? $req->withBody($fill($c['body'], $json), $c['content_type'] . '; charset=UTF-8')->post($url)
                : $req->get($url);
            $res = SendResult::fromResponse($r, null);
            if ($res->success && $c['success_contains'] && !str_contains($r->body(), $c['success_contains'])) {
                return SendResult::fail('Unexpected response: ' . mb_substr($r->body(), 0, 200), false);
            }
            if ($res->success) return SendResult::ok($r->json('request_id') ?? $r->json('message') ?? 'HTTP ' . $r->status());
            return $res;
        } catch (\Throwable $e) {
            return SendResult::fail(class_basename($e) . ': ' . $e->getMessage(), true);
        }
    }
}
