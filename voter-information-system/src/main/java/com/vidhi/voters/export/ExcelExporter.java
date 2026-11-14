package com.vidhi.voters.export;

import com.vidhi.voters.model.Voter;
import org.apache.poi.ss.usermodel.*;
import org.apache.poi.ss.util.CellRangeAddress;
import org.apache.poi.xssf.streaming.SXSSFSheet;
import org.apache.poi.xssf.streaming.SXSSFWorkbook;
import org.springframework.stereotype.Component;

import java.io.IOException;
import java.io.OutputStream;
import java.util.List;

@Component
public class ExcelExporter {

    public void write(List<Voter> voters, String title, OutputStream out) throws IOException {
        // Streaming workbook: keeps only 200 rows in memory, so large rolls export fine.
        try (SXSSFWorkbook wb = new SXSSFWorkbook(200)) {
            SXSSFSheet sheet = wb.createSheet("Voters");
            sheet.trackAllColumnsForAutoSizing();

            Font bold = wb.createFont();
            bold.setBold(true);
            CellStyle titleStyle = wb.createCellStyle();
            Font big = wb.createFont();
            big.setBold(true);
            big.setFontHeightInPoints((short) 14);
            titleStyle.setFont(big);

            CellStyle head = wb.createCellStyle();
            head.setFont(bold);
            head.setFillForegroundColor(IndexedColors.GREY_25_PERCENT.getIndex());
            head.setFillPattern(FillPatternType.SOLID_FOREGROUND);
            head.setBorderBottom(BorderStyle.THIN);

            List<Columns.Col> cols = Columns.ALL;
            int r = 0;
            Row t = sheet.createRow(r++);
            Cell tc = t.createCell(0);
            tc.setCellValue(title);
            tc.setCellStyle(titleStyle);
            sheet.addMergedRegion(new CellRangeAddress(0, 0, 0, cols.size() - 1));
            r++;

            Row h = sheet.createRow(r++);
            h.createCell(0).setCellValue("S.No.");
            h.getCell(0).setCellStyle(head);
            for (int i = 0; i < cols.size(); i++) {
                Cell c = h.createCell(i + 1);
                c.setCellValue(cols.get(i).header());
                c.setCellStyle(head);
            }
            int headerRow = r - 1;

            int n = 1;
            for (Voter v : voters) {
                Row row = sheet.createRow(r++);
                row.createCell(0).setCellValue(n++);
                for (int i = 0; i < cols.size(); i++) {
                    Object val = cols.get(i).value().apply(v);
                    Cell c = row.createCell(i + 1);
                    if (val instanceof Number num) c.setCellValue(num.doubleValue());
                    else if (val != null) c.setCellValue(val.toString());   // mobile stays text: no 9.87E+09
                }
            }

            sheet.createFreezePane(2, headerRow + 1);
            sheet.setAutoFilter(new CellRangeAddress(headerRow, Math.max(headerRow, r - 1), 0, cols.size()));
            for (int i = 0; i <= cols.size(); i++) {
                sheet.autoSizeColumn(i);
                sheet.setColumnWidth(i, Math.min(sheet.getColumnWidth(i) + 512, 60 * 256));
            }
            wb.write(out);
        }
    }

    /** Empty workbook with just the header row, for bulk upload. */
    public void writeTemplate(OutputStream out) throws IOException {
        try (SXSSFWorkbook wb = new SXSSFWorkbook()) {
            SXSSFSheet sheet = wb.createSheet("Voters");
            Row h = sheet.createRow(0);
            for (int i = 0; i < Columns.ALL.size(); i++) {
                h.createCell(i).setCellValue(Columns.ALL.get(i).header());
                sheet.setColumnWidth(i, 18 * 256);
            }
            Row ex = sheet.createRow(1);
            Object[] sample = {101, "Sample Nagar", 12, 345, "ABC1234567", "Ramesh Kumar", "Father", "Suresh Kumar",
                    "Male", 42, "12-B", "Main Road", "Ward 5", "Jaipur", "302001", "9876543210", "", "", "Govt. School Room 1", "", ""};
            for (int i = 0; i < sample.length; i++) ex.createCell(i).setCellValue(String.valueOf(sample[i]));
            wb.write(out);
        }
    }
}
