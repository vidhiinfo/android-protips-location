package com.vidhi.voters.messaging;

import com.vidhi.voters.messaging.provider.MessageSender;
import com.vidhi.voters.messaging.provider.SendResult;
import org.springframework.stereotype.Component;

import java.util.List;

/** Picks the configured provider for a channel. */
@Component
public class MessageGateway {

    private final List<MessageSender> senders;
    private final MessagingProperties props;

    public MessageGateway(List<MessageSender> senders, MessagingProperties props) {
        this.senders = senders;
        this.props = props;
    }

    public MessageSender sender(Channel channel) {
        String id = props.provider(channel);
        return senders.stream().filter(s -> s.id().equalsIgnoreCase(id) && s.supports(channel)).findFirst()
                .orElseThrow(() -> new IllegalStateException("No provider '" + id + "' for " + channel));
    }

    public boolean dryRun(Channel channel) { return "log".equalsIgnoreCase(props.provider(channel)); }

    public boolean ready(Channel channel) {
        try { return sender(channel).configured(channel); } catch (IllegalStateException e) { return false; }
    }

    public SendResult send(Channel channel, String e164, String body, MessageTemplate t, List<String> params) {
        MessageSender s;
        try { s = sender(channel); } catch (IllegalStateException e) { return SendResult.fail(e.getMessage(), false); }
        if (!s.configured(channel)) return SendResult.fail("Provider '" + s.id() + "' is not configured for " + channel, false);
        return s.send(channel, e164, body, t, params);
    }
}
