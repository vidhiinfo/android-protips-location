<?php

namespace App\Services\Export;

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

    /** Workbook with the header row and one sample row, for bulk upload. */
    public function template(): string
    {
        $headers = array_map(fn ($c) => t($c[0]), Columns::all());
        $sample = [101, 'Sample Nagar', 5, 12, 345, 'ABC1234567', 'Ramesh Kumar', 'रमेश कुमार', t('relation.FATHER'), 'Suresh Kumar', 'सुरेश कुमार',
            t('gender.MALE'), 42, '12-B', 'Main Road', 'मुख्य मार्ग', 'Ward 5', 'Jaipur', '302001', '9876543210', '', '', 'Govt. School Room 1', '', '',
            'M-1024', '15-08-1982', '20-11-2008', 'Saini', 'Influencer', 'Supporter', 'Booth Committee', 'PM Awas', 'Ujjwala', '', 'Jaipur', 'Jaipur', 'Ward 5 President', '', '', ''];
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
