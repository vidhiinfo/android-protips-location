package com.vidhi.voters.admin;

/**
 * Fine-grained permissions. Roles (table roles) are sets of permissions (table role_permissions);
 * users get roles (table user_roles). Every screen and button checks one of these.
 */
public enum Permission {
    DASHBOARD_VIEW("general"),
    VOTER_VIEW("voters"), VOTER_EDIT("voters"), VOTER_DELETE("voters"), VOTER_IMPORT("voters"), VOTER_EXPORT("voters"),
    MASTER_VIEW("masters"), MASTER_EDIT("masters"),
    RESULT_VIEW("results"), RESULT_ENTRY("results"),
    REPORT_VIEW("reports"), REPORT_SENSITIVE("reports"),
    MESSAGE_SEND("messages"), MESSAGE_ADMIN("messages"),
    USER_ADMIN("admin"), AUDIT_VIEW("admin"), SYSTEM_VIEW("admin");

    private final String group;
    Permission(String group) { this.group = group; }
    public String getGroup() { return group; }
    public String getCode() { return name(); }
}
