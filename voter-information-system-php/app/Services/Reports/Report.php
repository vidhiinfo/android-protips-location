<?php

namespace App\Services\Reports;

/** A tabular report, already translated; rendered by reports/view and exported to Excel / PDF. */
class Report
{
    public const DISPLAY_LIMIT = 1000;
    public string $subtitle = '';
    public array $rows = [];
    public ?array $totals = null;

    public function __construct(public string $title, public array $headers) {}

    public function subtitle(?string $s): self { $this->subtitle = (string) $s; return $this; }
    public function add(array $row): void { $this->rows[] = $row; }
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
