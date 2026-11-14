package com.vidhi.voters.admin;

import org.springframework.security.core.GrantedAuthority;
import org.springframework.security.core.userdetails.User;

import java.util.Collection;

/** Logged-in user: authorities = permission codes + ROLE_<code> per role; plus the assembly the user is locked to. */
public class AppUserDetails extends User {
    private final Integer assemblyNo;
    private final String fullName;

    public AppUserDetails(String username, String password, boolean enabled, Collection<? extends GrantedAuthority> authorities,
                          Integer assemblyNo, String fullName) {
        super(username, password, enabled, true, true, true, authorities);
        this.assemblyNo = assemblyNo;
        this.fullName = fullName;
    }

    public Integer getAssemblyNo() { return assemblyNo; }
    public String getFullName() { return fullName; }
}
