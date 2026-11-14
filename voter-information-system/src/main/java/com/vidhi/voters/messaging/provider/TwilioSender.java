package com.vidhi.voters.messaging.provider;

import com.vidhi.voters.messaging.Channel;
import com.vidhi.voters.messaging.MessageTemplate;
import com.vidhi.voters.messaging.MessagingProperties;
import org.springframework.stereotype.Component;

import java.net.URI;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.util.Base64;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

/**
 * Twilio Programmable Messaging (SMS and WhatsApp).
 * WhatsApp messages outside a 24-hour customer session must use an approved template: set the
 * template's provider id to its Content SID (HX...) and list the variables in "params".
 */
@Component
public class TwilioSender implements MessageSender {

    private final MessagingProperties.Twilio cfg;

    public TwilioSender(MessagingProperties props) { this.cfg = props.getTwilio(); }

    @Override public String id() { return "twilio"; }
    @Override public boolean supports(Channel channel) { return true; }

    @Override
    public boolean configured(Channel channel) {
        return !cfg.getAccountSid().isBlank() && !cfg.getAuthToken().isBlank()
                && !(channel == Channel.SMS ? cfg.getSmsFrom() : cfg.getWhatsappFrom()).isBlank();
    }

    @Override
    public SendResult send(Channel channel, String e164, String body, MessageTemplate t, List<String> params) {
        Map<String, String> f = new LinkedHashMap<>();
        if (channel == Channel.WHATSAPP) {
            f.put("To", "whatsapp:" + e164);
            f.put("From", "whatsapp:" + cfg.getWhatsappFrom());
        } else {
            f.put("To", e164);
            if (cfg.getSmsFrom().startsWith("MG")) f.put("MessagingServiceSid", cfg.getSmsFrom());
            else f.put("From", cfg.getSmsFrom());
        }
        String contentSid = t == null ? null : t.getProviderTemplateId();
        if (channel == Channel.WHATSAPP && contentSid != null && contentSid.startsWith("HX")) {
            f.put("ContentSid", contentSid);
            StringBuilder vars = new StringBuilder("{");
            for (int i = 0; i < params.size(); i++) {
                if (i > 0) vars.append(',');
                vars.append('"').append(i + 1).append("\":").append(HttpSupport.json(params.get(i)));
            }
            f.put("ContentVariables", vars.append('}').toString());
        } else {
            f.put("Body", body);
        }
        String auth = Base64.getEncoder().encodeToString((cfg.getAccountSid() + ":" + cfg.getAuthToken()).getBytes(StandardCharsets.UTF_8));
        HttpRequest req = HttpRequest.newBuilder(URI.create(cfg.getBaseUrl() + "/2010-04-01/Accounts/" + cfg.getAccountSid() + "/Messages.json"))
                .timeout(HttpSupport.TIMEOUT)
                .header("Authorization", "Basic " + auth)
                .header("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8")
                .POST(HttpRequest.BodyPublishers.ofString(HttpSupport.form(f)))
                .build();
        try {
            HttpResponse<String> r = HttpSupport.CLIENT.send(req, HttpResponse.BodyHandlers.ofString());
            return HttpSupport.fromStatus(r, HttpSupport.jsonValue(r.body(), "sid"));
        } catch (Exception e) {
            return SendResult.fail(e.getClass().getSimpleName() + ": " + e.getMessage(), true);
        }
    }
}
