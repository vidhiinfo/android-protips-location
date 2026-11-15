<?php

namespace App\Services\Messaging\Providers;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Dry run (default): writes the message to the log and reports success. Nothing is sent. */
class LogSender implements Sender
{
    public function id(): string { return 'log'; }
    public function supports(string $channel): bool { return true; }
    public function configured(string $channel): bool { return true; }

    public function send(string $channel, string $e164, string $body, ?MessageTemplate $t, array $params): SendResult
    {
        Log::info("[DRY-RUN $channel] to " . preg_replace('/\d(?=\d{4})/', '*', $e164) . ': ' . $body);
        return SendResult::ok('dry-run-' . Str::lower(Str::random(8)));
    }
}
