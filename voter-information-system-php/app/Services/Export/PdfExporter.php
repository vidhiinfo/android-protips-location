<?php

namespace App\Services\Export;

use App\Services\Reports\Report;
use App\Support\Lang;
use Mpdf\Mpdf;

/**
 * PDF export with mPDF. autoScriptToLang + autoLangToFont pick a font per script (FreeSans/FreeSerif for Devanagari,
 * Bengali, Gurmukhi, Gujarati, Tamil; Pothana for Telugu; XB Riyaz / Lateef for Urdu) and shape conjuncts and joined letters.
 */
class PdfExporter
{
    private function mpdf(bool $landscape): Mpdf
    {
        // big lists: ask for more memory / time where the host allows it (ignored otherwise)
        if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 512) @ini_set('memory_limit', '512M');
        @set_time_limit(300);
        $tmp = storage_path('app/mpdf');
        if (!is_dir($tmp)) @mkdir($tmp, 0775, true);
        $m = new Mpdf([
            'mode' => 'utf-8', 'format' => $landscape ? 'A4-L' : 'A4', 'margin_left' => 8, 'margin_right' => 8, 'margin_top' => 10, 'margin_bottom' => 12,
            'autoScriptToLang' => true, 'autoLangToFont' => true, 'baseScript' => 1, 'autoArabic' => true,
            'default_font' => 'freesans', 'tempDir' => $tmp, 'packTableData' => true, 'directionality' => Lang::rtl() ? 'rtl' : 'ltr',
        ]);
        $m->setFooter('{PAGENO}');
        return $m;
    }

    private static function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

    private function table(array $headers, array $rows, ?array $totals, array $widths = [], bool $continued = false): string
    {
        $h = '<table class="t' . ($continued ? ' c' : '') . '" repeat_header="1"><thead><tr>';
        foreach ($headers as $i => $x) $h .= '<th' . (isset($widths[$i]) ? ' width="' . $widths[$i] . '%"' : '') . '>' . self::e($x) . '</th>';
        $h .= '</tr></thead><tbody>';
        foreach ($rows as $n => $row) {
            $h .= '<tr' . ($n % 2 ? ' class="z"' : '') . '>';
            foreach ($row as $v) $h .= '<td' . (Report::isNumber($v) ? ' class="n"' : '') . '>' . self::e(Report::fmt($v)) . '</td>';
            $h .= '</tr>';
        }
        if ($totals) {
            $h .= '<tr class="tot">';
            foreach ($totals as $v) $h .= '<td' . (Report::isNumber($v) ? ' class="n"' : '') . '>' . self::e(Report::fmt($v)) . '</td>';
            $h .= '</tr>';
        }
        return $h . '</tbody></table>';
    }

    private const CSS = '
        body { font-family: freesans; font-size: 7.5pt; }
        h1 { font-size: 13pt; text-align: center; margin: 0 0 1mm; }
        .meta { text-align: center; color: #555; font-size: 7.5pt; margin-bottom: 3mm; }
        table.t { border-collapse: collapse; width: 100%; }
        .t th { background: #214073; color: #fff; font-weight: bold; padding: 1.5mm; border: 0.2mm solid #214073; text-align: left; }
        .t td { padding: 1.2mm; border: 0.2mm solid #c8cdd7; }
        .t tr.z td { background: #f2f5fa; }
        .t td.n { text-align: right; }
        .t tr.tot td { font-weight: bold; background: #e1e6f0; }
        table.c { margin-top: 0; }';

    public function voters(iterable $voters, string $title, string $filterSummary): string
    {
        $cols = Columns::pdf();
        $headers = array_merge(['#'], array_map(fn ($c) => t($c[0]), $cols));
        $sum = array_sum(array_column($cols, 2)) + 3;
        $widths = array_merge([round(300 / $sum, 1)], array_map(fn ($c) => round(100 * $c[2] / $sum, 1), $cols));
        $rows = [];
        $n = 1;
        foreach ($voters as $v) {
            $row = [$n++];
            foreach ($cols as $c) { $val = $c[1]($v); $row[] = $val === null ? '' : (is_int($val) ? $val : (string) $val); }
            $rows[] = $row;
        }
        $meta = t('pdf.generated') . ' ' . now()->format('d-m-Y H:i') . '   |   ' . t('pdf.records') . ': ' . count($rows)
            . ($filterSummary !== '' ? '   |   ' . t('pdf.filters') . ': ' . $filterSummary : '');
        $m = $this->mpdf(true);
        $this->write($m, '<h1>' . self::e($title) . '</h1><div class="meta">' . self::e($meta) . '</div>', $headers, $rows, null, $widths);
        return $m->Output('', 'S');
    }

    public function report(Report $r): string
    {
        $meta = ($r->subtitle !== '' ? $r->subtitle . '   |   ' : '') . t('pdf.generated') . ' ' . now()->format('d-m-Y H:i');
        $m = $this->mpdf(count($r->headers) > 7);
        $this->write($m, '<h1>' . self::e($r->title) . '</h1><div class="meta">' . self::e($meta) . '</div>', $r->headers, $r->rows, $r->totals);
        return $m->Output('', 'S');
    }

    /** mPDF lays out a whole table in memory: write big lists as consecutive 150-row tables (same columns, header repeated on each page). */
    private function write(Mpdf $m, string $top, array $headers, array $rows, ?array $totals, array $widths = []): void
    {
        $m->WriteHTML(self::CSS, \Mpdf\HTMLParserMode::HEADER_CSS);
        $m->WriteHTML($top);
        $chunks = array_chunk($rows, 150, true) ?: [[]];
        foreach ($chunks as $i => $chunk) {
            $m->WriteHTML($this->table($headers, $chunk, $i === count($chunks) - 1 ? $totals : null, $widths, $i > 0));
        }
    }
}
