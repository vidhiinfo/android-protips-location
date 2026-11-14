package com.vidhi.voters.messaging;

import jakarta.persistence.*;

import java.time.LocalDateTime;

/** One message to one mobile number. Rows are created QUEUED and processed by OutboxWorker. */
@Entity
@Table(name = "message_outbox", indexes = {
        @Index(name = "idx_outbox_status", columnList = "status, campaign_id"),
        @Index(name = "idx_outbox_campaign", columnList = "campaign_id")})
public class OutboxMessage {
    public enum Status { QUEUED, SENDING, SENT, FAILED, SKIPPED }

    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "campaign_id", nullable = false)
    private Long campaignId;

    @Column(name = "voter_id")
    private Long voterId;

    @Column(name = "voter_name", length = 120)
    private String voterName;

    @Column(nullable = false, length = 16)
    private String mobile;               // E.164, e.g. +919876543210

    @Column(nullable = false, length = 1000)
    private String body;                 // rendered text

    /** Rendered values of the template's provider variables, separated by U+001F. */
    @Column(name = "param_values", length = 1000)
    private String paramValues;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 10)
    private Status status = Status.QUEUED;

    private int attempts;

    @Column(name = "provider_id", length = 100)
    private String providerId;

    @Column(length = 300)
    private String error;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    @Column(name = "sent_at")
    private LocalDateTime sentAt;

    @PrePersist
    void created() { createdAt = LocalDateTime.now(); }

    public Long getId() { return id; }
    public Long getCampaignId() { return campaignId; }
    public void setCampaignId(Long campaignId) { this.campaignId = campaignId; }
    public Long getVoterId() { return voterId; }
    public void setVoterId(Long voterId) { this.voterId = voterId; }
    public String getVoterName() { return voterName; }
    public void setVoterName(String voterName) { this.voterName = voterName; }
    public String getMobile() { return mobile; }
    public String getMaskedMobile() { return MessagingService.mask(mobile); }
    public void setMobile(String mobile) { this.mobile = mobile; }
    public String getBody() { return body; }
    public void setBody(String body) { this.body = body; }
    public String getParamValues() { return paramValues; }
    public void setParamValues(String paramValues) { this.paramValues = paramValues; }
    public Status getStatus() { return status; }
    public void setStatus(Status status) { this.status = status; }
    public int getAttempts() { return attempts; }
    public void setAttempts(int attempts) { this.attempts = attempts; }
    public String getProviderId() { return providerId; }
    public void setProviderId(String providerId) { this.providerId = providerId; }
    public String getError() { return error; }
    public void setError(String error) { this.error = error == null || error.length() <= 300 ? error : error.substring(0, 300); }
    public LocalDateTime getCreatedAt() { return createdAt; }
    public LocalDateTime getSentAt() { return sentAt; }
    public void setSentAt(LocalDateTime sentAt) { this.sentAt = sentAt; }
}
