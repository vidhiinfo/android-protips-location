package com.vidhi.voters.messaging.provider;

import com.vidhi.voters.messaging.Channel;
import com.vidhi.voters.messaging.MessageTemplate;

import java.util.List;

/** A messaging provider. Implementations must not throw for provider errors; return SendResult.fail instead. */
public interface MessageSender {
    /** Name used in app.messaging.sms-provider / whatsapp-provider. */
    String id();

    boolean supports(Channel channel);

    /** True when the required credentials are present. */
    boolean configured(Channel channel);

    /**
     * @param e164   recipient, e.g. +919876543210
     * @param body   rendered text
     * @param params rendered values for the provider template variables (template.params order)
     */
    SendResult send(Channel channel, String e164, String body, MessageTemplate template, List<String> params);
}
