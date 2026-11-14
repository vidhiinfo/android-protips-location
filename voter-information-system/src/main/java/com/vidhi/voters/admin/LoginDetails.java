package com.vidhi.voters.admin;

import jakarta.servlet.http.HttpServletRequest;
import org.springframework.security.web.authentication.WebAuthenticationDetails;

/** Login form details: also carries the assembly picked in the drop-down (blank = all). */
public class LoginDetails extends WebAuthenticationDetails {
    private final Integer assemblyNo;

    public LoginDetails(HttpServletRequest req) {
        super(req);
        Integer a = null;
        try {
            String s = req.getParameter("assemblyNo");
            if (s != null && !s.isBlank()) a = Integer.valueOf(s.trim());
        } catch (NumberFormatException ignored) { }
        this.assemblyNo = a;
    }

    public Integer getAssemblyNo() { return assemblyNo; }
}
