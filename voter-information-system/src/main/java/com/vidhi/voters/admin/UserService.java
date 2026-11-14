package com.vidhi.voters.admin;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.context.event.ApplicationReadyEvent;
import org.springframework.context.event.EventListener;
import org.springframework.security.authentication.event.AbstractAuthenticationFailureEvent;
import org.springframework.security.authentication.event.AuthenticationSuccessEvent;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.userdetails.User;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;
import java.util.List;

/**
 * Users are stored in the app_users table with BCrypt password hashes.
 * Roles are cumulative: ADMIN also gets OPERATOR and VIEWER rights, OPERATOR also gets VIEWER.
 * On first start (empty table) an admin account is created from app.security.admin.* properties.
 */
@Service
public class UserService implements UserDetailsService {

    private final AppUserRepository repo;
    private final PasswordEncoder encoder;
    private final AuditService audit;
    private final String bootstrapUser;
    private final String bootstrapPassword;

    public UserService(AppUserRepository repo, PasswordEncoder encoder, AuditService audit,
                       @Value("${app.security.admin.username}") String bootstrapUser,
                       @Value("${app.security.admin.password}") String bootstrapPassword) {
        this.repo = repo;
        this.encoder = encoder;
        this.audit = audit;
        this.bootstrapUser = bootstrapUser;
        this.bootstrapPassword = bootstrapPassword;
    }

    @EventListener(ApplicationReadyEvent.class)
    @Transactional
    public void createFirstAdmin() {
        // create the configured admin only when no active administrator exists
        if (repo.countByRoleAndEnabledTrue(AppUser.Role.ADMIN) > 0 || repo.existsByUsernameIgnoreCase(bootstrapUser)) return;
        AppUser u = new AppUser();
        u.setUsername(bootstrapUser);
        u.setFullName("Administrator");
        u.setRole(AppUser.Role.ADMIN);
        u.setPasswordHash(bootstrapPassword.startsWith("{") ? bootstrapPassword : encoder.encode(bootstrapPassword));
        repo.save(u);
        audit.log("system", "CREATE", "USER", u.getId(), "Initial administrator " + bootstrapUser);
    }

    @Override
    @Transactional(readOnly = true)
    public UserDetails loadUserByUsername(String username) {
        AppUser u = repo.findByUsernameIgnoreCase(username)
                .orElseThrow(() -> new UsernameNotFoundException(username));
        return User.withUsername(u.getUsername())
                .password(u.getPasswordHash())
                .disabled(!u.isEnabled())
                .authorities(authorities(u.getRole()))
                .build();
    }

    static List<SimpleGrantedAuthority> authorities(AppUser.Role role) {
        return switch (role) {
            case ADMIN -> List.of(new SimpleGrantedAuthority("ROLE_ADMIN"), new SimpleGrantedAuthority("ROLE_OPERATOR"), new SimpleGrantedAuthority("ROLE_VIEWER"));
            case OPERATOR -> List.of(new SimpleGrantedAuthority("ROLE_OPERATOR"), new SimpleGrantedAuthority("ROLE_VIEWER"));
            case VIEWER -> List.of(new SimpleGrantedAuthority("ROLE_VIEWER"));
        };
    }

    public String encode(String raw) { return encoder.encode(raw); }

    public boolean matches(String raw, String hash) { return encoder.matches(raw, hash); }

    @EventListener
    @Transactional
    public void onLogin(AuthenticationSuccessEvent e) {
        String name = e.getAuthentication().getName();
        repo.findByUsernameIgnoreCase(name).ifPresent(u -> u.setLastLogin(LocalDateTime.now()));
        audit.log(name, "LOGIN", "USER", null, null);
    }

    @EventListener
    public void onLoginFailure(AbstractAuthenticationFailureEvent e) {
        audit.log(String.valueOf(e.getAuthentication().getName()), "LOGIN_FAILED", "USER", null,
                e.getException().getClass().getSimpleName());
    }
}
