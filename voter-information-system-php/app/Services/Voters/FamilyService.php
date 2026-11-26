<?php

namespace App\Services\Voters;

use App\Support\Text;

/**
 * Splits the voters of one house into families, using only what the roll prints:
 * a voter is linked to the member of the same house whose name is their father's / husband's / mother's name,
 * and voters who name the same father / husband / mother are linked to each other (brothers and sisters,
 * or a mother and her children when the father is not on the roll). Nothing is guessed beyond that:
 * members with no link form a family of their own.
 */
class FamilyService
{
    /**
     * @param iterable $members voters of one house (objects with id, name, relative_name, relation_type, gender, age, serial_no)
     * @return array<int, array> families, each a list of the same voter objects; the head (oldest man, else oldest member) first
     */
    public function split(iterable $members): array
    {
        $list = array_values(is_array($members) ? $members : iterator_to_array($members));
        $n = count($list);
        if ($n === 0) return [];
        $parent = range(0, $n - 1);
        $find = function (int $i) use (&$parent) { while ($parent[$i] !== $i) { $parent[$i] = $parent[$parent[$i]]; $i = $parent[$i]; } return $i; };
        $union = function (int $a, int $b) use (&$parent, $find) { $a = $find($a); $b = $find($b); if ($a !== $b) $parent[max($a, $b)] = min($a, $b); };

        $names = array_map(fn ($v) => self::tokens($v->name), $list);
        $rels = array_map(fn ($v) => self::tokens($v->relative_name), $list);
        for ($i = 0; $i < $n; $i++) {
            if (!$rels[$i]) continue;
            for ($j = $i + 1; $j < $n; $j++) if ($rels[$j] && self::sameName($rels[$i], $rels[$j])) $union($i, $j);   // same father / husband / mother named
            $want = match (strtoupper((string) ($list[$i]->relation_type ?: 'FATHER'))) { 'FATHER', 'HUSBAND' => 'MALE', 'MOTHER' => 'FEMALE', default => null };
            for ($j = 0; $j < $n; $j++) {
                if ($j === $i || !$names[$j]) continue;
                $g = strtoupper((string) $list[$j]->gender);
                if ($want && $g && $g !== $want) continue;   // a father must be a man, a mother a woman
                if (self::sameName($rels[$i], $names[$j])) $union($i, $j);
            }
        }

        $groups = [];
        for ($i = 0; $i < $n; $i++) $groups[$find($i)][] = $list[$i];
        $out = [];
        foreach ($groups as $g) {
            usort($g, fn ($a, $b) => [self::isMan($b), (int) $b->age] <=> [self::isMan($a), (int) $a->age]);   // head first
            $out[] = $g;
        }
        usort($out, fn ($a, $b) => self::firstSerial($a) <=> self::firstSerial($b));
        return $out;
    }

    /** Number of families in a house (for reports). */
    public function count(iterable $members): int { return count($this->split($members)); }

    /** Name words, lower case, letters and digits only. */
    public static function tokens(?string $s): array
    {
        $s = mb_strtolower((string) Text::nfc($s));
        $s = preg_replace('/[^\p{L}\p{N}\p{M}]+/u', ' ', $s);
        return array_values(array_filter(explode(' ', $s), fn ($w) => $w !== ''));
    }

    /**
     * Same person's name as the roll writes it in two places. The first names must agree; then the shorter name's words
     * must appear, in order, in the longer one: "ANURAG" = "ANURAG BANSAL", "RAMESH ARORA" = "RAMESH KUMAR ARORA".
     * A word matches an initial ("N P SINGH" = "NARINDER PAL SINGH") or, from 5 letters, a one-letter spelling difference ("ASHWANI" = "ASHWINI").
     */
    public static function sameName(array $a, array $b): bool
    {
        if (!$a || !$b) return false;
        if ($a === $b || implode('', $a) === implode('', $b)) return true;   // "RAJKUMAR" = "RAJ KUMAR"
        [$short, $long] = count($a) <= count($b) ? [$a, $b] : [$b, $a];
        if (!self::sameWord($short[0], $long[0])) return false;
        if (count($short) === 1 && mb_strlen($short[0]) === 1) return false;   // a lone initial says too little
        $j = 1;
        for ($i = 1; $i < count($short); $i++) {
            while ($j < count($long) && !self::sameWord($short[$i], $long[$j])) $j++;
            if ($j >= count($long)) return false;
            $j++;
        }
        return true;
    }

    public static function sameWord(string $x, string $y): bool
    {
        if ($x === $y) return true;
        $lx = mb_strlen($x); $ly = mb_strlen($y);
        if ($lx === 1 || $ly === 1) return mb_substr($x, 0, 1) === mb_substr($y, 0, 1);   // initial
        return $lx >= 5 && $ly >= 5 && abs($lx - $ly) <= 1 && levenshtein($x, $y) <= 1;
    }

    private static function isMan($v): int { return strtoupper((string) $v->gender) === 'MALE' ? 1 : 0; }
    private static function firstSerial(array $g): int { return min(array_map(fn ($v) => (int) ($v->serial_no ?? PHP_INT_MAX), $g)); }
}
