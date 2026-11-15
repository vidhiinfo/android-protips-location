<?php

namespace App\Services\Messaging;

use App\Models\MessageTemplate;
use App\Models\Voter;

class TemplateRenderer
{
    public const SEP = "\x1F";

    public static function placeholders(): array
    {
        return [
            'name' => fn (Voter $v) => $v->name,
            'nameLocal' => fn (Voter $v) => $v->name_local ?: $v->name,
            'epicNo' => fn (Voter $v) => $v->epic_no,
            'assemblyNo' => fn (Voter $v) => $v->assembly_no,
            'assemblyName' => fn (Voter $v) => $v->assembly_name,
            'boothNo' => fn (Voter $v) => $v->booth_no,
            'boothName' => fn (Voter $v) => $v->booth_name,
            'serialNo' => fn (Voter $v) => $v->serial_no,
            'houseNo' => fn (Voter $v) => $v->house_no,
            'villageWard' => fn (Voter $v) => $v->village_ward,
        ];
    }

    private static function value(string $key, Voter $v): string
    {
        $f = self::placeholders()[$key] ?? null;
        return $f ? (string) $f($v) : '';
    }

    public static function render(string $body, Voter $v): string
    {
        foreach (array_keys(self::placeholders()) as $k) $body = str_replace('{' . $k . '}', self::value($k, $v), $body);
        return $body;
    }

    public static function params(MessageTemplate $t, Voter $v): array
    {
        if (!$t->params) return [];
        return array_map(fn ($p) => self::value(trim($p), $v), explode(',', $t->params));
    }

    public static function unknownPlaceholders(?string $body, ?string $params): array
    {
        preg_match_all('/\{([A-Za-z]+)}/', (string) $body, $m);
        $bad = array_values(array_diff($m[1], array_keys(self::placeholders())));
        if ($params) foreach (explode(',', $params) as $p) if (!array_key_exists(trim($p), self::placeholders())) $bad[] = trim($p);
        return $bad;
    }

    private const GSM7 = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    private const GSM7_EXT = '^{}\\[~]|€';

    public static function isGsm7(string $s): bool
    {
        foreach (mb_str_split($s) as $c) if (mb_strpos(self::GSM7, $c) === false && mb_strpos(self::GSM7_EXT, $c) === false) return false;
        return true;
    }

    /** SMS parts billed: GSM-7 160 / 153 per part, Unicode (Hindi, Urdu ...) 70 / 67 per part. */
    public static function smsSegments(string $s): int
    {
        if ($s === '') return 1;
        if (self::isGsm7($s)) {
            $len = 0;
            foreach (mb_str_split($s) as $c) $len += mb_strpos(self::GSM7_EXT, $c) !== false ? 2 : 1;
            return $len <= 160 ? 1 : (int) ceil($len / 153);
        }
        $len = 0;
        foreach (mb_str_split($s) as $c) $len += mb_ord($c) > 0xFFFF ? 2 : 1;
        return $len <= 70 ? 1 : (int) ceil($len / 67);
    }
}
