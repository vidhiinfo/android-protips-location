package com.vidhi.voters.export;

import com.lowagie.text.*;
import com.lowagie.text.pdf.*;
import com.vidhi.voters.model.Voter;
import org.springframework.stereotype.Component;

import java.awt.Color;
import java.io.OutputStream;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;

@Component
public class PdfExporter {

    public void write(List<Voter> voters, String title, String filterSummary, OutputStream out) {
        Document doc = new Document(PageSize.A4.rotate(), 20, 20, 25, 30);
        PdfWriter writer = PdfWriter.getInstance(doc, out);
        writer.setPageEvent(new Footer());
        doc.open();

        Font titleFont = FontFactory.getFont(FontFactory.HELVETICA_BOLD, 14);
        Font small = FontFactory.getFont(FontFactory.HELVETICA, 8, Color.DARK_GRAY);
        Font head = FontFactory.getFont(FontFactory.HELVETICA_BOLD, 8, Color.WHITE);
        Font cell = FontFactory.getFont(FontFactory.HELVETICA, 7.5f);

        Paragraph t = new Paragraph(title, titleFont);
        t.setAlignment(Element.ALIGN_CENTER);
        doc.add(t);
        Paragraph meta = new Paragraph("Generated " + LocalDateTime.now().format(DateTimeFormatter.ofPattern("dd-MM-yyyy HH:mm"))
                + "   |   Records: " + voters.size()
                + (filterSummary.isBlank() ? "" : "   |   Filters: " + filterSummary), small);
        meta.setAlignment(Element.ALIGN_CENTER);
        meta.setSpacingAfter(8);
        doc.add(meta);

        List<Columns.Col> cols = Columns.pdf();
        float[] widths = new float[cols.size() + 1];
        widths[0] = 3;
        for (int i = 0; i < cols.size(); i++) widths[i + 1] = cols.get(i).pdfWidth();

        PdfPTable table = new PdfPTable(widths);
        table.setWidthPercentage(100);
        table.setHeaderRows(1);   // repeat header on every page

        Color headBg = new Color(33, 64, 115);
        addHeader(table, "#", head, headBg);
        for (Columns.Col c : cols) addHeader(table, c.header(), head, headBg);

        Color zebra = new Color(242, 245, 250);
        int n = 1;
        for (Voter v : voters) {
            Color bg = n % 2 == 0 ? zebra : Color.WHITE;
            addCell(table, String.valueOf(n++), cell, bg);
            for (Columns.Col c : cols) {
                Object val = c.value().apply(v);
                addCell(table, val == null ? "" : val.toString(), cell, bg);
            }
        }
        doc.add(table);
        doc.close();
    }

    private static void addHeader(PdfPTable t, String text, Font f, Color bg) {
        PdfPCell c = new PdfPCell(new Phrase(text, f));
        c.setBackgroundColor(bg);
        c.setPadding(4);
        c.setVerticalAlignment(Element.ALIGN_MIDDLE);
        t.addCell(c);
    }

    private static void addCell(PdfPTable t, String text, Font f, Color bg) {
        PdfPCell c = new PdfPCell(new Phrase(text, f));
        c.setBackgroundColor(bg);
        c.setPadding(3);
        c.setBorderColor(new Color(200, 205, 215));
        t.addCell(c);
    }

    /** "Page X" footer on every page. */
    private static class Footer extends PdfPageEventHelper {
        private final Font f = FontFactory.getFont(FontFactory.HELVETICA, 8, Color.GRAY);

        @Override
        public void onEndPage(PdfWriter writer, Document document) {
            ColumnText.showTextAligned(writer.getDirectContent(), Element.ALIGN_RIGHT,
                    new Phrase("Page " + writer.getPageNumber(), f),
                    document.right(), document.bottom() - 15, 0);
        }
    }
}
