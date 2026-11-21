<?php

namespace App\Services\Messaging;

use App\Models\MessageTemplate;
use App\Models\Voter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way messaging: replies from voters (WhatsApp / SMS) land in conversations, workers answer from the inbox,
 * and delivery / read receipts from the providers update campaign messages and replies.
 */
class InboxService
{
    /** Words that stop all messages to the number (opt-out), in English and Hindi. */
    public const STOP_WORDS = ['STOP', 'UNSUBSCRIBE', 'STOP ALL', 'BAND KARO', 'बंद', 'बंद करो', 'रोकें'];
    private const RANK = ['SENT' => 1, 'DELIVERED' => 2, 'READ' => 3];

    public function __construct(private MessagingService $msg, private Gateway $gateway) {}

    public static function enabled(): bool { return Schema::hasTable('conversations'); }

    /** A message from a voter. Returns the conversation id. */
    public function receive(string $channel, string $from, ?string $body, ?string $mediaType, ?string $providerId, ?string $profileName = null): ?int
    {
        $e164 = $this->msg->e164($from);
        if (!$e164) return null;
        if ($providerId && DB::table('conversation_messages')->where('provider_id', $providerId)->where('direction', 'IN')->exists()) return null;   // retried webhook
        return DB::transaction(function () use ($channel, $e164, $body, $mediaType, $providerId, $profileName) {
            $conv = DB::table('conversations')->where('channel', $channel)->where('mobile', $e164)->lockForUpdate()->first();
            if (!$conv) {
                $v = Voter::withoutGlobalScopes()->where('mobile', substr($e164, -10))->where('deleted', false)->orderBy('id')->first();
                $id = DB::table('conversations')->insertGetId(['channel' => $channel, 'mobile' => $e164, 'voter_id' => $v?->id,
                    'name' => $v?->name ?? ($profileName ? mb_substr($profileName, 0, 120) : null), 'assembly_no' => $v?->assembly_no, 'status' => 'OPEN', 'unread' => 0]);
                $conv = DB::table('conversations')->find($id);
            }
            DB::table('conversation_messages')->insert(['conversation_id' => $conv->id, 'direction' => 'IN', 'body' => $body !== null ? mb_substr($body, 0, 2000) : null,
                'media_type' => $mediaType, 'provider_id' => $providerId, 'at' => now()]);
            DB::table('conversations')->where('id', $conv->id)->update(['unread' => $conv->unread + 1, 'status' => 'OPEN', 'last_in_at' => now(), 'last_at' => now()]);
            if ($body !== null && in_array(mb_strtoupper(trim($body)), self::STOP_WORDS, true)) {
                $this->msg->optOut($e164, null, 'Replied ' . trim($body), 'inbox');
                DB::table('conversation_messages')->insert(['conversation_id' => $conv->id, 'direction' => 'OUT', 'body' => t('inbox.optedOutNote'),
                    'status' => 'NOTE', 'by_user' => 'system', 'at' => now()]);
            }
            return $conv->id;
        });
    }

    /** Free-text replies on WhatsApp are allowed only within 24 hours of the voter's last message. */
    public function windowOpen(object $conv): bool
    {
        return $conv->channel !== 'WHATSAPP' || ($conv->last_in_at && now()->diffInHours($conv->last_in_at, true) < 24);
    }

    /** Sends a reply (free text, or a template when the WhatsApp window is closed). Returns null on success, else an error text. */
    public function reply(object $conv, ?string $text, ?MessageTemplate $tpl, string $user): ?string
    {
        if ($this->msg->isOptedOut($conv->mobile, $conv->channel)) return t('inbox.isOptedOut');
        $v = $conv->voter_id ? Voter::withoutGlobalScopes()->find($conv->voter_id) : null;
        if ($tpl) {
            if ($tpl->channel !== $conv->channel || !$tpl->active) return t('inbox.badTemplate');
            $body = $v ? TemplateRenderer::render($tpl->body, $v) : $tpl->body;
            $params = $v ? TemplateRenderer::params($tpl, $v) : [];
        } else {
            $body = trim((string) $text);
            if ($body === '') return t('inbox.empty');
            if (!$this->windowOpen($conv)) return t('inbox.windowClosed');
            $params = [];
        }
        $r = $this->gateway->send($conv->channel, $conv->mobile, $body, $tpl, $params);
        DB::table('conversation_messages')->insert(['conversation_id' => $conv->id, 'direction' => 'OUT', 'body' => mb_substr($body, 0, 2000),
            'provider_id' => $r->providerId, 'status' => $r->success ? 'SENT' : 'FAILED', 'error' => $r->success ? null : mb_substr((string) $r->error, 0, 300),
            'by_user' => $user, 'at' => now()]);
        DB::table('conversations')->where('id', $conv->id)->update(['last_at' => now(), 'unread' => 0]);
        return $r->success ? null : t('inbox.sendFailed', (string) $r->error);
    }

    /**
     * Receipt from a provider for a message it accepted earlier: DELIVERED, READ or UNDELIVERED.
     * Updates the campaign outbox and inbox replies; a later receipt never moves a message back (READ stays READ).
     */
    public function receipt(string $providerId, string $status, ?\DateTimeInterface $at = null): int
    {
        $status = strtoupper($status);
        $map = ['DELIVERED' => 'DELIVERED', 'READ' => 'READ', 'UNDELIVERED' => 'UNDELIVERED', 'FAILED' => 'UNDELIVERED'];
        if (!isset($map[$status]) || $providerId === '') return 0;
        $status = $map[$status]; $at ??= now();
        $n = 0;
        if (Schema::hasColumn('message_outbox', 'delivery')) {
            foreach (DB::table('message_outbox')->where('provider_id', $providerId)->get(['id', 'delivery']) as $m) {
                if (($m->delivery === 'READ') || ($m->delivery === 'DELIVERED' && $status === 'UNDELIVERED')) continue;
                $upd = ['delivery' => $status];
                if ($status === 'DELIVERED') $upd['delivered_at'] = $at;
                if ($status === 'READ') { $upd['read_at'] = $at; $upd['delivered_at'] = DB::raw('coalesce(delivered_at, ' . DB::getPdo()->quote($at->format('Y-m-d H:i:s')) . ')'); }
                $n += DB::table('message_outbox')->where('id', $m->id)->update($upd);
            }
        }
        foreach (DB::table('conversation_messages')->where('provider_id', $providerId)->where('direction', 'OUT')->get(['id', 'status']) as $m) {
            $new = $status === 'UNDELIVERED' ? 'FAILED' : $status;
            if (($m->status === 'READ') || (self::RANK[$m->status] ?? 0) > (self::RANK[$new] ?? 0)) continue;
            $n += DB::table('conversation_messages')->where('id', $m->id)->update(['status' => $new]);
        }
        return $n;
    }
}
