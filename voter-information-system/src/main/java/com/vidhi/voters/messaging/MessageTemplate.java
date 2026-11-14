package com.vidhi.voters.messaging;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

import java.time.LocalDateTime;

/**
 * An approved message text. The body may contain placeholders such as {name} or {boothNo}
 * (see TemplateRenderer.PLACEHOLDERS). For DLT SMS and WhatsApp templates the provider's
 * template id/name is stored, and "params" lists the placeholders in the order of the
 * provider template's variables ({#var#} / {{1}}, {{2}} ...).
 */
@Entity
@Table(name = "message_templates")
public class MessageTemplate {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotBlank(message = "{validation.required}") @Size(max = 100, message = "{validation.size}")
    @Column(nullable = false, length = 100)
    private String name;

    @NotNull(message = "{validation.required}")
    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 10)
    private Channel channel = Channel.SMS;

    /** Language of the text (en, hi, ur, ...). WhatsApp templates are registered per language. */
    @NotBlank(message = "{validation.required}") @Size(max = 10, message = "{validation.size}")
    @Column(nullable = false, length = 10)
    private String language = "en";

    @NotBlank(message = "{validation.required}") @Size(max = 1000, message = "{validation.size}")
    @Column(nullable = false, length = 1000)
    private String body;

    /** DLT template id (SMS) / approved template name (WhatsApp) / Twilio Content SID. */
    @Size(max = 100, message = "{validation.size}")
    @Column(name = "provider_template_id", length = 100)
    private String providerTemplateId;

    /** Comma-separated placeholder names in provider variable order, e.g. "name,boothNo,boothName". */
    @Size(max = 300, message = "{validation.size}")
    @Column(length = 300)
    private String params;

    @Column(nullable = false)
    private boolean active = true;

    @Column(name = "created_at")
    private LocalDateTime createdAt;

    @PrePersist
    void created() { createdAt = LocalDateTime.now(); }

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public Channel getChannel() { return channel; }
    public void setChannel(Channel channel) { this.channel = channel; }
    public String getLanguage() { return language; }
    public void setLanguage(String language) { this.language = language; }
    public String getBody() { return body; }
    public void setBody(String body) { this.body = body; }
    public String getProviderTemplateId() { return providerTemplateId; }
    public void setProviderTemplateId(String v) { this.providerTemplateId = v; }
    public String getParams() { return params; }
    public void setParams(String params) { this.params = params; }
    public boolean isActive() { return active; }
    public void setActive(boolean active) { this.active = active; }
    public LocalDateTime getCreatedAt() { return createdAt; }
}
