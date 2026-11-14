package com.vidhi.voters.export;

import com.lowagie.text.Chunk;
import com.lowagie.text.Font;
import com.lowagie.text.Phrase;
import com.lowagie.text.pdf.BaseFont;
import com.lowagie.text.pdf.LayoutProcessor;
import jakarta.annotation.PostConstruct;
import org.springframework.core.io.ClassPathResource;
import org.springframework.stereotype.Component;

import java.awt.Color;
import java.io.InputStream;
import java.lang.Character.UnicodeScript;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.util.EnumMap;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Embedded Unicode fonts for the PDF export (Google Noto, SIL Open Font License, in static/fonts).
 *
 * Text is split into runs by script (Latin, Devanagari, Bengali, Urdu/Arabic, ...) and each run is
 * drawn with the matching Noto font. OpenPDF's LayoutProcessor uses Java's text shaping engine,
 * so Indic conjuncts and matras and joined, right-to-left Urdu letters come out correctly.
 */
@Component
public class PdfFonts {

    /** Font file base names per script. Latin / anything else uses NotoSans. */
    private static final Map<UnicodeScript, String> FAMILY = new EnumMap<>(Map.of(
            UnicodeScript.DEVANAGARI, "NotoSansDevanagari",   // Hindi, Marathi
            UnicodeScript.BENGALI, "NotoSansBengali",         // Bengali, Assamese
            UnicodeScript.GUJARATI, "NotoSansGujarati",
            UnicodeScript.GURMUKHI, "NotoSansGurmukhi",       // Punjabi
            UnicodeScript.TAMIL, "NotoSansTamil",
            UnicodeScript.TELUGU, "NotoSansTelugu",
            UnicodeScript.ARABIC, "NotoNaskhArabic"));        // Urdu
    private static final String LATIN = "NotoSans";

    private final Map<String, BaseFont> baseFonts = new ConcurrentHashMap<>();
    private final Map<String, Font> fontCache = new ConcurrentHashMap<>();

    @PostConstruct
    void load() throws Exception {
        if (!LayoutProcessor.isEnabled()) LayoutProcessor.enableKernLiga();   // JVM-wide switch, enable once
        // LayoutProcessor needs real files, so copy the fonts out of the jar once
        Path dir = Files.createTempDirectory("vis-fonts");
        dir.toFile().deleteOnExit();
        for (String family : new java.util.HashSet<>(FAMILY.values()) {{ add(LATIN); }}) {
            for (String weight : new String[]{"Regular", "Bold"}) {
                String file = family + "-" + weight + ".ttf";
                Path target = dir.resolve(file);
                try (InputStream in = new ClassPathResource("static/fonts/" + file).getInputStream()) {
                    Files.copy(in, target, StandardCopyOption.REPLACE_EXISTING);
                }
                target.toFile().deleteOnExit();
                BaseFont bf = BaseFont.createFont(target.toString(), BaseFont.IDENTITY_H, BaseFont.EMBEDDED);
                LayoutProcessor.loadFont(bf, target.toString());
                baseFonts.put(family + "-" + weight, bf);
            }
        }
    }

    /** Builds a phrase, switching font at every change of script. */
    public Phrase phrase(String text, float size, boolean bold, Color color) {
        Phrase p = new Phrase();
        if (text == null || text.isEmpty()) {
            p.add(new Chunk("", font(LATIN, size, bold, color, false)));
            return p;
        }
        String weight = bold ? "Bold" : "Regular";
        StringBuilder run = new StringBuilder();
        String runFamily = null;

        int i = 0;
        while (i < text.length()) {
            int cp = text.codePointAt(i);
            UnicodeScript sc = UnicodeScript.of(cp);
            String fam;
            if (sc == UnicodeScript.COMMON || sc == UnicodeScript.INHERITED) {
                // spaces, digits, punctuation, danda: stay in the current run if its font has the glyph
                fam = runFamily != null && baseFonts.get(runFamily + "-" + weight).charExists(cp) ? runFamily : LATIN;
                if (runFamily != null && !fam.equals(runFamily) && sc == UnicodeScript.INHERITED) fam = runFamily;
            } else {
                fam = FAMILY.getOrDefault(sc, LATIN);
            }
            if (runFamily != null && !fam.equals(runFamily)) {
                p.add(new Chunk(run.toString(), font(runFamily, size, bold, color, runFamily.equals("NotoNaskhArabic"))));
                run.setLength(0);
            }
            runFamily = fam;
            run.appendCodePoint(cp);
            i += Character.charCount(cp);
        }
        p.add(new Chunk(run.toString(), font(runFamily, size, bold, color, runFamily.equals("NotoNaskhArabic"))));
        return p;
    }

    /** True when the text is mostly right-to-left script (cell should be right-aligned). */
    public static boolean isRtl(String text) {
        if (text == null) return false;
        long rtl = text.codePoints().filter(c -> UnicodeScript.of(c) == UnicodeScript.ARABIC).count();
        long letters = text.codePoints().filter(Character::isLetter).count();
        return letters > 0 && rtl * 2 >= letters;
    }

    private Font font(String family, float size, boolean bold, Color color, boolean rtl) {
        String key = family + bold + size + color.getRGB();
        return fontCache.computeIfAbsent(key, k -> {
            Font f = new Font(baseFonts.get(family + "-" + (bold ? "Bold" : "Regular")), size, Font.NORMAL, color);
            if (rtl) LayoutProcessor.setRunDirectionRtl(f);
            return f;
        });
    }
}
