package com.vidhi.voters.messaging;

import jakarta.persistence.*;

import java.time.LocalDateTime;

/** A mobile number that must not be messaged (on one channel, or on all when channel is null). */
@Entity
@Table(name = "message_optouts", uniqueConstraints = @UniqueConstraint(name = "uk_optout", columnNames = {"mobile", "channel"}))
public class OptOut {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, length = 10)
    private String mobile;               // 10 digits

    @Enumerated(EnumType.STRING)
    @Column(length = 10)
    private Channel channel;             // null = every channel

    @Column(length = 200)
    private String reason;

    @Column(name = "created_by", length = 50)
    private String createdBy;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    @PrePersist
    void created() { createdAt = LocalDateTime.now(); }

    public Long getId() { return id; }
    public String getMobile() { return mobile; }
    public void setMobile(String mobile) { this.mobile = mobile; }
    public Channel getChannel() { return channel; }
    public void setChannel(Channel channel) { this.channel = channel; }
    public String getReason() { return reason; }
    public void setReason(String reason) { this.reason = reason; }
    public String getCreatedBy() { return createdBy; }
    public void setCreatedBy(String createdBy) { this.createdBy = createdBy; }
    public LocalDateTime getCreatedAt() { return createdAt; }
}
