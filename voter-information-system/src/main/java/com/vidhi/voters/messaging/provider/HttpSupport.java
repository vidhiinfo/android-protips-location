package com.vidhi.voters.messaging.provider;

import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.Map;
import java.util.StringJoiner;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/** Small helpers shared by the HTTP providers (java.net.http, no SDKs). */
final class HttpSupport {
    static final HttpClient CLIENT = HttpClient.newBuilder().connectTimeout(Duration.ofSeconds(10)).build();
    static final Duration TIMEOUT = Duration.ofSeconds(20);

    private HttpSupport() {}

    static String enc(String s) { return URLEncoder.encode(s == null ? "" : s, StandardCharsets.UTF_8); }

    static String form(Map<String, String> m) {
        StringJoiner j = new StringJoiner("&");
        m.forEach((k, v) -> j.add(enc(k) + "=" + enc(v)));
        return j.toString();
    }

    static String json(String s) {
        if (s == null) return "null";
        StringBuilder b = new StringBuilder("\"");
        for (char c : s.toCharArray()) {
            switch (c) {
                case '"' -> b.append("\\\"");
                case '\\' -> b.append("\\\\");
                case '\n' -> b.append("\\n");
                case '\r' -> b.append("\\r");
                case '\t' -> b.append("\\t");
                default -> { if (c < 0x20) b.append(String.format("\\u%04x", (int) c)); else b.append(c); }
            }
        }
        return b.append('"').toString();
    }

    /** First value of "key":"value" in a JSON response (good enough for provider ids). */
    static String jsonValue(String body, String key) {
        Matcher m = Pattern.compile("\"" + Pattern.quote(key) + "\"\\s*:\\s*\"([^\"]*)\"").matcher(body == null ? "" : body);
        return m.find() ? m.group(1) : null;
    }

    static SendResult fromStatus(HttpResponse<String> r, String id) {
        int s = r.statusCode();
        if (s >= 200 && s < 300) return SendResult.ok(id == null ? "HTTP " + s : id);
        String msg = "HTTP " + s + ": " + abbreviate(r.body());
        return SendResult.fail(msg, s == 429 || s >= 500);
    }

    static String abbreviate(String s) {
        if (s == null) return "";
        s = s.replaceAll("\\s+", " ");
        return s.length() > 200 ? s.substring(0, 200) + "..." : s;
    }
}
