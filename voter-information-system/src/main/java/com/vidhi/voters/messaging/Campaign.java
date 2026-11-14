package com.vidhi.voters.messaging;

import jakarta.persistence.*;

import java.time.LocalDateTime;

/** One send-out of a template to the voters matching a filter. Recipients are frozen at creation time. */
@Entity
@Table(name = "message_campaigns")
public class Campaign {
    public enum Status { QUEUED, SENDING, COMPLETED, CANCELLED }

    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, length = 150)
    private String name;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 10)
    private Channel channel;

    @ManyToOne(optional = false, fetch = FetchType.EAGER)
    @JoinColumn(name = "template_id", nullable = false)
    private MessageTemplate template;

    /** Voter-list filter used to pick recipients (query string), kept for the record. */
    @Column(name = "filter_query", length = 1000)
    private String filterQuery;

    @Column(name = "filter_summary", length = 500)
    private String filterSummary;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 10)
    private Status status = Status.QUEUED;

    /** Optional: do not start before this time. */
    @Column(name = "scheduled_at")
    private LocalDateTime scheduledAt;

    private int total;
    private int sent;
    private int failed;
    private int skipped;

    @Column(name = "created_by", length = 50)
    private String createdBy;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    @Column(name = "completed_at")
    private LocalDateTime completedAt;

    @PrePersist
    void created() { createdAt = LocalDateTime.now(); }

    public int getPending() { return Math.max(0, total - sent - failed - skipped); }
    public int getProgress() { return total == 0 ? 100 : (int) Math.round(100.0 * (total - getPending()) / total); }

    public Long getId() { return id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public Channel getChannel() { return channel; }
    public void setChannel(Channel channel) { this.channel = channel; }
    public MessageTemplate getTemplate() { return template; }
    public void setTemplate(MessageTemplate template) { this.template = template; }
    public String getFilterQuery() { return filterQuery; }
    public void setFilterQuery(String filterQuery) { this.filterQuery = filterQuery; }
    public String getFilterSummary() { return filterSummary; }
    public void setFilterSummary(String filterSummary) { this.filterSummary = filterSummary; }
    public Status getStatus() { return status; }
    public void setStatus(Status status) { this.status = status; }
    public LocalDateTime getScheduledAt() { return scheduledAt; }
    public void setScheduledAt(LocalDateTime scheduledAt) { this.scheduledAt = scheduledAt; }
    public int getTotal() { return total; }
    public void setTotal(int total) { this.total = total; }
    public int getSent() { return sent; }
    public void setSent(int sent) { this.sent = sent; }
    public int getFailed() { return failed; }
    public void setFailed(int failed) { this.failed = failed; }
    public int getSkipped() { return skipped; }
    public void setSkipped(int skipped) { this.skipped = skipped; }
    public String getCreatedBy() { return createdBy; }
    public void setCreatedBy(String createdBy) { this.createdBy = createdBy; }
    public LocalDateTime getCreatedAt() { return createdAt; }
    public LocalDateTime getCompletedAt() { return completedAt; }
    public void setCompletedAt(LocalDateTime completedAt) { this.completedAt = completedAt; }
}
