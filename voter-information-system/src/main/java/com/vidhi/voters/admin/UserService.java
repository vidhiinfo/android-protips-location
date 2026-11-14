package com.vidhi.voters.admin;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.context.event.ApplicationReadyEvent;
import org.springframework.context.event.EventListener;
import org.springframework.security.authentication.event.AbstractAuthenticationFailureEvent;
import org.springframework.security.authentication.event.AuthenticationSuccessEvent;
import org.springframework.security.core.GrantedAuthority;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;
import java.util.*;

/**
 * Users: table app_users, roles: table roles (+ role_permissions), assignments: table user_roles.
 * Or, with app.security.user-source=external, users and role names from existing tables (ExternalUserSource).
 * A user's authorities are the permission codes of all its roles plus ROLE_<code> for each role.
 */
@Service
public class UserService implements UserDetailsService {

    private final AppUserRepository repo;
    private final RoleRepository roles;
    private final PasswordEncoder encoder;
    private final AuditService audit;
    private final SecurityProperties props;
    private final ExternalUserSource external;
    private final String bootstrapUser;
    private final String bootstrapPassword;

    public UserService(AppUserRepository repo, RoleRepository roles, PasswordEncoder encoder, AuditService audit,
                       SecurityProperties props, ExternalUserSource external,
                       @Value("${app.security.admin.username}") String bootstrapUser,
                       @Value("${app.security.admin.password}") String bootstrapPassword) {
        this.repo = repo;
        this.roles = roles;
        this.encoder = encoder;
        this.audit = audit;
        this.props = props;
        this.external = external;
        this.bootstrapUser = bootstrapUser;
        this.bootstrapPassword = bootstrapPassword;
    }

    /** Creates the configured administrator when (in app mode) no enabled user can manage users. */
    @EventListener(ApplicationReadyEvent.class)
    @Transactional
    public void createFirstAdmin() {
        if (props.isExternal()) return;
        boolean hasAdmin = repo.findAll().stream().anyMatch(u -> u.isEnabled() && u.has(Permission.USER_ADMIN));
        if (hasAdmin || repo.existsByUsernameIgnoreCase(bootstrapUser)) return;
        AppUser u = new AppUser();
        u.setUsername(bootstrapUser);
        u.setFullName("Administrator");
        roles.findByCodeIgnoreCase("ADMIN").ifPresent(r -> u.getRoles().add(r));
        u.setPasswordHash(bootstrapPassword.startsWith("{") ? bootstrapPassword : encoder.encode(bootstrapPassword));
        repo.save(u);
        audit.log("system", "CREATE", "USER", u.getId(), "Initial administrator " + bootstrapUser);
    }

    @Override
    @Transactional(readOnly = true)
    public UserDetails loadUserByUsername(String username) {
        if (props.isExternal()) return external.load(username);
        AppUser u = repo.findByUsernameIgnoreCase(username).orElseThrow(() -> new UsernameNotFoundException(username));
        return new AppUserDetails(u.getUsername(), u.getPasswordHash(), u.isEnabled(), authorities(u.getRoles()),
                u.getAssemblyNo(), u.getFullName());
    }

    static List<GrantedAuthority> authorities(Collection<Role> userRoles) {
        Set<String> a = new LinkedHashSet<>();
        for (Role r : userRoles) {
            a.add("ROLE_" + r.getCode());
            r.getPermissions().forEach(p -> a.add(p.name()));
        }
        return a.stream().map(s -> (GrantedAuthority) new SimpleGrantedAuthority(s)).toList();
    }

    public String encode(String raw) { return encoder.encode(raw); }

    public boolean matches(String raw, String hash) { return encoder.matches(raw, hash); }

    public boolean isExternal() { return props.isExternal(); }

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
