package com.vidhi.voters.admin;

import jakarta.persistence.*;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;

import java.util.EnumSet;
import java.util.Set;

/** A role = a named set of permissions. System roles (ADMIN, OPERATOR, VIEWER) cannot be deleted. */
@Entity
@Table(name = "roles", uniqueConstraints = @UniqueConstraint(name = "uk_role_code", columnNames = "code"))
public class Role {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    /** Short code, also used to match role names coming from an external user table. */
    @NotBlank(message = "{validation.required}")
    @Pattern(regexp = "^[A-Za-z0-9_]{2,40}$", message = "{validation.roleCode}")
    @Column(nullable = false, length = 40)
    private String code;

    @NotBlank(message = "{validation.required}") @Size(max = 100, message = "{validation.size}")
    @Column(nullable = false, length = 100)
    private String name;

    @Size(max = 300, message = "{validation.size}")
    @Column(length = 300)
    private String description;

    @Column(name = "system_role", nullable = false)
    private boolean systemRole;

    @ElementCollection(fetch = FetchType.EAGER)
    @CollectionTable(name = "role_permissions", joinColumns = @JoinColumn(name = "role_id"),
            uniqueConstraints = @UniqueConstraint(name = "uk_role_permission", columnNames = {"role_id", "permission"}))
    @Enumerated(EnumType.STRING)
    @Column(name = "permission", length = 30, nullable = false)
    private Set<Permission> permissions = EnumSet.noneOf(Permission.class);

    public Role() {}

    public Role(String code, String name, String description, boolean systemRole, Set<Permission> permissions) {
        this.code = code;
        this.name = name;
        this.description = description;
        this.systemRole = systemRole;
        this.permissions = permissions.isEmpty() ? EnumSet.noneOf(Permission.class) : EnumSet.copyOf(permissions);
    }

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getCode() { return code; }
    public void setCode(String code) { this.code = code == null ? null : code.trim().toUpperCase(java.util.Locale.ROOT); }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
    public boolean isSystemRole() { return systemRole; }
    public void setSystemRole(boolean systemRole) { this.systemRole = systemRole; }
    public Set<Permission> getPermissions() { return permissions; }
    public void setPermissions(Set<Permission> permissions) { this.permissions = permissions; }
    public boolean has(Permission p) { return permissions.contains(p); }
}
