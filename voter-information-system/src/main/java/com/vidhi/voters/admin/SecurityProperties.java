package com.vidhi.voters.admin;

import org.springframework.boot.context.properties.ConfigurationProperties;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Where users come from (prefix app.security).
 *  user-source=app       users, roles and permissions in this application's tables (default)
 *  user-source=external  users and their role names are read from YOUR existing tables with the SQL below;
 *                        role names are matched to the role codes managed under Admin > Roles (or mapped with role-map).
 */
@ConfigurationProperties(prefix = "app.security")
public class SecurityProperties {

    private String userSource = "app";
    private final External external = new External();

    public static class External {
        /** Must return: username, password, enabled [, assembly_no] [, full_name] for ? = username. */
        private String usersQuery = "select username, password, enabled, assembly_no, full_name from users where username = ?";
        /** Must return one role name per row for ? = username. */
        private String rolesQuery = "select r.role_name from user_roles ur join users u on u.id = ur.user_id "
                + "join roles r on r.id = ur.role_id where u.username = ?";
        /** Password hashes without a {id} prefix are checked with this encoder: bcrypt, pbkdf2, scrypt, argon2, sha256, MD5, noop. */
        private String passwordEncoding = "bcrypt";
        /** External role name -> application role code, e.g. SUPER_ADMIN -> ADMIN. Unmapped names are matched by code. */
        private Map<String, String> roleMap = new LinkedHashMap<>();
        /** Optional separate database for the user tables; empty = the application database. */
        private String url = "";
        private String username = "";
        private String password = "";

        public String getUsersQuery() { return usersQuery; }
        public void setUsersQuery(String v) { usersQuery = v; }
        public String getRolesQuery() { return rolesQuery; }
        public void setRolesQuery(String v) { rolesQuery = v; }
        public String getPasswordEncoding() { return passwordEncoding; }
        public void setPasswordEncoding(String v) { passwordEncoding = v; }
        public Map<String, String> getRoleMap() { return roleMap; }
        public void setRoleMap(Map<String, String> v) { roleMap = v; }
        public String getUrl() { return url; }
        public void setUrl(String v) { url = v; }
        public String getUsername() { return username; }
        public void setUsername(String v) { username = v; }
        public String getPassword() { return password; }
        public void setPassword(String v) { password = v; }
    }

    public String getUserSource() { return userSource; }
    public void setUserSource(String v) { userSource = v; }
    public boolean isExternal() { return "external".equalsIgnoreCase(userSource); }
    public External getExternal() { return external; }
}
