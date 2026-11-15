<?php

namespace App\Services\Messaging\Providers;

class SendResult
{
    public function __construct(public bool $success, public ?string $providerId = null, public ?string $error = null, public bool $retryable = false) {}
    public static function ok(?string $id): self { return new self(true, $id); }
    public static function fail(string $error, bool $retryable): self { return new self(false, null, $error, $retryable); }

    public static function fromResponse($resp, ?string $id): self
    {
        $s = $resp->status();
        if ($s >= 200 && $s < 300) return self::ok($id ?: "HTTP $s");
        return self::fail("HTTP $s: " . mb_substr(preg_replace('/\s+/', ' ', (string) $resp->body()), 0, 200), $s === 429 || $s >= 500);
    }
}
