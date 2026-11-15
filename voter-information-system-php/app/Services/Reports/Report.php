<?php

namespace App\Services\Reports;

/** A tabular report, already translated; rendered by reports/view and exported to Excel / PDF. */
class Report
{
    public const DISPLAY_LIMIT = 1000;
    /** Voter-list reports stop here (memory on shared hosting); narrow by booth for more. */
    public const MAX_ROWS = 50000;
    public bool $capped = false;
    /** Optional voter-list filters per cell: [row index => [column index => query params]]; 'totals' for the totals row. */
    public array $links = [];
    public string $subtitle = '';
    public array $rows = [];
    public ?array $totals = null;

    public function __construct(public string $title, public array $headers) {}

    public function subtitle(?string $s): self { $this->subtitle = (string) $s; return $this; }
    public function add(array $row, array $links = []): void
    {
        $this->rows[] = $row;
        if ($links) $this->links[count($this->rows) - 1] = $links;
    }

    /** Voter list URL for a cell, or null. */
    public function link($row, int $col): ?string
    {
        $q = $this->links[$row][$col] ?? null;
        return $q === null ? null : url('/voters') . '?' . http_build_query(array_filter($q, fn ($v) => $v !== null && $v !== ''));
    }
    public function displayRows(): array { return array_slice($this->rows, 0, self::DISPLAY_LIMIT); }
    public function truncated(): bool { return count($this->rows) > self::DISPLAY_LIMIT; }

    public static function fmt($v): string
    {
        if ($v === null) return '';
        if (is_float($v)) return number_format($v, 2, '.', '');
        return (string) $v;
    }

    public static function isNumber($v): bool { return is_int($v) || is_float($v); }
}
