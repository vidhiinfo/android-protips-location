package com.vidhi.voters;

import com.sun.net.httpserver.HttpServer;
import com.vidhi.voters.messaging.Channel;
import com.vidhi.voters.messaging.MessageTemplate;
import com.vidhi.voters.messaging.MessagingProperties;
import com.vidhi.voters.messaging.TemplateRenderer;
import com.vidhi.voters.messaging.provider.HttpGatewaySender;
import com.vidhi.voters.messaging.provider.MetaWhatsAppSender;
import com.vidhi.voters.messaging.provider.SendResult;
import com.vidhi.voters.messaging.provider.TwilioSender;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;

import java.net.InetSocketAddress;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.List;
import java.util.concurrent.atomic.AtomicReference;

import static org.assertj.core.api.Assertions.assertThat;

/** Checks the exact HTTP requests each provider makes, against a local stub server. */
class MessagingProvidersTest {

    HttpServer server;
    final AtomicReference<String> method = new AtomicReference<>(), path = new AtomicReference<>(),
            auth = new AtomicReference<>(), body = new AtomicReference<>();
    volatile int status = 201;
    volatile String reply = "{\"sid\":\"SM123\",\"id\":\"wamid.X\",\"request_id\":\"R1\"}";
    String base;

    @BeforeEach
    void start() throws Exception {
        server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);
        server.createContext("/", ex -> {
            method.set(ex.getRequestMethod());
            path.set(ex.getRequestURI().toString());
            auth.set(ex.getRequestHeaders().getFirst("Authorization"));
            body.set(new String(ex.getRequestBody().readAllBytes(), StandardCharsets.UTF_8));
            byte[] out = reply.getBytes(StandardCharsets.UTF_8);
            ex.sendResponseHeaders(status, out.length);
            ex.getResponseBody().write(out);
            ex.close();
        });
        server.start();
        base = "http://127.0.0.1:" + server.getAddress().getPort();
    }

    @AfterEach
    void stop() { server.stop(0); }

    private static MessageTemplate template(String providerId, String params, String lang) {
        MessageTemplate t = new MessageTemplate();
        t.setBody("x");
        t.setProviderTemplateId(providerId);
        t.setParams(params);
        t.setLanguage(lang);
        return t;
    }

    @Test
    void twilioSmsAndWhatsAppTemplate() {
        MessagingProperties p = new MessagingProperties();
        p.getTwilio().setBaseUrl(base);
        p.getTwilio().setAccountSid("AC1");
        p.getTwilio().setAuthToken("tok");
        p.getTwilio().setSmsFrom("+15550001111");
        p.getTwilio().setWhatsappFrom("+14155238886");
        TwilioSender s = new TwilioSender(p);

        SendResult r = s.send(Channel.SMS, "+919876543210", "नमस्ते रमेश", null, List.of());
        assertThat(r.success()).isTrue();
        assertThat(r.providerId()).isEqualTo("SM123");
        assertThat(path.get()).isEqualTo("/2010-04-01/Accounts/AC1/Messages.json");
        assertThat(auth.get()).startsWith("Basic ");
        String form = URLDecoder.decode(body.get(), StandardCharsets.UTF_8);
        assertThat(form).contains("To=+919876543210").contains("From=+15550001111").contains("Body=नमस्ते रमेश");

        s.send(Channel.WHATSAPP, "+919876543210", "ignored", template("HXabc", "name,boothNo", "hi"), List.of("रमेश", "12"));
        form = URLDecoder.decode(body.get(), StandardCharsets.UTF_8);
        assertThat(form).contains("To=whatsapp:+919876543210").contains("ContentSid=HXabc")
                .contains("ContentVariables={\"1\":\"रमेश\",\"2\":\"12\"}").doesNotContain("Body=");
    }

    @Test
    void metaWhatsAppTemplateMessage() {
        MessagingProperties p = new MessagingProperties();
        p.getMeta().setBaseUrl(base);
        p.getMeta().setPhoneNumberId("PN1");
        p.getMeta().setAccessToken("secret");
        SendResult r = new MetaWhatsAppSender(p).send(Channel.WHATSAPP, "+919876543210", "x",
                template("voter_slip", "name,boothNo", "ur"), List.of("عمران \"خان\"", "7"));
        assertThat(r.success()).isTrue();
        assertThat(r.providerId()).isEqualTo("wamid.X");
        assertThat(path.get()).isEqualTo("/v21.0/PN1/messages");
        assertThat(auth.get()).isEqualTo("Bearer secret");
        assertThat(body.get()).contains("\"to\":\"919876543210\"").contains("\"name\":\"voter_slip\"")
                .contains("\"code\":\"ur\"").contains("{\"type\":\"text\",\"text\":\"عمران \\\"خان\\\"\"}");
    }

    @Test
    void genericGatewayGetAndErrors() {
        MessagingProperties p = new MessagingProperties();
        p.getHttp().setUrl(base + "/send?to={mobile}&msg={message}&tid={templateId}&sender={senderId}");
        p.getHttp().setSenderId("JVPRTY");
        HttpGatewaySender s = new HttpGatewaySender(p);
        status = 200;
        SendResult r = s.send(Channel.SMS, "+919876543210", "मतदान करें & जीतें", template("1107000000000001", null, "hi"), List.of());
        assertThat(r.success()).isTrue();
        assertThat(method.get()).isEqualTo("GET");
        String q = URLDecoder.decode(path.get(), StandardCharsets.UTF_8);
        assertThat(q).contains("to=9876543210").contains("msg=मतदान करें & जीतें").contains("tid=1107000000000001").contains("sender=JVPRTY");

        status = 503;
        r = s.send(Channel.SMS, "+919876543210", "x", null, List.of());
        assertThat(r.success()).isFalse();
        assertThat(r.retryable()).isTrue();
        status = 400;
        assertThat(s.send(Channel.SMS, "+919876543210", "x", null, List.of()).retryable()).isFalse();
    }

    @Test
    void unicodeSmsUsesSeventyCharacterParts() {
        assertThat(TemplateRenderer.isGsm7("Please vote on Sunday")).isTrue();
        assertThat(TemplateRenderer.smsSegments("a".repeat(160))).isEqualTo(1);
        assertThat(TemplateRenderer.smsSegments("a".repeat(161))).isEqualTo(2);
        assertThat(TemplateRenderer.isGsm7("कृपया मतदान करें")).isFalse();
        assertThat(TemplateRenderer.smsSegments("क".repeat(70))).isEqualTo(1);
        assertThat(TemplateRenderer.smsSegments("क".repeat(71))).isEqualTo(2);
    }
}
