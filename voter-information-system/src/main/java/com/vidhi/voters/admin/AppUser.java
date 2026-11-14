package com.vidhi.voters.admin;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

import java.time.LocalDateTime;
import java.util.EnumSet;
import java.util.LinkedHashSet;
import java.util.Set;

@Entity
@Table(name = "app_users", uniqueConstraints = @UniqueConstraint(name = "uk_user_username", columnNames = "username"))
public class AppUser {

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

    /** Roles of the user (table user_roles). Permissions are the union of the roles' permissions. */
    @ManyToMany(fetch = FetchType.EAGER)
    @JoinTable(name = "user_roles", joinColumns = @JoinColumn(name = "user_id"), inverseJoinColumns = @JoinColumn(name = "role_id"))
    private Set<Role> roles = new LinkedHashSet<>();

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
    public Set<Role> getRoles() { return roles; }
    public void setRoles(Set<Role> roles) { this.roles = roles; }

    public Set<Permission> getPermissions() {
        Set<Permission> p = EnumSet.noneOf(Permission.class);
        roles.forEach(r -> p.addAll(r.getPermissions()));
        return p;
    }

    public boolean has(Permission p) { return getPermissions().contains(p); }
    public boolean isEnabled() { return enabled; }
    public void setEnabled(boolean enabled) { this.enabled = enabled; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public LocalDateTime getLastLogin() { return lastLogin; }
    public void setLastLogin(LocalDateTime lastLogin) { this.lastLogin = lastLogin; }
    public LocalDateTime getCreatedAt() { return createdAt; }
}
