package com.vidhi.voters;

import org.springframework.security.test.context.support.WithMockUser;

import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;

/** Test user with the default VIEWER role permissions. */
@Retention(RetentionPolicy.RUNTIME)
@WithMockUser(username = "viewer", authorities = {"ROLE_VIEWER", "DASHBOARD_VIEW", "VOTER_VIEW", "VOTER_EXPORT", "MASTER_VIEW",
        "RESULT_VIEW", "REPORT_VIEW"})
public @interface AsViewer {}
