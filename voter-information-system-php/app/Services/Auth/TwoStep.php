<?php

namespace App\Services\Auth;

use App\Models\AppUser;
use App\Services\Messaging\Gateway;
use App\Services\Messaging\MessagingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Two-step login: after the password, a 6-digit code from an authenticator app (TOTP, RFC 6238: Google / Microsoft
 * Authenticator) or sent by SMS / WhatsApp through the messaging gateway. Recovery codes for a lost phone.
 * A device can be trusted for OTP_TRUST_DAYS (default 30) so field workers are not asked every time.
 * Policy OTP_REQUIRED: none (each user may switch it on) | admins (users who can manage users) | all.
 */
class TwoStep
{
    public const PENDING = 'vis.pending';
    public const COOKIE = 'vis_trust';
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(private Gateway $gateway) {}

    // ------------------------------------------------------------------ policy

    public function required(AppUser $u, array $permissions): bool
    {
        if ($u->otp_method) return true;
        return match ((string) config('voters.security.otp_required', 'none')) {
            'all' => true,
            'admins' => in_array('USER_ADMIN', $permissions, true),
            default => false,
        };
    }

    /** Methods this installation can offer: app always; SMS / WhatsApp only when a real (not dry-run) provider is set up. */
    public function methods(): array
    {
        $m = ['app'];
        foreach (['sms' => 'SMS', 'whatsapp' => 'WHATSAPP'] as $k => $ch) if ($this->gateway->ready($ch) && !$this->gateway->dryRun($ch)) $m[] = $k;
        return $m;
    }

    // ------------------------------------------------------------------ authenticator app (TOTP)

    public static function newSecret(): string
    {
        $bytes = random_bytes(20); $bits = ''; $out = '';
        foreach (str_split($bytes) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        foreach (str_split($bits, 5) as $chunk) $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        return $out;
    }

    private static function b32decode(string $s): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos(self::B32, $c)), 5, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
        return $out;
    }

    public static function totp(string $secret, int $counter): string
    {
        $h = hash_hmac('sha1', pack('J', $counter), self::b32decode($secret), true);
        $o = ord($h[19]) & 0xf;
        $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Accepts the code of the current 30-second step or one step either side; a code is accepted only once. */
    public function checkTotp(int $userId, string $secret, string $code): bool
    {
        $now = intdiv(time(), 30);
        foreach ([0, -1, 1] as $d) {
            if (hash_equals(self::totp($secret, $now + $d), $code)) {
                $key = "vis.otp.used.$userId";
                if ((int) Cache::get($key, 0) >= $now + $d) return false;   // replay
                Cache::put($key, $now + $d, 180);
                return true;
            }
        }
        return false;
    }

    public static function otpauthUri(string $secret, string $username): string
    {
        $issuer = (string) (config('voters.branding.app_name') ?: config('app.name'));
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $username) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
    }

    public static function secretOf(AppUser $u): ?string
    {
        try { return $u->otp_secret ? Crypt::decryptString($u->otp_secret) : null; } catch (\Throwable $e) { return null; }
    }

    // ------------------------------------------------------------------ SMS / WhatsApp code

    /** Sends a 6-digit code valid for 5 minutes; at most one every 30 seconds. Returns an error key or null. */
    public function sendCode(AppUser $u, string $method): ?string
    {
        $e164 = app(MessagingService::class)->e164($u->mobile);
        if (!$e164) return 'otp.error.noMobile';
        $key = "vis.otp.code.{$u->id}";
        $prev = Cache::get($key);
        if ($prev && ($prev['at'] ?? 0) > time() - 30) return 'otp.error.wait';
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $body = t('otp.smsText', $code, (string) (config('voters.branding.app_name') ?: config('app.name')));
        $res = $this->gateway->send($method === 'whatsapp' ? 'WHATSAPP' : 'SMS', $e164, $body, null, ['code' => $code]);
        if (!$res->success) return 'otp.error.send';
        Cache::put($key, ['hash' => hash('sha256', $code . $u->id), 'exp' => time() + 300, 'tries' => 0, 'at' => time()], 600);
        return null;
    }

    public function checkCode(AppUser $u, string $code): bool
    {
        $key = "vis.otp.code.{$u->id}";
        $c = Cache::get($key);
        if (!$c || $c['exp'] < time() || $c['tries'] >= 5) return false;
        if (hash_equals($c['hash'], hash('sha256', $code . $u->id))) { Cache::forget($key); return true; }
        $c['tries']++;
        Cache::put($key, $c, 600);
        return false;
    }

    // ------------------------------------------------------------------ recovery codes

    /** 8 one-time codes (shown once; only their hashes are kept). */
    public static function newRecovery(AppUser $u): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) $codes[] = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        $u->otp_recovery = json_encode(array_map(fn ($c) => password_hash($c, PASSWORD_BCRYPT), $codes));
        $u->save();
        return $codes;
    }

    public static function useRecovery(AppUser $u, string $code): bool
    {
        $list = json_decode((string) $u->otp_recovery, true) ?: [];
        foreach ($list as $i => $h) {
            if (password_verify(strtoupper(trim($code)), $h)) {
                unset($list[$i]);
                $u->otp_recovery = json_encode(array_values($list));
                $u->save();
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------ verify any code

    public function verify(AppUser $u, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (preg_match('/^\d{6}$/', $code)) {
            if ($u->otp_method === 'app' && ($s = self::secretOf($u))) return $this->checkTotp($u->id, $s, $code);
            if (in_array($u->otp_method, ['sms', 'whatsapp'], true)) return $this->checkCode($u, $code);
            return false;
        }
        return self::useRecovery($u, $code);
    }

    // ------------------------------------------------------------------ trusted device

    private static function sig(AppUser $u, int $exp): string
    {
        return hash_hmac('sha256', $u->id . '|' . $exp . '|' . $u->password_hash . '|' . $u->otp_secret . '|' . $u->otp_method, (string) config('app.key'));
    }

    public static function trustCookie(AppUser $u): \Symfony\Component\HttpFoundation\Cookie
    {
        $days = max(1, (int) config('voters.security.otp_trust_days', 30));
        $exp = time() + $days * 86400;
        return cookie(self::COOKIE . '_' . $u->id, $exp . '.' . self::sig($u, $exp), $days * 1440, null, null, null, true, false, 'lax');
    }

    /** Valid until it expires, or until the password or the two-step setting changes. */
    public static function trusted(AppUser $u): bool
    {
        $v = (string) request()->cookie(self::COOKIE . '_' . $u->id);
        if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $v, $m) || (int) $m[1] < time()) return false;
        return hash_equals(self::sig($u, (int) $m[1]), $m[2]);
    }
}
