package com.vidhi.voters.admin;

import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.datasource.DriverManagerDataSource;
import org.springframework.security.core.GrantedAuthority;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.stereotype.Component;

import javax.sql.DataSource;
import java.sql.ResultSet;
import java.sql.ResultSetMetaData;
import java.sql.SQLException;
import java.util.*;

/**
 * Reads users and their role names from existing tables (app.security.user-source=external).
 * Role names are mapped to this application's roles, whose permissions are managed under Admin > Roles.
 */
@Component
public class ExternalUserSource {

    private final SecurityProperties props;
    private final RoleRepository roles;
    private final DataSource appDataSource;
    private volatile JdbcTemplate jdbc;

    public ExternalUserSource(SecurityProperties props, RoleRepository roles, DataSource appDataSource) {
        this.props = props;
        this.roles = roles;
        this.appDataSource = appDataSource;
    }

    private JdbcTemplate jdbc() {
        if (jdbc == null) {
            SecurityProperties.External e = props.getExternal();
            DataSource ds = e.getUrl().isBlank() ? appDataSource : new DriverManagerDataSource(e.getUrl(), e.getUsername(), e.getPassword());
            jdbc = new JdbcTemplate(ds);
        }
        return jdbc;
    }

    public AppUserDetails load(String username) {
        SecurityProperties.External e = props.getExternal();
        List<AppUserDetails> found = jdbc().query(e.getUsersQuery(), (rs, i) -> row(rs), username);
        if (found.isEmpty()) throw new UsernameNotFoundException(username);
        AppUserDetails base = found.get(0);

        List<String> names = jdbc().queryForList(e.getRolesQuery(), String.class, username);
        Set<Role> mapped = new LinkedHashSet<>();
        for (String n : names) {
            if (n == null) continue;
            String code = e.getRoleMap().getOrDefault(n.trim(), e.getRoleMap().getOrDefault(n.trim().toUpperCase(Locale.ROOT), n.trim()));
            roles.findByCodeIgnoreCase(code).ifPresent(mapped::add);
        }
        return new AppUserDetails(base.getUsername(), base.getPassword(), base.isEnabled(),
                UserService.authorities(mapped), base.getAssemblyNo(), base.getFullName());
    }

    /** Role names an external user has, as returned by the roles query (for the Admin screen). */
    public List<String> roleNames(String username) {
        return jdbc().queryForList(props.getExternal().getRolesQuery(), String.class, username);
    }

    private AppUserDetails row(ResultSet rs) throws SQLException {
        ResultSetMetaData md = rs.getMetaData();
        Set<String> cols = new HashSet<>();
        for (int i = 1; i <= md.getColumnCount(); i++) cols.add(md.getColumnLabel(i).toLowerCase(Locale.ROOT));
        String user = rs.getString(1);
        String pass = rs.getString(2);
        if (!pass.startsWith("{")) pass = "{" + props.getExternal().getPasswordEncoding() + "}" + pass;
        boolean enabled = !cols.contains("enabled") || rs.getBoolean("enabled");
        Integer assembly = null;
        if (cols.contains("assembly_no")) { int a = rs.getInt("assembly_no"); assembly = rs.wasNull() ? null : a; }
        String fullName = cols.contains("full_name") ? rs.getString("full_name") : user;
        return new AppUserDetails(user, pass, enabled, List.<GrantedAuthority>of(), assembly, fullName);
    }
}
