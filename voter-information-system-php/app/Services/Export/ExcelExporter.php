<?php

namespace App\Services\Export;

use App\Models\Voter;
use App\Services\Reports\Report;
use App\Support\Lang;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as SpoutWriter;

class ExcelExporter
{
    /** Temp file inside storage/ (shared hosts often block the system temp folder via open_basedir). */
    public static function tempFile(): string
    {
        $dir = storage_path('app/tmp');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        foreach (glob($dir . '/x*') ?: [] as $old) if (@filemtime($old) < time() - 3600) @unlink($old);
        return $dir . '/x' . bin2hex(random_bytes(8)) . '.xlsx';
    }

    /**
     * Voter list streamed straight to an .xlsx file (constant memory, so a whole assembly of
     * lakhs of voters works on shared hosting). Returns the temp file path; caller deletes it.
     */
    public function votersFile(iterable $voters, string $title): string
    {
        $cols = Columns::all();
        $headers = array_merge([t('col.sno')], array_map(fn ($c) => t($c[0]), $cols));
        $file = self::tempFile();
        $w = new SpoutWriter();
        $w->openToFile($file);
        $sheet = $w->getCurrentSheet();
        $sheet->setName(mb_substr(t('excel.sheet'), 0, 31));
        $sheet->setSheetView((new SheetView())->setRightToLeft(Lang::rtl())->setFreezeRow(4));
        $sheet->setColumnWidth(6, 1);
        foreach ($cols as $i => $c) $sheet->setColumnWidth(max(8.0, min(40.0, $c[2] ? $c[2] * 2.2 : mb_strlen(t($c[0])) + 6)), $i + 2);
        $w->addRow(Row::fromValues([$title], (new Style())->setFontBold()->setFontSize(14)));
        $w->addRow(Row::fromValues([]));
        $w->addRow(Row::fromValues([]));
        $w->addRow(Row::fromValues($headers, (new Style())->setFontBold()->setBackgroundColor('D9D9D9')));
        $n = 0;
        foreach ($voters as $v) {
            $cells = [Cell::fromValue(++$n)];
            foreach ($cols as $c) {
                $val = $c[1]($v);
                $cells[] = $val === null ? Cell::fromValue(null) : (is_int($val) ? Cell::fromValue($val) : new Cell\StringCell((string) $val, null));
            }
            $w->addRow(new Row($cells));
        }
        $sheet->setAutoFilter(new AutoFilter(0, 4, count($headers) - 1, max(4, $n + 4)));
        $w->close();
        return $file;
    }

    /** Voter list as bytes (small lists, tests). */
    public function voters(iterable $voters, string $title): string
    {
        $file = $this->votersFile($voters, $title);
        $data = (string) file_get_contents($file);
        @unlink($file);
        return $data;
    }

    public function report(Report $r): string
    {
        return $this->write($r->title, $r->subtitle, $r->headers, $r->rows, $r->totals, mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $r->title), 0, 31));
    }

    /**
     * Door-to-door survey sheet: voters house by house (alternate shading per house), Caste / Sub Caste drop-downs from the
     * masters, yellow columns to fill in. The column titles are the normal import titles, so Bulk Import reads it back.
     */
    public function surveySheet(iterable $voters, string $title): string
    {
        $cols = [['col.assemblyNo', 'assembly_no'], ['col.wardNo', 'ward_no'], ['col.boothNo', 'booth_no'], ['col.serialNo', 'serial_no'],
            ['col.houseNo', 'house_no'], ['col.epicNo', 'epic_no'], ['col.name', 'name'], ['col.relativeName', 'relative_name'],
            ['col.gender', 'gender'], ['col.age', 'age'], ['col.caste', 'caste'], ['col.subCaste', 'sub_caste'], ['col.politicalStatus', 'political_status'],
            ['col.socialStatus', 'social_status'], ['col.mobile', 'mobile'], ['col.remarks', 'remarks']];
        $widths = [8, 7, 7, 7, 12, 14, 26, 26, 9, 6, 18, 18, 22, 20, 14, 24];
        if (!Voter::showWard()) { array_splice($cols, 1, 1); array_splice($widths, 1, 1); }
        $fill = ['caste', 'sub_caste', 'political_status', 'social_status', 'mobile', 'remarks'];
        $book = new Spreadsheet();
        $sh = $book->getActiveSheet();
        $sh->setTitle(mb_substr(t('survey.sheet'), 0, 31));
        $sh->setRightToLeft(Lang::rtl());
        $last = Coordinate::stringFromColumnIndex(count($cols));
        $sh->setCellValue('A1', $title);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sh->setCellValue('A2', t('survey.howto'));
        $sh->mergeCells("A2:{$last}2");
        $sh->getStyle('A2')->getAlignment()->setWrapText(true);
        $sh->getRowDimension(2)->setRowHeight(32);
        foreach ($cols as $i => [$key]) $sh->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '3', t($key));
        $sh->getStyle("A3:{$last}3")->getFont()->setBold(true);
        $sh->getStyle("A3:{$last}3")->getFill()->setFillType('solid')->getStartColor()->setRGB('D9D9D9');
        $r = 3; $house = null; $shade = false;
        foreach ($voters as $v) {
            $r++;
            $key = $v->booth_no . '|' . mb_strtolower((string) $v->village_ward) . '|' . \App\Support\Text::houseKey($v->house_no);
            if ($key !== $house) { $house = $key; $shade = !$shade; }
            foreach ($cols as $i => [, $f]) {
                $val = $f === 'gender' ? $v->genderLabel() : $v->$f;
                if ($val !== null && $val !== '') $sh->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1) . $r, (string) $val,
                    in_array($f, ['assembly_no', 'ward_no', 'booth_no', 'serial_no', 'age'], true) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            if ($shade) $sh->getStyle("A{$r}:{$last}{$r}")->getFill()->setFillType('solid')->getStartColor()->setRGB('EEF3FB');
        }
        $end = max(4, $r);
        // columns to fill in: yellow; Caste / Sub Caste: drop-down lists from the masters (other values still allowed)
        $tree = \App\Models\Caste::tree();
        $lists = $book->createSheet(); $lists->setTitle(mb_substr(t('survey.lists'), 0, 31));
        $lists->setCellValue('A1', t('col.caste')); $lists->setCellValue('B1', t('col.subCaste'));
        $castes = array_keys($tree); $subs = array_values(array_unique(array_merge([], ...array_values($tree ?: [[]]))));
        foreach ($castes as $i => $c) $lists->setCellValueExplicit('A' . ($i + 2), $c, DataType::TYPE_STRING);
        foreach ($subs as $i => $c) $lists->setCellValueExplicit('B' . ($i + 2), $c, DataType::TYPE_STRING);
        // Political / Social Status: names from their masters (columns C and D of the lists sheet)
        $statusLists = ['political_status' => ['C', array_keys(\App\Support\StatusMasters::options('political_status'))], 'social_status' => ['D', array_keys(\App\Support\StatusMasters::options('social_status'))]];
        foreach ($statusLists as $sf => [$lc, $vals]) {
            $lists->setCellValue($lc . '1', t($sf === 'political_status' ? 'col.politicalStatus' : 'col.socialStatus'));
            foreach ($vals as $i => $c) $lists->setCellValueExplicit($lc . ($i + 2), $c, DataType::TYPE_STRING);
        }
        $listRef = "'" . str_replace("'", "''", $lists->getTitle()) . "'";
        foreach ($cols as $i => [, $f]) {
            if (!in_array($f, $fill, true)) continue;
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sh->getStyle("{$col}3:{$col}{$end}")->getFill()->setFillType('solid')->getStartColor()->setRGB('FFF2CC');
            $range = $f === 'caste' && $castes ? '$A$2:$A$' . (count($castes) + 1) : ($f === 'sub_caste' && $subs ? '$B$2:$B$' . (count($subs) + 1) : null);
            if (isset($statusLists[$f]) && $statusLists[$f][1]) $range = '$' . $statusLists[$f][0] . '$2:$' . $statusLists[$f][0] . '$' . (count($statusLists[$f][1]) + 1);
            if (!$range) continue;
            $dv = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
            $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION)
               ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(false)->setFormula1($listRef . '!' . $range);
            $sh->setDataValidation("{$col}4:{$col}{$end}", $dv);
        }
        $sh->freezePane('A4');
        $sh->setAutoFilter("A3:{$last}{$end}");
        foreach ($widths as $i => $w) $sh->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        $sh->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sh->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(3, 3);
        $book->setActiveSheetIndex(0);
        return $this->save($book);
    }

    /** Workbook with the header row and one sample row, for bulk upload. */
    public function template(): string
    {
        $headers = array_map(fn ($c) => t($c[0]), Columns::importable());
        $sample = [101, 'Sample Nagar', 5, 12, 345, 'ABC1234567', 'Ramesh Kumar', 'रमेश कुमार', t('relation.FATHER'), 'Suresh Kumar', 'सुरेश कुमार',
            t('gender.MALE'), 42, '12-B', 'Main Road', 'मुख्य मार्ग', 'Ward 5', 'Jaipur', '302001', '9876543210', '', '', 'Govt. School Room 1', '', '',
            'M-1024', '15-08-1982', '20-11-2008', 'Saini', '', 'Influencer', 'Supporter', 'Booth Committee', 'PM Awas', 'Ujjwala', '', 'Jaipur', 'Jaipur', 'Ward 5 President', '', '', ''];
        if (!Voter::showWard()) array_splice($sample, 2, 1);   // no Ward No column
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

    /** Report workbook (streamed, so large lists do not run out of memory). */
    private function write(string $title, string $subtitle, array $headers, array $rows, ?array $totals, string $sheetName, array $textKeys = []): string
    {
        $file = self::tempFile();
        $w = new SpoutWriter();
        $w->openToFile($file);
        $sheet = $w->getCurrentSheet();
        $sheet->setName(trim(mb_substr($sheetName, 0, 31)) ?: 'Sheet1');
        $headerRow = $subtitle !== '' ? 4 : 3;
        $sheet->setSheetView((new SheetView())->setRightToLeft(Lang::rtl())->setFreezeRow($headerRow));
        // column widths from the longest value in the first rows
        foreach ($headers as $i => $h) {
            $len = mb_strlen((string) $h);
            foreach (array_slice($rows, 0, 200) as $row) $len = max($len, mb_strlen(Report::fmt(array_values($row)[$i] ?? '')));
            $sheet->setColumnWidth(min(60.0, max(6.0, $len + 2.0)), $i + 1);
        }
        $textCols = [];
        foreach ($textKeys as $k) { $i = array_search(t($k), $headers, true); if ($i !== false) $textCols[$i] = true; }
        $w->addRow(Row::fromValues([$title], (new Style())->setFontBold()->setFontSize(14)));
        if ($subtitle !== '') $w->addRow(Row::fromValues([$subtitle]));
        $w->addRow(Row::fromValues([]));
        $w->addRow(Row::fromValues($headers, (new Style())->setFontBold()->setBackgroundColor('D9D9D9')));
        foreach ($rows as $row) {
            $cells = [];
            foreach (array_values($row) as $i => $val) {
                $cells[] = $val === null ? Cell::fromValue(null)
                    : (!isset($textCols[$i]) && (is_int($val) || is_float($val)) ? Cell::fromValue($val) : new Cell\StringCell((string) $val, null));
            }
            $w->addRow(new Row($cells));
        }
        if ($totals) $w->addRow(Row::fromValues(array_map(fn ($v) => $v === '' ? null : $v, array_values($totals)), (new Style())->setFontBold()));
        $sheet->setAutoFilter(new AutoFilter(0, $headerRow, count($headers) - 1, $headerRow + max(1, count($rows))));
        $w->close();
        $data = (string) file_get_contents($file);
        @unlink($file);
        return $data;
    }

    private function save(Spreadsheet $book): string
    {
        $file = self::tempFile();
        (new Xlsx($book))->save($file);
        $data = file_get_contents($file);
        @unlink($file);
        return $data;
    }
}
