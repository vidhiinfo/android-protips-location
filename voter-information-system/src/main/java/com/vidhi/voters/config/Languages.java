package com.vidhi.voters.config;

import java.util.List;
import java.util.Locale;

/**
 * Languages offered in the language switcher.
 * To add one: add an entry here, create messages_xx.properties and (for a new script) add its font in PdfFonts.
 */
public final class Languages {

    public record Lang(String code, String nativeName, String englishName, boolean rtl) {
        public Locale locale() { return Locale.forLanguageTag(code); }
    }

    public static final List<Lang> ALL = List.of(
            new Lang("en", "English", "English", false),
            new Lang("hi", "हिन्दी", "Hindi", false),
            new Lang("bn", "বাংলা", "Bengali", false),
            new Lang("ur", "اردو", "Urdu", true),
            new Lang("mr", "मराठी", "Marathi", false),
            new Lang("gu", "ગુજરાતી", "Gujarati", false),
            new Lang("pa", "ਪੰਜਾਬੀ", "Punjabi", false),
            new Lang("ta", "தமிழ்", "Tamil", false),
            new Lang("te", "తెలుగు", "Telugu", false));

    public static final Lang DEFAULT = ALL.get(0);

    /** Supported language for a locale, or English. */
    public static Lang of(Locale locale) {
        if (locale == null) return DEFAULT;
        return ALL.stream().filter(l -> l.code().equals(locale.getLanguage())).findFirst().orElse(DEFAULT);
    }

    private Languages() {}
}
