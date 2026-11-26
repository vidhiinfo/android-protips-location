<?php

namespace App\Support;

/**
 * First / middle / last name of a voter and of the father / husband / mother, split from the name as the roll prints it:
 * one word = first name; two words = first + last; three or more = first + middle (all words in between) + last.
 * The database computes the columns itself (generated columns), so they always follow the name, however the data
 * arrives: screen, Excel import, roll update or an SQL file.
 */
class NameParts
{
    /** [column => source column] */
    public const COLUMNS = [
        'first_name' => 'name', 'middle_name' => 'name', 'last_name' => 'name',
        'relative_first_name' => 'relative_name', 'relative_middle_name' => 'relative_name', 'relative_last_name' => 'relative_name',
    ];

    /** Same rule in PHP (for display of unsaved data and for tests). */
    public static function split(?string $name): array
    {
        $w = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        $n = count($w);
        if ($n === 0) return [null, null, null];
        if ($n === 1) return [$w[0], null, null];
        return [$w[0], $n > 2 ? implode(' ', array_slice($w, 1, -1)) : null, $w[$n - 1]];
    }

    /** SQL expression computing $part (first|middle|last) of $col for the given driver. */
    public static function sql(string $col, string $part, string $driver): string
    {
        // the name with surrounding spaces removed and runs of spaces made single
        $n = "trim(replace(replace(replace($col, '    ', ' '), '  ', ' '), '  ', ' '))";
        if ($driver === 'sqlite') {
            $first = "substr($n, 1, instr($n || ' ', ' ') - 1)";
            // last word: rtrim() with every non-space character of the name strips back to the last space
            $last = "substr($n, length(rtrim($n, replace($n, ' ', ''))) + 1)";
            $words = "(length($n) - length(replace($n, ' ', '')))";   // spaces = words - 1
            return match ($part) {
                'first' => "case when $col is null or $n = '' then null else $first end",
                'last' => "case when $col is null or $words < 1 then null else $last end",
                'middle' => "case when $col is null or $words < 2 then null else trim(substr($n, length($first) + 2, length($n) - length($first) - length($last) - 2)) end",
            };
        }
        $first = "substring_index($n, ' ', 1)";
        $last = "substring_index($n, ' ', -1)";
        $words = "(char_length($n) - char_length(replace($n, ' ', '')))";
        return match ($part) {
            'first' => "case when $col is null or $n = '' then null else $first end",
            'last' => "case when $col is null or $words < 1 then null else $last end",
            'middle' => "case when $col is null or $words < 2 then null else trim(substring($n, char_length($first) + 2, char_length($n) - char_length($first) - char_length($last) - 2)) end",
        };
    }

    /** Part (first|middle|last) of a column name like relative_middle_name. */
    public static function part(string $column): string
    {
        return preg_replace('/^(relative_)?(first|middle|last)_name$/', '$2', $column);
    }

    private static ?bool $has = null;
    public static function available(): bool { return self::$has ??= \Illuminate\Support\Facades\Schema::hasColumn('voters', 'first_name'); }
}
