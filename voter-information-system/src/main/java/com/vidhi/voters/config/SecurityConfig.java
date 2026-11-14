package com.vidhi.voters.config;

import com.vidhi.voters.admin.AssemblyAuthenticationProvider;
import com.vidhi.voters.admin.LoginDetails;
import com.vidhi.voters.master.Assembly;
import com.vidhi.voters.master.AssemblyRepository;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.crypto.factory.PasswordEncoderFactories;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.web.SecurityFilterChain;

/**
 * Role-based access: users (app_users) have roles (user_roles -> roles), roles have permissions (role_permissions).
 * Authorities are the permission codes (VOTER_EDIT, REPORT_SENSITIVE, ...) checked by URL rules below,
 * @PreAuthorize on controllers and sec:authorize in templates. Users may also come from external tables (UserService).
 */
@Configuration
@EnableMethodSecurity
@org.springframework.boot.context.properties.EnableConfigurationProperties(com.vidhi.voters.admin.SecurityProperties.class)
public class SecurityConfig {

    /** First page the user is allowed to open. */
    public static String landingPage(org.springframework.security.core.Authentication auth) {
        java.util.Set<String> a = new java.util.HashSet<>();
        auth.getAuthorities().forEach(g -> a.add(g.getAuthority()));
        if (a.contains("DASHBOARD_VIEW")) return "/dashboard";
        if (a.contains("VOTER_VIEW")) return "/voters";
        if (a.contains("REPORT_VIEW")) return "/reports";
        if (a.contains("RESULT_VIEW")) return "/results";
        if (a.contains("MESSAGE_SEND")) return "/messages/campaigns";
        if (a.contains("USER_ADMIN")) return "/admin/users";
        return "/account/password";
    }

    @Bean
    PasswordEncoder passwordEncoder() {
        return PasswordEncoderFactories.createDelegatingPasswordEncoder();
    }

    @Bean
    SecurityFilterChain filterChain(HttpSecurity http, AssemblyAuthenticationProvider provider,
                                    WorkContext work, AssemblyRepository assemblies) throws Exception {
        http
            .authorizeHttpRequests(a -> a
                .requestMatchers("/css/**", "/js/**", "/fonts/**", "/images/**", "/webjars/**", "/login", "/error").permitAll()
                // second line of defence - controllers also check with @PreAuthorize
                .requestMatchers("/admin/users/**", "/admin/roles/**").hasAuthority("USER_ADMIN")
                .requestMatchers("/admin/audit/**").hasAuthority("AUDIT_VIEW")
                .requestMatchers("/admin/system/**").hasAuthority("SYSTEM_VIEW")
                .requestMatchers("/voters/new", "/voters/*/edit").hasAuthority("VOTER_EDIT")
                .requestMatchers("/voters/*/delete").hasAuthority("VOTER_DELETE")
                .requestMatchers("/voters/import", "/voters/import/**").hasAuthority("VOTER_IMPORT")
                .requestMatchers("/voters/export/**", "/voters/print").hasAuthority("VOTER_EXPORT")
                .requestMatchers("/voters/**").hasAuthority("VOTER_VIEW")
                .requestMatchers("/results/entry", "/results/entry/**").hasAuthority("RESULT_ENTRY")
                .requestMatchers("/results/**").hasAuthority("RESULT_VIEW")
                .requestMatchers("/masters/**").hasAuthority("MASTER_VIEW")
                .requestMatchers("/reports/**").hasAuthority("REPORT_VIEW")
                .requestMatchers("/dashboard/**").hasAuthority("DASHBOARD_VIEW")
                .requestMatchers("/messages/**").hasAuthority("MESSAGE_SEND")
                .anyRequest().authenticated())
            .authenticationProvider(provider)
            .formLogin(f -> f.loginPage("/login").permitAll()
                .authenticationDetailsSource(LoginDetails::new)
                .successHandler((req, res, auth) -> {
                    // remember the assembly picked on the login screen for this session
                    Integer a = auth.getDetails() instanceof LoginDetails d ? d.getAssemblyNo() : null;
                    work.set(a, a == null ? null : assemblies.findByAssemblyNo(a).map(Assembly::getName).orElse(""));
                    res.sendRedirect(req.getContextPath() + landingPage(auth));
                })
                .failureHandler((req, res, ex) -> res.sendRedirect(req.getContextPath() + "/login?error="
                        + (ex instanceof AssemblyAuthenticationProvider.AssemblyNotAllowedException ? "assembly" : "credentials"))))
            .logout(l -> l.logoutSuccessUrl("/login?logout").permitAll())
            .headers(h -> h.contentSecurityPolicy(c -> c.policyDirectives(
                    "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'")));
        return http.build();
    }
}
