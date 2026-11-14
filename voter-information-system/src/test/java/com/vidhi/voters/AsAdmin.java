package com.vidhi.voters;

import org.springframework.security.test.context.support.WithMockUser;

import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;

/** Test user with every permission (like the ADMIN role). */
@Retention(RetentionPolicy.RUNTIME)
@WithMockUser(username = "admin", authorities = {"ROLE_ADMIN", "DASHBOARD_VIEW", "VOTER_VIEW", "VOTER_EDIT", "VOTER_DELETE",
        "VOTER_IMPORT", "VOTER_EXPORT", "MASTER_VIEW", "MASTER_EDIT", "RESULT_VIEW", "RESULT_ENTRY", "REPORT_VIEW", "REPORT_SENSITIVE",
        "MESSAGE_SEND", "MESSAGE_ADMIN", "USER_ADMIN", "AUDIT_VIEW", "SYSTEM_VIEW"})
public @interface AsAdmin {}
