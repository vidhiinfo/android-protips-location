<?php

namespace App\Http\Controllers;

use App\Services\Messaging\InboxService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Calls from the messaging providers (no login): delivery / read receipts and replies from voters.
 * Every URL carries the secret WEBHOOK_TOKEN: /hooks/{token}/twilio/status, /hooks/{token}/twilio/inbound,
 * /hooks/{token}/meta (WhatsApp Cloud API), /hooks/{token}/sms/status, /hooks/{token}/sms/inbound (other SMS gateways).
 */
class WebhookController extends Controller
{
    public function __construct(private InboxService $inbox) {}

    private function check(string $token): void
    {
        $want = (string) config('voters.messaging.webhook_token');
        abort_if(strlen($want) < 16 || !hash_equals($want, $token) || !InboxService::enabled(), 404);
    }

    public function twilioStatus(Request $r, string $token)
    {
        $this->check($token);
        $this->inbox->receipt((string) $r->input('MessageSid'), (string) $r->input('MessageStatus'));
        return response('', 204);
    }

    public function twilioInbound(Request $r, string $token)
    {
        $this->check($token);
        $from = (string) $r->input('From');
        $channel = str_starts_with($from, 'whatsapp:') ? 'WHATSAPP' : 'SMS';
        $media = (int) $r->input('NumMedia', 0) > 0 ? mb_substr((string) $r->input('MediaContentType0', 'media'), 0, 20) : null;
        $this->inbox->receive($channel, str_replace('whatsapp:', '', $from), $r->input('Body'), $media, $r->input('MessageSid'), $r->input('ProfileName'));
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml']);
    }

    /** WhatsApp Cloud API webhook verification (GET with hub.challenge). */
    public function metaVerify(Request $r, string $token)
    {
        $this->check($token);
        $verify = (string) config('voters.messaging.meta.verify_token');
        abort_unless($r->query('hub_mode') === 'subscribe' && $verify !== '' && hash_equals($verify, (string) $r->query('hub_verify_token')), 403);
        return response((string) $r->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function meta(Request $r, string $token)
    {
        $this->check($token);
        $secret = (string) config('voters.messaging.meta.app_secret');
        if ($secret !== '') {
            $sig = (string) $r->header('X-Hub-Signature-256');
            abort_unless(hash_equals('sha256=' . hash_hmac('sha256', $r->getContent(), $secret), $sig), 403);
        }
        foreach ((array) $r->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $ch) {
                $val = $ch['value'] ?? [];
                foreach ((array) ($val['statuses'] ?? []) as $s) {
                    $at = isset($s['timestamp']) && is_numeric($s['timestamp']) ? Carbon::createFromTimestamp((int) $s['timestamp']) : null;
                    $this->inbox->receipt((string) ($s['id'] ?? ''), (string) ($s['status'] ?? ''), $at);
                }
                $names = [];
                foreach ((array) ($val['contacts'] ?? []) as $c) $names[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? null;
                foreach ((array) ($val['messages'] ?? []) as $m) {
                    $type = (string) ($m['type'] ?? 'text');
                    $body = $type === 'text' ? ($m['text']['body'] ?? null) : ($m['button']['text'] ?? $m['interactive']['button_reply']['title'] ?? $m[$type]['caption'] ?? null);
                    $this->inbox->receive('WHATSAPP', '+' . ltrim((string) ($m['from'] ?? ''), '+'), $body, $type === 'text' ? null : mb_substr($type, 0, 20),
                        $m['id'] ?? null, $names[$m['from'] ?? ''] ?? null);
                }
            }
        }
        return response()->json(['ok' => true]);
    }

    /** Other SMS gateways: ?id=<message id>&status=delivered|failed|read (GET or POST). */
    public function smsStatus(Request $r, string $token)
    {
        $this->check($token);
        $n = $this->inbox->receipt((string) $r->input('id', $r->input('msgid', '')), (string) $r->input('status', ''));
        return response()->json(['updated' => $n]);
    }

    /** Other SMS gateways: ?from=<mobile>&text=<message>&id=<message id>. */
    public function smsInbound(Request $r, string $token)
    {
        $this->check($token);
        $id = $this->inbox->receive('SMS', (string) $r->input('from', $r->input('sender', '')), $r->input('text', $r->input('message')), null, $r->input('id'));
        if ($id === null) Log::info('SMS inbound ignored (bad number or repeat)');
        return response()->json(['ok' => true]);
    }
}
