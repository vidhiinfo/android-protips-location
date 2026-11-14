package com.vidhi.voters.admin;

import org.springframework.boot.CommandLineRunner;
import org.springframework.core.annotation.Order;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

import java.util.EnumSet;

import static com.vidhi.voters.admin.Permission.*;

/** Creates the three system roles on first start (runs before any other start-up data). */
@Component
@Order(0)
public class RoleSeeder implements CommandLineRunner {

    private final RoleRepository roles;

    public RoleSeeder(RoleRepository roles) { this.roles = roles; }

    @Override
    @Transactional
    public void run(String... args) {
        create("ADMIN", "Administrator", "Everything, including users, roles, masters and the audit log", EnumSet.allOf(Permission.class));
        create("OPERATOR", "Operator", "Voter data entry and import, results entry, messaging",
                EnumSet.of(DASHBOARD_VIEW, VOTER_VIEW, VOTER_EDIT, VOTER_DELETE, VOTER_IMPORT, VOTER_EXPORT, MASTER_VIEW,
                        RESULT_VIEW, RESULT_ENTRY, REPORT_VIEW, MESSAGE_SEND));
        create("VIEWER", "Viewer", "Search, view, reports, export and print",
                EnumSet.of(DASHBOARD_VIEW, VOTER_VIEW, VOTER_EXPORT, MASTER_VIEW, RESULT_VIEW, REPORT_VIEW));
    }

    private void create(String code, String name, String desc, EnumSet<Permission> perms) {
        if (!roles.existsByCodeIgnoreCase(code)) roles.save(new Role(code, name, desc, true, perms));
    }
}
