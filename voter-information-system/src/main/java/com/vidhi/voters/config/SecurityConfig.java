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
 * Users live in the app_users table (see admin.UserService). Roles:
 *  ADMIN    - everything, incl. Admin menu and editing Masters
 *  OPERATOR - add / edit / delete / import voters, enter results
 *  VIEWER   - search, view, reports, results, export, print
 * Fine-grained rules are @PreAuthorize annotations on the controllers; URL rules below are a second line of defence.
 */
@Configuration
@EnableMethodSecurity
public class SecurityConfig {

    @Bean
    PasswordEncoder passwordEncoder() {
        return PasswordEncoderFactories.createDelegatingPasswordEncoder();
    }

    @Bean
    SecurityFilterChain filterChain(HttpSecurity http, AssemblyAuthenticationProvider provider,
                                    WorkContext work, AssemblyRepository assemblies) throws Exception {
        http
            .authorizeHttpRequests(a -> a
                .requestMatchers("/css/**", "/js/**", "/fonts/**", "/webjars/**", "/login", "/error").permitAll()
                .requestMatchers("/admin/**").hasRole("ADMIN")
                .requestMatchers("/voters/new", "/voters/*/edit", "/voters/*/delete", "/voters/import", "/voters/import/**",
                                 "/results/entry", "/results/entry/**").hasRole("OPERATOR")
                .anyRequest().hasRole("VIEWER"))
            .authenticationProvider(provider)
            .formLogin(f -> f.loginPage("/login").permitAll()
                .authenticationDetailsSource(LoginDetails::new)
                .successHandler((req, res, auth) -> {
                    // remember the assembly picked on the login screen for this session
                    Integer a = auth.getDetails() instanceof LoginDetails d ? d.getAssemblyNo() : null;
                    work.set(a, a == null ? null : assemblies.findByAssemblyNo(a).map(Assembly::getName).orElse(""));
                    res.sendRedirect(req.getContextPath() + "/dashboard");
                })
                .failureHandler((req, res, ex) -> res.sendRedirect(req.getContextPath() + "/login?error="
                        + (ex instanceof AssemblyAuthenticationProvider.AssemblyNotAllowedException ? "assembly" : "credentials"))))
            .logout(l -> l.logoutSuccessUrl("/login?logout").permitAll())
            .headers(h -> h.contentSecurityPolicy(c -> c.policyDirectives(
                    "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'")));
        return http.build();
    }
}
