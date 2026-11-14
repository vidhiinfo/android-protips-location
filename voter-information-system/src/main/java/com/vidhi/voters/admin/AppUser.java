package com.vidhi.voters.admin;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

import java.time.LocalDateTime;

@Entity
@Table(name = "app_users", uniqueConstraints = @UniqueConstraint(name = "uk_user_username", columnNames = "username"))
public class AppUser {

    /** ADMIN: everything incl. Admin and Masters. OPERATOR: voter data entry, import, results entry. VIEWER: read, reports, export. */
    public enum Role { ADMIN, OPERATOR, VIEWER }

    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotBlank(message = "{validation.required}")
    @Pattern(regexp = "^[A-Za-z0-9._-]{3,50}$", message = "{validation.username}")
    @Column(nullable = false, length = 50)
    private String username;

    @Column(name = "password_hash", nullable = false, length = 100)
    private String passwordHash;

    @NotBlank(message = "{validation.required}") @Size(max = 100, message = "{validation.size}")
    @Column(name = "full_name", nullable = false, length = 100)
    private String fullName;

    @NotNull(message = "{validation.required}")
    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 10)
    private Role role = Role.VIEWER;

    @Column(nullable = false)
    private boolean enabled = true;

    /** If set, the user can only log in to (and work on) this assembly. null = any assembly. */
    @Min(value = 1, message = "{validation.positive}")
    @Column(name = "assembly_no")
    private Integer assemblyNo;

    @Column(name = "last_login")
    private LocalDateTime lastLogin;

    @Column(name = "created_at", updatable = false)
    private LocalDateTime createdAt;

    @PrePersist
    void created() { createdAt = LocalDateTime.now(); }

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getUsername() { return username; }
    public void setUsername(String username) { this.username = username; }
    public String getPasswordHash() { return passwordHash; }
    public void setPasswordHash(String passwordHash) { this.passwordHash = passwordHash; }
    public String getFullName() { return fullName; }
    public void setFullName(String fullName) { this.fullName = fullName; }
    public Role getRole() { return role; }
    public void setRole(Role role) { this.role = role; }
    public boolean isEnabled() { return enabled; }
    public void setEnabled(boolean enabled) { this.enabled = enabled; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public LocalDateTime getLastLogin() { return lastLogin; }
    public void setLastLogin(LocalDateTime lastLogin) { this.lastLogin = lastLogin; }
    public LocalDateTime getCreatedAt() { return createdAt; }
}
