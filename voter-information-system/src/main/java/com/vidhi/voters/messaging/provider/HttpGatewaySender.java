package com.vidhi.voters.messaging.provider;

import com.vidhi.voters.messaging.Channel;
import com.vidhi.voters.messaging.MessageTemplate;
import com.vidhi.voters.messaging.MessagingProperties;
import org.springframework.stereotype.Component;

import java.net.URI;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.util.List;
import java.util.Map;

/**
 * Generic HTTP SMS gateway for Indian DLT-registered providers (MSG91, Textlocal, Fast2SMS, Kaleyra ...).
 * The URL / body are templates configured in app.messaging.http.*; see README for examples.
 * DLT: every SMS must match a registered template - its id is the template's provider id.
 */
@Component
public class HttpGatewaySender implements MessageSender {

    private final MessagingProperties.Http cfg;

    public HttpGatewaySender(MessagingProperties props) { this.cfg = props.getHttp(); }

    @Override public String id() { return "http"; }
    @Override public boolean supports(Channel channel) { return channel == Channel.SMS; }
    @Override public boolean configured(Channel channel) { return !cfg.getUrl().isBlank(); }

    @Override
    public SendResult send(Channel channel, String e164, String body, MessageTemplate t, List<String> params) {
        boolean json = cfg.getContentType().contains("json");
        Map<String, String> vars = Map.of(
                "mobile", e164.length() > 10 ? e164.substring(e164.length() - 10) : e164,
                "e164", e164,
                "message", body,
                "templateId", t == null || t.getProviderTemplateId() == null ? "" : t.getProviderTemplateId(),
                "senderId", cfg.getSenderId(),
                "entityId", cfg.getEntityId());
        String url = fill(cfg.getUrl(), vars, false);
        HttpRequest.Builder b = HttpRequest.newBuilder(URI.create(url)).timeout(HttpSupport.TIMEOUT);
        if (!cfg.getAuthHeader().isBlank() && cfg.getAuthHeader().contains(":")) {
            int i = cfg.getAuthHeader().indexOf(':');
            b.header(cfg.getAuthHeader().substring(0, i).trim(), cfg.getAuthHeader().substring(i + 1).trim());
        }
        if ("POST".equalsIgnoreCase(cfg.getMethod())) {
            b.header("Content-Type", cfg.getContentType() + "; charset=UTF-8")
             .POST(HttpRequest.BodyPublishers.ofString(fill(cfg.getBody(), vars, json)));
        } else {
            b.GET();
        }
        try {
            HttpResponse<String> r = HttpSupport.CLIENT.send(b.build(), HttpResponse.BodyHandlers.ofString());
            SendResult res = HttpSupport.fromStatus(r, null);
            if (res.success() && !cfg.getSuccessContains().isBlank() && !r.body().contains(cfg.getSuccessContains()))
                return SendResult.fail("Unexpected response: " + HttpSupport.abbreviate(r.body()), false);
            if (res.success()) {
                String id = HttpSupport.jsonValue(r.body(), "request_id");
                if (id == null) id = HttpSupport.jsonValue(r.body(), "message");
                return SendResult.ok(id == null ? "HTTP " + r.statusCode() : id);
            }
            return res;
        } catch (Exception e) {
            return SendResult.fail(e.getClass().getSimpleName() + ": " + e.getMessage(), true);
        }
    }

    private static String fill(String template, Map<String, String> vars, boolean json) {
        String out = template;
        for (Map.Entry<String, String> e : vars.entrySet()) {
            String v = json ? HttpSupport.json(e.getValue()).replaceAll("^\"|\"$", "") : HttpSupport.enc(e.getValue());
            out = out.replace("{" + e.getKey() + "}", v);
        }
        return out;
    }
}
