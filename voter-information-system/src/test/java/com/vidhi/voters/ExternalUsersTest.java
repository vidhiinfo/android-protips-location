package com.vidhi.voters;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.mock.web.MockHttpSession;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;

import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/**
 * Users and roles read from "existing" tables (user-source=external). The tables below stand in for a
 * customer's own schema: different table and column names, plain role names mapped to app roles.
 */
@SpringBootTest(properties = {
        "app.messaging.worker-enabled=false",
        "spring.datasource.url=jdbc:h2:mem:extusers;MODE=MySQL;DB_CLOSE_DELAY=-1",
        "app.security.user-source=external",
        "app.security.external.users-query=select login_id, pwd_hash, is_active as enabled, ac_no as assembly_no, display_name as full_name from staff where login_id = ?",
        "app.security.external.roles-query=select g.group_name from staff_groups sg join staff s on s.staff_id = sg.staff_id join access_groups g on g.group_id = sg.group_id where s.login_id = ?",
        "app.security.external.role-map.SUPERVISOR=ADMIN",
        "app.security.external.role-map.FIELD_STAFF=VIEWER"})
@AutoConfigureMockMvc
@ActiveProfiles("demo")
class ExternalUsersTest {

    @Autowired MockMvc mvc;
    @Autowired JdbcTemplate jdbc;

    @BeforeEach
    void legacyTables() {
        jdbc.execute("create table if not exists staff (staff_id int primary key, login_id varchar(50), pwd_hash varchar(100), is_active boolean, ac_no int, display_name varchar(100))");
        jdbc.execute("create table if not exists access_groups (group_id int primary key, group_name varchar(50))");
        jdbc.execute("create table if not exists staff_groups (staff_id int, group_id int)");
        if (jdbc.queryForObject("select count(*) from staff", Integer.class) == 0) {
            String hash = new BCryptPasswordEncoder().encode("Legacy@123");   // stored without {bcrypt} prefix
            jdbc.update("insert into staff values (1, 'supervisor1', ?, true, null, 'Supervisor One')", hash);
            jdbc.update("insert into staff values (2, 'field1', ?, true, 46, 'Field Worker')", hash);
            jdbc.update("insert into staff values (3, 'gone1', ?, false, null, 'Former Staff')", hash);
            jdbc.update("insert into access_groups values (1, 'SUPERVISOR'), (2, 'FIELD_STAFF')");
            jdbc.update("insert into staff_groups values (1, 1), (2, 2), (3, 1)");
        }
    }

    @Test
    void externalSupervisorBecomesAdmin() throws Exception {
        MockHttpSession s = (MockHttpSession) mvc.perform(post("/login").with(csrf())
                        .param("username", "supervisor1").param("password", "Legacy@123").param("assemblyNo", ""))
                .andExpect(redirectedUrl("/dashboard")).andReturn().getRequest().getSession(false);
        mvc.perform(get("/admin/roles").session(s)).andExpect(status().isOk());
        mvc.perform(get("/admin/users").session(s)).andExpect(status().isOk())
           .andExpect(content().string(org.hamcrest.Matchers.containsString("existing user tables")));
    }

    @Test
    void externalFieldStaffIsViewerLockedToAssembly() throws Exception {
        mvc.perform(post("/login").with(csrf()).param("username", "field1").param("password", "Legacy@123").param("assemblyNo", "45"))
           .andExpect(redirectedUrl("/login?error=assembly"));
        MockHttpSession s = (MockHttpSession) mvc.perform(post("/login").with(csrf())
                        .param("username", "field1").param("password", "Legacy@123").param("assemblyNo", "46"))
                .andExpect(redirectedUrl("/dashboard")).andReturn().getRequest().getSession(false);
        mvc.perform(get("/voters/new").session(s)).andExpect(status().isForbidden());
        mvc.perform(get("/voters").session(s)).andExpect(status().isOk());
    }

    @Test
    void disabledOrWrongPasswordIsRejected() throws Exception {
        mvc.perform(post("/login").with(csrf()).param("username", "gone1").param("password", "Legacy@123"))
           .andExpect(redirectedUrl("/login?error=credentials"));
        mvc.perform(post("/login").with(csrf()).param("username", "supervisor1").param("password", "nope"))
           .andExpect(redirectedUrl("/login?error=credentials"));
    }
}
