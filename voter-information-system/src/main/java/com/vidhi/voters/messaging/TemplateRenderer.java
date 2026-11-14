package com.vidhi.voters.messaging;

import com.vidhi.voters.model.Voter;

import java.util.*;
import java.util.function.Function;

/** Fills {placeholders} in a template body with a voter's details. */
public final class TemplateRenderer {

    /** Supported placeholders, in the order shown to template authors. */
    public static final Map<String, Function<Voter, Object>> PLACEHOLDERS = new LinkedHashMap<>();
    static {
        PLACEHOLDERS.put("name", Voter::getName);
        PLACEHOLDERS.put("nameLocal", v -> v.getNameLocal() != null ? v.getNameLocal() : v.getName());
        PLACEHOLDERS.put("epicNo", Voter::getEpicNo);
        PLACEHOLDERS.put("assemblyNo", Voter::getAssemblyNo);
        PLACEHOLDERS.put("assemblyName", Voter::getAssemblyName);
        PLACEHOLDERS.put("boothNo", Voter::getBoothNo);
        PLACEHOLDERS.put("boothName", Voter::getBoothName);
        PLACEHOLDERS.put("serialNo", Voter::getSerialNo);
        PLACEHOLDERS.put("houseNo", Voter::getHouseNo);
        PLACEHOLDERS.put("villageWard", Voter::getVillageWard);
    }

    /** Separator for stored parameter values (ASCII unit separator, never typed by users). */
    public static final String SEP = "\u001F";

    private TemplateRenderer() {}

    private static String value(String key, Voter v) {
        Function<Voter, Object> f = PLACEHOLDERS.get(key);
        Object o = f == null ? null : f.apply(v);
        return o == null ? "" : o.toString();
    }

    public static String render(String body, Voter v) {
        String out = body;
        for (String key : PLACEHOLDERS.keySet()) out = out.replace("{" + key + "}", value(key, v));
        return out;
    }

    /** Values of the template's provider variables (template.params order). */
    public static List<String> params(MessageTemplate t, Voter v) {
        if (t.getParams() == null || t.getParams().isBlank()) return List.of();
        List<String> out = new ArrayList<>();
        for (String p : t.getParams().split(",")) out.add(value(p.trim(), v));
        return out;
    }

    /** Placeholders used in a body or params list that do not exist. */
    public static List<String> unknownPlaceholders(String body, String params) {
        List<String> bad = new ArrayList<>();
        java.util.regex.Matcher m = java.util.regex.Pattern.compile("\\{([A-Za-z]+)}").matcher(body == null ? "" : body);
        while (m.find()) if (!PLACEHOLDERS.containsKey(m.group(1))) bad.add(m.group(1));
        if (params != null && !params.isBlank())
            for (String p : params.split(",")) if (!PLACEHOLDERS.containsKey(p.trim())) bad.add(p.trim());
        return bad;
    }

    // ---------------------------------------------------------------- SMS length
    private static final String GSM7 =
            "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    private static final String GSM7_EXT = "^{}\\[~]|€";

    /** True when the text fits the 7-bit GSM alphabet; Hindi, Urdu, Tamil etc. need UCS-2 (Unicode) SMS. */
    public static boolean isGsm7(String s) {
        for (char c : s.toCharArray()) if (GSM7.indexOf(c) < 0 && GSM7_EXT.indexOf(c) < 0) return false;
        return true;
    }

    /** Number of SMS parts the text is billed as: GSM-7 160 / 153 per part, Unicode 70 / 67 per part. */
    public static int smsSegments(String s) {
        if (s == null || s.isEmpty()) return 1;
        if (isGsm7(s)) {
            int len = 0;
            for (char c : s.toCharArray()) len += GSM7_EXT.indexOf(c) >= 0 ? 2 : 1;
            return len <= 160 ? 1 : (int) Math.ceil(len / 153.0);
        }
        int len = s.codePointCount(0, s.length()) + (int) s.codePoints().filter(c -> c > 0xFFFF).count();
        return len <= 70 ? 1 : (int) Math.ceil(len / 67.0);
    }
}
