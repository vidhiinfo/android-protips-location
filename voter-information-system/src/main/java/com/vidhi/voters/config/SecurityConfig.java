package com.vidhi.voters.config;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.core.userdetails.User;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.crypto.factory.PasswordEncoderFactories;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.provisioning.InMemoryUserDetailsManager;
import org.springframework.security.web.SecurityFilterChain;

/**
 * Two roles:
 *  ADMIN  - add / edit / delete / import + everything below
 *  VIEWER - search, view, export, print
 * Credentials come from application.properties / environment variables.
 * Passwords may be plain text (encoded at startup) or already encoded, e.g. "{bcrypt}$2a$10$...".
 */
@Configuration
@EnableMethodSecurity
public class SecurityConfig {

    @Bean
    PasswordEncoder passwordEncoder() {
        return PasswordEncoderFactories.createDelegatingPasswordEncoder();
    }

    @Bean
    UserDetailsService users(PasswordEncoder enc,
                             @Value("${app.security.admin.username}") String adminUser,
                             @Value("${app.security.admin.password}") String adminPass,
                             @Value("${app.security.viewer.username}") String viewerUser,
                             @Value("${app.security.viewer.password}") String viewerPass) {
        return new InMemoryUserDetailsManager(
                User.withUsername(adminUser).password(encode(enc, adminPass)).roles("ADMIN", "VIEWER").build(),
                User.withUsername(viewerUser).password(encode(enc, viewerPass)).roles("VIEWER").build());
    }

    private static String encode(PasswordEncoder enc, String raw) {
        return raw.startsWith("{") ? raw : enc.encode(raw);
    }

    @Bean
    SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(a -> a
                .requestMatchers("/css/**", "/js/**", "/webjars/**", "/login", "/error").permitAll()
                .requestMatchers("/voters/new", "/voters/*/edit", "/voters/*/delete", "/voters/import/**", "/voters/import")
                    .hasRole("ADMIN")
                .anyRequest().hasRole("VIEWER"))
            .formLogin(f -> f.loginPage("/login").defaultSuccessUrl("/voters", true).permitAll())
            .logout(l -> l.logoutSuccessUrl("/login?logout").permitAll())
            .headers(h -> h.contentSecurityPolicy(c -> c.policyDirectives(
                    "default-src 'self'; img-src 'self' data:; frame-ancestors 'none'")));
        return http.build();
    }
}
