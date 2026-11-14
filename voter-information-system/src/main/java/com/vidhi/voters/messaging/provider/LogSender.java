package com.vidhi.voters.messaging.provider;

import com.vidhi.voters.messaging.Channel;
import com.vidhi.voters.messaging.MessageTemplate;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.UUID;

/** Dry-run provider (default): writes the message to the application log and reports success. Nothing is sent. */
@Component
public class LogSender implements MessageSender {
    private static final Logger log = LoggerFactory.getLogger(LogSender.class);

    @Override public String id() { return "log"; }
    @Override public boolean supports(Channel channel) { return true; }
    @Override public boolean configured(Channel channel) { return true; }

    @Override
    public SendResult send(Channel channel, String e164, String body, MessageTemplate template, List<String> params) {
        log.info("[DRY-RUN {}] to {}: {}", channel, e164.replaceAll("\\d(?=\\d{4})", "*"), body);
        return SendResult.ok("dry-run-" + UUID.randomUUID().toString().substring(0, 8));
    }
}
