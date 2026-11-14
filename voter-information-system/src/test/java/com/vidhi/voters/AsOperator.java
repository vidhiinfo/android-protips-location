package com.vidhi.voters;

import org.springframework.security.test.context.support.WithMockUser;

import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;

/** Test user with the default OPERATOR role permissions. */
@Retention(RetentionPolicy.RUNTIME)
@WithMockUser(username = "operator", authorities = {"ROLE_OPERATOR", "DASHBOARD_VIEW", "VOTER_VIEW", "VOTER_EDIT", "VOTER_DELETE",
        "VOTER_IMPORT", "VOTER_EXPORT", "MASTER_VIEW", "RESULT_VIEW", "RESULT_ENTRY", "REPORT_VIEW", "MESSAGE_SEND"})
public @interface AsOperator {}
