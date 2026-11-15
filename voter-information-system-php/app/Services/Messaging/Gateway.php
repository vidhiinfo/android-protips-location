<?php

namespace App\Services\Messaging;

use App\Models\MessageTemplate;
use App\Services\Messaging\Providers\{HttpGatewaySender, LogSender, MetaWhatsAppSender, SendResult, Sender, TwilioSender};

class Gateway
{
    private function senders(): array { return [new LogSender(), new TwilioSender(), new MetaWhatsAppSender(), new HttpGatewaySender()]; }

    public function provider(string $channel): string
    {
        return $channel === 'SMS' ? config('voters.messaging.sms_provider') : config('voters.messaging.whatsapp_provider');
    }

    public function sender(string $channel): ?Sender
    {
        foreach ($this->senders() as $s) if ($s->id() === strtolower($this->provider($channel)) && $s->supports($channel)) return $s;
        return null;
    }

    public function dryRun(string $channel): bool { return strtolower($this->provider($channel)) === 'log'; }
    public function ready(string $channel): bool { return (bool) $this->sender($channel)?->configured($channel); }

    public function send(string $channel, string $e164, string $body, ?MessageTemplate $t, array $params): SendResult
    {
        $s = $this->sender($channel);
        if (!$s) return SendResult::fail("No provider '{$this->provider($channel)}' for $channel", false);
        if (!$s->configured($channel)) return SendResult::fail("Provider '{$s->id()}' is not configured for $channel", false);
        return $s->send($channel, $e164, $body, $t, $params);
    }
}
