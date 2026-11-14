package com.vidhi.voters.report;

import com.lowagie.text.Document;
import com.lowagie.text.Element;
import com.lowagie.text.PageSize;
import com.lowagie.text.Paragraph;
import com.lowagie.text.pdf.*;
import com.vidhi.voters.config.Languages;
import com.vidhi.voters.export.PdfFonts;
import org.apache.poi.ss.usermodel.*;
import org.apache.poi.ss.util.CellRangeAddress;
import org.apache.poi.xssf.streaming.SXSSFSheet;
import org.apache.poi.xssf.streaming.SXSSFWorkbook;
import org.springframework.context.MessageSource;
import org.springframework.stereotype.Component;

import java.awt.Color;
import java.io.IOException;
import java.io.OutputStream;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Locale;

/** Excel / PDF export for any {@link Report} (used by Reports and Results). */
@Component
public class TableExporter {

    private final PdfFonts fonts;
    private final MessageSource messages;

    public TableExporter(PdfFonts fonts, MessageSource messages) {
        this.fonts = fonts;
        this.messages = messages;
    }

    public void excel(Report r, Locale locale, OutputStream out) throws IOException {
        try (SXSSFWorkbook wb = new SXSSFWorkbook(200)) {
            SXSSFSheet sh = wb.createSheet(safeSheetName(r.getTitle()));
            sh.trackAllColumnsForAutoSizing();
            if (Languages.of(locale).rtl()) sh.setRightToLeft(true);
            Font bold = wb.createFont();
            bold.setBold(true);
            CellStyle head = wb.createCellStyle();
            head.setFont(bold);
            head.setFillForegroundColor(IndexedColors.GREY_25_PERCENT.getIndex());
            head.setFillPattern(FillPatternType.SOLID_FOREGROUND);
            CellStyle boldStyle = wb.createCellStyle();
            boldStyle.setFont(bold);

            int n = 0;
            Row t = sh.createRow(n++);
            t.createCell(0).setCellValue(r.getTitle());
            t.getCell(0).setCellStyle(boldStyle);
            if (r.getHeaders().size() > 1) sh.addMergedRegion(new CellRangeAddress(0, 0, 0, r.getHeaders().size() - 1));
            if (!r.getSubtitle().isBlank()) sh.createRow(n++).createCell(0).setCellValue(r.getSubtitle());
            n++;
            Row h = sh.createRow(n++);
            for (int i = 0; i < r.getHeaders().size(); i++) {
                h.createCell(i).setCellValue(r.getHeaders().get(i));
                h.getCell(i).setCellStyle(head);
            }
            int headerRow = n - 1;
            for (List<Object> row : r.getRows()) write(sh.createRow(n++), row, null);
            if (r.getTotals() != null) write(sh.createRow(n++), r.getTotals(), boldStyle);
            sh.createFreezePane(0, headerRow + 1);
            for (int i = 0; i < r.getHeaders().size(); i++) {
                try { sh.autoSizeColumn(i); } catch (RuntimeException ignored) { }
                sh.setColumnWidth(i, Math.min(sh.getColumnWidth(i) + 512, 60 * 256));
            }
            wb.write(out);
        }
    }

    private static void write(Row row, List<Object> values, CellStyle style) {
        for (int i = 0; i < values.size(); i++) {
            Object v = values.get(i);
            Cell c = row.createCell(i);
            if (v instanceof Number num) c.setCellValue(num.doubleValue());
            else if (v != null) c.setCellValue(v.toString());
            if (style != null) c.setCellStyle(style);
        }
    }

    public void pdf(Report r, Locale locale, OutputStream out) {
        boolean rtl = Languages.of(locale).rtl();
        boolean wide = r.getHeaders().size() > 7;
        Document doc = new Document(wide ? PageSize.A4.rotate() : PageSize.A4, 25, 25, 25, 30);
        PdfWriter.getInstance(doc, out);
        doc.open();
        Paragraph t = new Paragraph(fonts.phrase(r.getTitle(), 14, true, Color.BLACK));
        t.setAlignment(Element.ALIGN_CENTER);
        doc.add(t);
        String meta = (r.getSubtitle().isBlank() ? "" : r.getSubtitle() + "   |   ")
                + messages.getMessage("pdf.generated", null, locale) + " "
                + LocalDateTime.now().format(DateTimeFormatter.ofPattern("dd-MM-yyyy HH:mm"));
        Paragraph m = new Paragraph(fonts.phrase(meta, 8, false, Color.DARK_GRAY));
        m.setAlignment(Element.ALIGN_CENTER);
        m.setSpacingAfter(8);
        doc.add(m);

        PdfPTable table = new PdfPTable(r.getHeaders().size());
        table.setWidthPercentage(100);
        table.setHeaderRows(1);
        Color headBg = new Color(33, 64, 115);
        for (String s : ordered(new ArrayList<>(r.getHeaders()), rtl)) cell(table, s, true, Color.WHITE, headBg, false, rtl);
        int i = 0;
        for (List<Object> row : r.getRows()) {
            Color bg = i++ % 2 == 1 ? new Color(242, 245, 250) : Color.WHITE;
            for (Object o : ordered(new ArrayList<>(row), rtl)) cell(table, fmt(o), false, Color.BLACK, bg, o instanceof Number, rtl);
        }
        if (r.getTotals() != null)
            for (Object o : ordered(new ArrayList<>(r.getTotals()), rtl)) cell(table, fmt(o), true, Color.BLACK, new Color(225, 230, 240), o instanceof Number, rtl);
        doc.add(table);
        doc.close();
    }

    private static <T> List<T> ordered(List<T> l, boolean rtl) {
        if (rtl) Collections.reverse(l);
        return l;
    }

    private void cell(PdfPTable t, String text, boolean bold, Color fg, Color bg, boolean number, boolean rtl) {
        PdfPCell c = new PdfPCell(fonts.phrase(text, 8, bold, fg));
        c.setBackgroundColor(bg);
        c.setPadding(4);
        c.setPaddingBottom(5);
        c.setBorderColor(new Color(200, 205, 215));
        if (number || PdfFonts.isRtl(text) || (rtl && bold)) c.setHorizontalAlignment(Element.ALIGN_RIGHT);
        t.addCell(c);
    }

    public static String fmt(Object o) {
        if (o == null) return "";
        if (o instanceof Double d) return String.format(Locale.ROOT, "%.2f", d);
        return o.toString();
    }

    private static String safeSheetName(String s) {
        String n = s.replaceAll("[\\\\/?*\\[\\]:]", " ");
        return n.length() > 31 ? n.substring(0, 31) : n;
    }
}
