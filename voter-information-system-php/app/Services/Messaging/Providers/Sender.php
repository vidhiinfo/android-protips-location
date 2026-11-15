<?php

namespace App\Services\Messaging\Providers;

use App\Models\MessageTemplate;

interface Sender
{
    public function id(): string;
    public function supports(string $channel): bool;
    public function configured(string $channel): bool;
    /** @param string $e164 e.g. +919876543210  @param array $params values for the provider template variables */
    public function send(string $channel, string $e164, string $body, ?MessageTemplate $t, array $params): SendResult;
}
