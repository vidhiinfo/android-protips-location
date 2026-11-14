package com.vidhi.voters.messaging.provider;

import com.vidhi.voters.messaging.Channel;
import com.vidhi.voters.messaging.MessageTemplate;
import com.vidhi.voters.messaging.MessagingProperties;
import org.springframework.stereotype.Component;

import java.net.URI;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.util.List;

/**
 * WhatsApp Business Cloud API (graph.facebook.com). Business-initiated messages must use a template
 * approved in WhatsApp Manager: the template's provider id is the template name, its language the
 * template language (hi, bn, ur, en ...), and "params" fill {{1}}, {{2}} ... of the body.
 * Without a template name a plain text message is sent (only delivered inside a 24-hour session).
 */
@Component
public class MetaWhatsAppSender implements MessageSender {

    private final MessagingProperties.Meta cfg;

    public MetaWhatsAppSender(MessagingProperties props) { this.cfg = props.getMeta(); }

    @Override public String id() { return "meta"; }
    @Override public boolean supports(Channel channel) { return channel == Channel.WHATSAPP; }
    @Override public boolean configured(Channel channel) { return !cfg.getPhoneNumberId().isBlank() && !cfg.getAccessToken().isBlank(); }

    @Override
    public SendResult send(Channel channel, String e164, String body, MessageTemplate t, List<String> params) {
        String to = e164.replace("+", "");
        String payload;
        if (t != null && t.getProviderTemplateId() != null && !t.getProviderTemplateId().isBlank()) {
            StringBuilder p = new StringBuilder();
            for (int i = 0; i < params.size(); i++) {
                if (i > 0) p.append(',');
                p.append("{\"type\":\"text\",\"text\":").append(HttpSupport.json(params.get(i))).append('}');
            }
            payload = "{\"messaging_product\":\"whatsapp\",\"to\":" + HttpSupport.json(to) + ",\"type\":\"template\","
                    + "\"template\":{\"name\":" + HttpSupport.json(t.getProviderTemplateId())
                    + ",\"language\":{\"code\":" + HttpSupport.json(t.getLanguage()) + "}"
                    + (params.isEmpty() ? "" : ",\"components\":[{\"type\":\"body\",\"parameters\":[" + p + "]}]")
                    + "}}";
        } else {
            payload = "{\"messaging_product\":\"whatsapp\",\"to\":" + HttpSupport.json(to)
                    + ",\"type\":\"text\",\"text\":{\"body\":" + HttpSupport.json(body) + "}}";
        }
        HttpRequest req = HttpRequest.newBuilder(URI.create(cfg.getBaseUrl() + "/" + cfg.getApiVersion() + "/" + cfg.getPhoneNumberId() + "/messages"))
                .timeout(HttpSupport.TIMEOUT)
                .header("Authorization", "Bearer " + cfg.getAccessToken())
                .header("Content-Type", "application/json")
                .POST(HttpRequest.BodyPublishers.ofString(payload))
                .build();
        try {
            HttpResponse<String> r = HttpSupport.CLIENT.send(req, HttpResponse.BodyHandlers.ofString());
            return HttpSupport.fromStatus(r, HttpSupport.jsonValue(r.body(), "id"));
        } catch (Exception e) {
            return SendResult.fail(e.getClass().getSimpleName() + ": " + e.getMessage(), true);
        }
    }
}
