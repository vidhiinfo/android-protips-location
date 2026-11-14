package com.vidhi.voters.export;

import com.lowagie.text.*;
import com.lowagie.text.pdf.*;
import com.vidhi.voters.config.Languages;
import com.vidhi.voters.model.Voter;
import org.springframework.context.MessageSource;
import org.springframework.stereotype.Component;

import java.awt.Color;
import java.io.OutputStream;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Locale;

@Component
public class PdfExporter {

    private final PdfFonts fonts;
    private final MessageSource messages;

    public PdfExporter(PdfFonts fonts, MessageSource messages) {
        this.fonts = fonts;
        this.messages = messages;
    }

    public void write(List<Voter> voters, String title, String filterSummary, Locale locale, OutputStream out) {
        boolean rtl = Languages.of(locale).rtl();
        Document doc = new Document(PageSize.A4.rotate(), 20, 20, 25, 30);
        PdfWriter writer = PdfWriter.getInstance(doc, out);
        writer.setPageEvent(new Footer(fonts, t(locale, "pdf.page")));
        doc.open();

        Paragraph tp = new Paragraph(fonts.phrase(title, 14, true, Color.BLACK));
        tp.setAlignment(Element.ALIGN_CENTER);
        doc.add(tp);
        String meta = t(locale, "pdf.generated") + " " + LocalDateTime.now().format(DateTimeFormatter.ofPattern("dd-MM-yyyy HH:mm"))
                + "   |   " + t(locale, "pdf.records") + ": " + voters.size()
                + (filterSummary.isBlank() ? "" : "   |   " + t(locale, "pdf.filters") + ": " + filterSummary);
        Paragraph mp = new Paragraph(fonts.phrase(meta, 8, false, Color.DARK_GRAY));
        mp.setAlignment(Element.ALIGN_CENTER);
        mp.setSpacingAfter(8);
        doc.add(mp);

        List<Columns.Col> cols = new ArrayList<>(Columns.pdf());
        List<String> heads = new ArrayList<>();
        heads.add("#");
        cols.forEach(c -> heads.add(t(locale, c.key())));
        List<Float> widths = new ArrayList<>();
        widths.add(3f);
        cols.forEach(c -> widths.add(c.pdfWidth()));
        // Right-to-left languages: first column on the right. Done by reordering, because the
        // table's own RTL mode would reshape the already-shaped Urdu text a second time.
        if (rtl) { Collections.reverse(heads); Collections.reverse(widths); }

        float[] w = new float[widths.size()];
        for (int i = 0; i < w.length; i++) w[i] = widths.get(i);
        PdfPTable table = new PdfPTable(w);
        table.setWidthPercentage(100);
        table.setHeaderRows(1);   // repeat header on every page

        Color headBg = new Color(33, 64, 115);
        for (String h : heads) addCell(table, h, true, Color.WHITE, headBg, rtl);

        Color zebra = new Color(242, 245, 250);
        int n = 1;
        for (Voter v : voters) {
            Color bg = n % 2 == 0 ? zebra : Color.WHITE;
            List<String> row = new ArrayList<>();
            row.add(String.valueOf(n++));
            for (Columns.Col c : cols) {
                Object val = c.value().apply(v, k -> t(locale, k));
                row.add(val == null ? "" : val.toString());
            }
            if (rtl) Collections.reverse(row);
            for (String s : row) addCell(table, s, false, Color.BLACK, bg, rtl);
        }
        doc.add(table);
        doc.close();
    }

    private void addCell(PdfPTable t, String text, boolean header, Color fg, Color bg, boolean rtlDoc) {
        PdfPCell c = new PdfPCell(fonts.phrase(text, header ? 8 : 7.5f, header, fg));
        c.setBackgroundColor(bg);
        c.setPadding(header ? 4 : 3);
        c.setPaddingBottom(header ? 5 : 4);
        c.setVerticalAlignment(Element.ALIGN_MIDDLE);
        if (!header) c.setBorderColor(new Color(200, 205, 215));
        if (PdfFonts.isRtl(text) || (rtlDoc && header)) c.setHorizontalAlignment(Element.ALIGN_RIGHT);
        t.addCell(c);
    }

    private String t(Locale locale, String key) {
        return messages.getMessage(key, null, key, locale);
    }

    /** "Page X" footer on every page. */
    private static class Footer extends PdfPageEventHelper {
        private final PdfFonts fonts;
        private final String label;

        Footer(PdfFonts fonts, String label) { this.fonts = fonts; this.label = label; }

        @Override
        public void onEndPage(PdfWriter writer, Document document) {
            ColumnText.showTextAligned(writer.getDirectContent(), Element.ALIGN_RIGHT,
                    fonts.phrase(label + " " + writer.getPageNumber(), 8, false, Color.GRAY),
                    document.right(), document.bottom() - 15, 0);
        }
    }
}
