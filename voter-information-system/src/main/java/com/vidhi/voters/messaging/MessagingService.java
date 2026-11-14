package com.vidhi.voters.messaging;

import com.vidhi.voters.admin.AuditService;
import com.vidhi.voters.master.Election;
import com.vidhi.voters.master.ElectionRepository;
import com.vidhi.voters.model.Voter;
import com.vidhi.voters.service.VoterFilter;
import com.vidhi.voters.service.VoterService;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.time.ZoneId;
import java.util.*;

/**
 * Picks recipients, renders messages and queues them. Rules applied to every campaign:
 *  - only voters with a valid 10-digit mobile number
 *  - numbers on the opt-out list are skipped
 *  - one message per mobile number per campaign (families often share a phone)
 *  - nothing is queued during the pre-poll silence period of the current election
 */
@Service
public class MessagingService {

    /** Who will receive a campaign, before it is created. */
    public record Preview(int voters, int recipients, int noMobile, int optedOut, int duplicates,
                          List<String[]> samples, int maxSegments) {}

    private record Recipient(Voter voter, String e164) {}

    private final VoterService voters;
    private final OutboxRepository outbox;
    private final CampaignRepository campaigns;
    private final OptOutRepository optOuts;
    private final ElectionRepository elections;
    private final MessagingProperties props;
    private final AuditService audit;

    public MessagingService(VoterService voters, OutboxRepository outbox, CampaignRepository campaigns, OptOutRepository optOuts,
                            ElectionRepository elections, MessagingProperties props, AuditService audit) {
        this.voters = voters;
        this.outbox = outbox;
        this.campaigns = campaigns;
        this.optOuts = optOuts;
        this.elections = elections;
        this.props = props;
        this.audit = audit;
    }

    public String e164(String mobile) {
        if (mobile == null) return null;
        String d = mobile.replaceAll("[^0-9]", "");
        if (d.length() == 12 && d.startsWith("91")) d = d.substring(2);
        return d.matches("[6-9][0-9]{9}") ? props.getCountryCode() + d : null;
    }

    // ---------------------------------------------------------------- rules
    /** The current election's silence period: from 48 hours before poll day until poll day ends. */
    public Optional<String> silencePeriod() {
        if (!props.isEnforceSilencePeriod()) return Optional.empty();
        Optional<Election> e = elections.findFirstByCurrentTrue();
        if (e.isEmpty() || e.get().getPollDate() == null) return Optional.empty();
        LocalDate today = LocalDate.now(zone());
        LocalDate poll = e.get().getPollDate();
        return !today.isBefore(poll.minusDays(2)) && !today.isAfter(poll)
                ? Optional.of(e.get().getName() + " (" + poll + ")") : Optional.empty();
    }

    public boolean insideSendWindow() {
        LocalTime now = LocalTime.now(zone());
        return !now.isBefore(props.getSendFrom()) && now.isBefore(props.getSendUntil());
    }

    public ZoneId zone() { return ZoneId.of(props.getTimeZone()); }

    // ---------------------------------------------------------------- recipients
    private List<Recipient> recipients(VoterFilter f, Channel channel, int[] counts) {
        Set<String> blocked = optOuts.blockedNumbers(channel);
        Set<String> seen = new HashSet<>();
        List<Recipient> out = new ArrayList<>();
        List<Voter> list = voters.searchAll(f);
        counts[0] = list.size();
        for (Voter v : list) {
            String e = e164(v.getMobile());
            if (e == null) { counts[1]++; continue; }
            if (blocked.contains(e.substring(e.length() - 10))) { counts[2]++; continue; }
            if (!seen.add(e)) { counts[3]++; continue; }
            out.add(new Recipient(v, e));
        }
        return out;
    }

    @Transactional(readOnly = true)
    public Preview preview(VoterFilter f, MessageTemplate t) {
        int[] c = new int[4];
        List<Recipient> r = recipients(f, t.getChannel(), c);
        List<String[]> samples = new ArrayList<>();
        int maxSeg = 1;
        for (Recipient x : r) {
            String body = TemplateRenderer.render(t.getBody(), x.voter());
            maxSeg = Math.max(maxSeg, TemplateRenderer.smsSegments(body));
            if (samples.size() < 5) samples.add(new String[]{x.voter().getName(), mask(x.e164()), body});
        }
        return new Preview(c[0], r.size(), c[1], c[2], c[3], samples, maxSeg);
    }

    public static String mask(String e164) {
        return e164 == null || e164.length() < 6 ? e164 : e164.substring(0, e164.length() - 6) + "xxxx" + e164.substring(e164.length() - 2);
    }

    /** Freezes the recipient list into the outbox. The worker sends it in the background. */
    @Transactional
    public Campaign create(String name, MessageTemplate t, VoterFilter f, String filterSummary, LocalDateTime scheduledAt, String user) {
        if (!t.isActive()) throw new IllegalStateException("msg.error.inactive");
        if (silencePeriod().isPresent()) throw new IllegalStateException("msg.error.silence");
        int[] c = new int[4];
        List<Recipient> list = recipients(f, t.getChannel(), c);
        if (list.isEmpty()) throw new IllegalStateException("msg.error.noRecipients");

        Campaign camp = new Campaign();
        camp.setName(name == null || name.isBlank() ? t.getName() : name.trim());
        camp.setChannel(t.getChannel());
        camp.setTemplate(t);
        camp.setFilterQuery(f.queryWithSort());
        camp.setFilterSummary(filterSummary);
        camp.setScheduledAt(scheduledAt);
        camp.setCreatedBy(user);
        camp.setTotal(list.size());
        campaigns.save(camp);
        for (Recipient r : list) {
            OutboxMessage m = new OutboxMessage();
            m.setCampaignId(camp.getId());
            m.setVoterId(r.voter().getId());
            m.setVoterName(r.voter().getName());
            m.setMobile(r.e164());
            m.setBody(TemplateRenderer.render(t.getBody(), r.voter()));
            m.setParamValues(String.join(TemplateRenderer.SEP, TemplateRenderer.params(t, r.voter())));
            outbox.save(m);
        }
        audit.log("MSG_CAMPAIGN", "CAMPAIGN", camp.getId(), t.getChannel() + " '" + camp.getName() + "' to " + list.size()
                + " numbers (" + c[2] + " opted out, " + c[3] + " duplicate numbers, " + c[1] + " without mobile)");
        return camp;
    }

    @Transactional
    public void cancel(Campaign c, String user) {
        if (c.getStatus() == Campaign.Status.COMPLETED || c.getStatus() == Campaign.Status.CANCELLED) return;
        int n = outbox.skipQueued(c.getId(), "Cancelled by " + user);
        c.setSkipped(c.getSkipped() + n);
        c.setStatus(Campaign.Status.CANCELLED);
        c.setCompletedAt(LocalDateTime.now());
        campaigns.save(c);
        audit.log("MSG_CANCEL", "CAMPAIGN", c.getId(), n + " unsent messages cancelled");
    }

    // ---------------------------------------------------------------- opt-outs
    @Transactional
    public boolean optOut(String mobile, Channel channel, String reason, String user) {
        String e = e164(mobile);
        if (e == null) return false;
        String d = e.substring(e.length() - 10);
        boolean exists = channel == null ? optOuts.existsByMobileAndChannelIsNull(d) : optOuts.existsByMobileAndChannel(d, channel);
        if (!exists) {
            OptOut o = new OptOut();
            o.setMobile(d);
            o.setChannel(channel);
            o.setReason(reason);
            o.setCreatedBy(user);
            optOuts.save(o);
            audit.log("OPT_OUT", "OPTOUT", d.substring(0, 2) + "xxxxxx" + d.substring(8), channel == null ? "all channels" : channel.name());
        }
        return true;
    }

    public boolean isOptedOut(String mobile, Channel channel) {
        String e = e164(mobile);
        return e != null && optOuts.blockedNumbers(channel).contains(e.substring(e.length() - 10));
    }
}
