package com.vidhi.voters.messaging;

import com.vidhi.voters.messaging.provider.SendResult;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.boot.context.event.ApplicationReadyEvent;
import org.springframework.context.event.EventListener;
import org.springframework.data.domain.PageRequest;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;
import org.springframework.transaction.support.TransactionTemplate;

import java.time.LocalDateTime;
import java.util.Arrays;
import java.util.List;

/**
 * Background sender. Every few seconds it takes queued messages of active campaigns and sends them
 * at app.messaging.rate-per-second, only inside the daily send window and never during the
 * silence period. State lives in the database, so a restart resumes where it stopped.
 * Run a single application instance (or disable the worker on all but one).
 */
@Component
public class OutboxWorker {
    private static final Logger log = LoggerFactory.getLogger(OutboxWorker.class);

    private final OutboxRepository outbox;
    private final CampaignRepository campaigns;
    private final MessageGateway gateway;
    private final MessagingService service;
    private final MessagingProperties props;
    private final TransactionTemplate tx;

    public OutboxWorker(OutboxRepository outbox, CampaignRepository campaigns, MessageGateway gateway, MessagingService service,
                        MessagingProperties props, TransactionTemplate tx) {
        this.outbox = outbox;
        this.campaigns = campaigns;
        this.gateway = gateway;
        this.service = service;
        this.props = props;
        this.tx = tx;
    }

    @EventListener(ApplicationReadyEvent.class)
    public void recover() {
        Integer n = tx.execute(s -> outbox.failInterrupted());
        if (n != null && n > 0) log.warn("{} messages were being sent when the server stopped; marked as failed (not resent)", n);
    }

    @Scheduled(fixedDelayString = "${app.messaging.poll-ms:3000}", initialDelay = 5000)
    public void tick() {
        if (props.isWorkerEnabled()) processBatch(false);
    }

    /**
     * Sends up to rate-per-second x 3 messages.
     * @param ignoreWindow true to ignore the daily send window (used by tests)
     * @return number of messages attempted
     */
    public int processBatch(boolean ignoreWindow) {
        if (!ignoreWindow && !service.insideSendWindow()) return 0;
        if (service.silencePeriod().isPresent()) return 0;
        int budget = props.getRatePerSecond() * 3;
        long pause = 1000L / props.getRatePerSecond();
        int done = 0;
        List<Campaign> active = campaigns.findByStatusIn(Arrays.asList(Campaign.Status.QUEUED, Campaign.Status.SENDING));
        for (Campaign c : active) {
            if (c.getScheduledAt() != null && c.getScheduledAt().isAfter(LocalDateTime.now(service.zone()))) continue;
            List<OutboxMessage> batch = outbox.nextBatch(c.getId(), OutboxMessage.Status.QUEUED,
                    PageRequest.of(0, budget - done));
            if (c.getStatus() == Campaign.Status.QUEUED && !batch.isEmpty())
                tx.executeWithoutResult(s -> { c.setStatus(Campaign.Status.SENDING); campaigns.save(c); });
            for (OutboxMessage m : batch) {
                sendOne(c, m);
                done++;
                if (pause > 0) try { Thread.sleep(pause); } catch (InterruptedException e) { Thread.currentThread().interrupt(); return done; }
            }
            refresh(c.getId());
            if (done >= budget) break;
        }
        return done;
    }

    private void sendOne(Campaign c, OutboxMessage m) {
        tx.executeWithoutResult(s -> { m.setStatus(OutboxMessage.Status.SENDING); m.setAttempts(m.getAttempts() + 1); outbox.save(m); });
        List<String> params = m.getParamValues() == null || m.getParamValues().isEmpty() ? List.of()
                : Arrays.asList(m.getParamValues().split(TemplateRenderer.SEP, -1));
        SendResult r = gateway.send(c.getChannel(), m.getMobile(), m.getBody(), c.getTemplate(), params);
        tx.executeWithoutResult(s -> {
            if (r.success()) {
                m.setStatus(OutboxMessage.Status.SENT);
                m.setProviderId(r.providerId());
                m.setError(null);
                m.setSentAt(LocalDateTime.now());
            } else if (r.retryable() && m.getAttempts() < props.getMaxAttempts()) {
                m.setStatus(OutboxMessage.Status.QUEUED);     // try again in a later batch
                m.setError(r.error());
            } else {
                m.setStatus(OutboxMessage.Status.FAILED);
                m.setError(r.error());
            }
            outbox.save(m);
        });
    }

    /** Recount a campaign from its messages and close it when nothing is left. */
    public void refresh(Long campaignId) {
        tx.executeWithoutResult(s -> campaigns.findById(campaignId).ifPresent(c -> {
            c.setSent((int) outbox.countByCampaignIdAndStatus(c.getId(), OutboxMessage.Status.SENT));
            c.setFailed((int) outbox.countByCampaignIdAndStatus(c.getId(), OutboxMessage.Status.FAILED));
            c.setSkipped((int) outbox.countByCampaignIdAndStatus(c.getId(), OutboxMessage.Status.SKIPPED));
            long open = outbox.countByCampaignIdAndStatus(c.getId(), OutboxMessage.Status.QUEUED)
                    + outbox.countByCampaignIdAndStatus(c.getId(), OutboxMessage.Status.SENDING);
            if (open == 0 && c.getStatus() != Campaign.Status.CANCELLED) {
                c.setStatus(Campaign.Status.COMPLETED);
                c.setCompletedAt(LocalDateTime.now());
            }
            campaigns.save(c);
        }));
    }
}
