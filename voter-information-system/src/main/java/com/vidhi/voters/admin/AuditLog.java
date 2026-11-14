package com.vidhi.voters.admin;

import jakarta.persistence.*;

import java.time.LocalDateTime;

@Entity
@Table(name = "audit_log", indexes = {
        @Index(name = "idx_audit_time", columnList = "at"),
        @Index(name = "idx_audit_user", columnList = "username")})
public class AuditLog {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private LocalDateTime at;

    @Column(length = 50)
    private String username;

    @Column(nullable = false, length = 30)
    private String action;          // CREATE, UPDATE, DELETE, IMPORT, LOGIN, LOGIN_FAILED, RESULT_SAVE ...

    @Column(length = 30)
    private String entity;          // VOTER, ASSEMBLY, USER ...

    @Column(name = "entity_id", length = 50)
    private String entityId;

    @Column(length = 500)
    private String details;

    protected AuditLog() {}

    public AuditLog(String username, String action, String entity, String entityId, String details) {
        this.at = LocalDateTime.now();
        this.username = username;
        this.action = action;
        this.entity = entity;
        this.entityId = entityId;
        this.details = details == null || details.length() <= 500 ? details : details.substring(0, 500);
    }

    public Long getId() { return id; }
    public LocalDateTime getAt() { return at; }
    public String getUsername() { return username; }
    public String getAction() { return action; }
    public String getEntity() { return entity; }
    public String getEntityId() { return entityId; }
    public String getDetails() { return details; }
}
