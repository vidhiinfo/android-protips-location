package com.vidhi.voters.admin;

import com.vidhi.voters.master.AssemblyRepository;
import com.vidhi.voters.repo.VoterRepository;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.authentication.dao.DaoAuthenticationProvider;
import org.springframework.security.core.AuthenticationException;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;

/**
 * Username + password check, plus: the assembly chosen at login must exist, and a user assigned
 * to an assembly can only log in to that one (and not to "All assemblies").
 */
@Component
public class AssemblyAuthenticationProvider extends DaoAuthenticationProvider {

    /** Shown on the login page as ?error=assembly. */
    public static class AssemblyNotAllowedException extends AuthenticationException {
        public AssemblyNotAllowedException() { super("assembly not allowed"); }
    }

    private final AppUserRepository users;
    private final AssemblyRepository assemblies;
    private final VoterRepository voters;

    public AssemblyAuthenticationProvider(UserService userService, PasswordEncoder encoder, AppUserRepository users,
                                          AssemblyRepository assemblies, VoterRepository voters) {
        super(encoder);
        setUserDetailsService(userService);
        this.users = users;
        this.assemblies = assemblies;
        this.voters = voters;
    }

    @Override
    protected void additionalAuthenticationChecks(UserDetails user, UsernamePasswordAuthenticationToken auth)
            throws AuthenticationException {
        super.additionalAuthenticationChecks(user, auth);   // password
        Integer chosen = auth.getDetails() instanceof LoginDetails d ? d.getAssemblyNo() : null;
        Integer assigned = users.findByUsernameIgnoreCase(user.getUsername()).map(AppUser::getAssemblyNo).orElse(null);
        if (assigned != null && !assigned.equals(chosen)) throw new AssemblyNotAllowedException();
        if (chosen != null && assemblies.findByAssemblyNo(chosen).isEmpty() && voters.countByAssemblyNo(chosen) == 0)
            throw new AssemblyNotAllowedException();
    }
}
