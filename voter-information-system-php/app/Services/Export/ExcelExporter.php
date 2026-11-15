<?php

namespace App\Services\Export;

use App\Services\Reports\Report;
use App\Support\Lang;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelExporter
{
    /** Voter list: title row, header, one row per voter. Mobile numbers stay text. */
    public function voters(iterable $voters, string $title): string
    {
        $cols = Columns::all();
        $headers = array_merge([t('col.sno')], array_map(fn ($c) => t($c[0]), $cols));
        $rows = [];
        $n = 1;
        foreach ($voters as $v) {
            $row = [$n++];
            foreach ($cols as $c) $row[] = $c[1]($v);
            $rows[] = $row;
        }
        return $this->write($title, '', $headers, $rows, null, t('excel.sheet'), ['col.mobile', 'col.altMobile', 'col.pincode', 'col.epicNo']);
    }

    public function report(Report $r): string
    {
        return $this->write($r->title, $r->subtitle, $r->headers, $r->rows, $r->totals, mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $r->title), 0, 31));
    }

    /** Workbook with the header row and one sample row, for bulk upload. */
    public function template(): string
    {
        $headers = array_map(fn ($c) => t($c[0]), Columns::all());
        $sample = [101, 'Sample Nagar', 12, 345, 'ABC1234567', 'Ramesh Kumar', 'रमेश कुमार', t('relation.FATHER'), 'Suresh Kumar', 'सुरेश कुमार',
            t('gender.MALE'), 42, '12-B', 'Main Road', 'मुख्य मार्ग', 'Ward 5', 'Jaipur', '302001', '9876543210', '', '', 'Govt. School Room 1', '', ''];
        $book = new Spreadsheet();
        $sh = $book->getActiveSheet();
        $sh->setTitle(t('excel.sheet'));
        $sh->setRightToLeft(Lang::rtl());
        foreach ($headers as $i => $h) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sh->setCellValue($col . '1', $h);
            $sh->setCellValueExplicit($col . '2', (string) $sample[$i], DataType::TYPE_STRING);
            $sh->getColumnDimension($col)->setWidth(20);
        }
        $sh->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')->getFont()->setBold(true);
        return $this->save($book);
    }

    private function write(string $title, string $subtitle, array $headers, array $rows, ?array $totals, string $sheetName, array $textKeys = []): string
    {
        $book = new Spreadsheet();
        $sh = $book->getActiveSheet();
        $sh->setTitle($sheetName ?: 'Sheet1');
        $sh->setRightToLeft(Lang::rtl());
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $sh->setCellValue('A1', $title);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        if (count($headers) > 1) $sh->mergeCells("A1:{$last}1");
        $r = 2;
        if ($subtitle !== '') $sh->setCellValue('A' . $r++, $subtitle);
        $r++;
        $headerRow = $r;
        foreach ($headers as $i => $h) $sh->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $r, $h);
        $sh->getStyle("A{$r}:{$last}{$r}")->getFont()->setBold(true);
        $sh->getStyle("A{$r}:{$last}{$r}")->getFill()->setFillType('solid')->getStartColor()->setRGB('D9D9D9');
        $textCols = [];
        foreach ($textKeys as $k) { $i = array_search(t($k), $headers, true); if ($i !== false) $textCols[$i] = true; }
        foreach ($rows as $row) {
            $r++;
            foreach (array_values($row) as $i => $val) {
                $cell = Coordinate::stringFromColumnIndex($i + 1) . $r;
                if ($val === null) continue;
                if (isset($textCols[$i]) || !(is_int($val) || is_float($val))) $sh->setCellValueExplicit($cell, (string) $val, DataType::TYPE_STRING);
                else $sh->setCellValue($cell, $val);
            }
        }
        if ($totals) {
            $r++;
            foreach (array_values($totals) as $i => $val) if ($val !== null && $val !== '') $sh->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $r, $val);
            $sh->getStyle("A{$r}:{$last}{$r}")->getFont()->setBold(true);
        }
        $sh->freezePane('A' . ($headerRow + 1));
        $sh->setAutoFilter("A{$headerRow}:{$last}" . max($headerRow, $r));
        foreach (range(1, count($headers)) as $i) $sh->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        return $this->save($book);
    }

    private function save(Spreadsheet $book): string
    {
        $file = tempnam(sys_get_temp_dir(), 'xls');
        (new Xlsx($book))->save($file);
        $data = file_get_contents($file);
        @unlink($file);
        return $data;
    }
}
