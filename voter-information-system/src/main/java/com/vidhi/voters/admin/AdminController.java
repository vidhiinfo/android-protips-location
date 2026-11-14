package com.vidhi.voters.admin;

import com.vidhi.voters.repo.VoterRepository;
import jakarta.validation.Valid;
import org.springframework.data.domain.PageRequest;
import org.springframework.http.HttpStatus;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import javax.sql.DataSource;
import java.sql.Connection;
import java.sql.DatabaseMetaData;
import java.util.*;

/** Admin menu: users, audit log, system information. ADMIN only. */
@Controller
@RequestMapping("/admin")
@PreAuthorize("hasRole('ADMIN')")
public class AdminController {

    static final int MIN_PASSWORD = 8;

    private final AppUserRepository users;
    private final UserService userService;
    private final AuditLogRepository auditRepo;
    private final AuditService audit;
    private final VoterRepository voters;
    private final DataSource dataSource;
    private final JdbcTemplate jdbc;
    private final com.vidhi.voters.master.AssemblyRepository assemblyRepo;

    public AdminController(AppUserRepository users, UserService userService, AuditLogRepository auditRepo, AuditService audit,
                           VoterRepository voters, DataSource dataSource, JdbcTemplate jdbc,
                           com.vidhi.voters.master.AssemblyRepository assemblyRepo) {
        this.assemblyRepo = assemblyRepo;
        this.users = users;
        this.userService = userService;
        this.auditRepo = auditRepo;
        this.audit = audit;
        this.voters = voters;
        this.dataSource = dataSource;
        this.jdbc = jdbc;
    }

    // ---------------------------------------------------------------- users
    @GetMapping("/users")
    public String users(Model m) {
        m.addAttribute("items", users.findAllByOrderByUsernameAsc());
        return "admin/users";
    }

    @GetMapping({"/users/new", "/users/{id}/edit"})
    public String userForm(@PathVariable(required = false) Long id, Model m) {
        m.addAttribute("item", id == null ? new AppUser() : users.findById(id).orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND)));
        m.addAttribute("roles", AppUser.Role.values());
        return "admin/user-form";
    }

    @ModelAttribute("assemblyList")
    java.util.List<com.vidhi.voters.master.Assembly> assemblyList() { return assemblyRepo.findAllByOrderByAssemblyNoAsc(); }

    @PostMapping("/users")
    public String saveUser(@Valid @ModelAttribute("item") AppUser form, BindingResult br,
                           @RequestParam(required = false) String newPassword, Model m, RedirectAttributes ra) {
        boolean isNew = form.getId() == null;
        AppUser u = isNew ? new AppUser() : users.findById(form.getId()).orElseThrow();
        if (isNew && form.getUsername() != null && users.existsByUsernameIgnoreCase(form.getUsername()))
            br.rejectValue("username", "validation.duplicate");
        if ((isNew || (newPassword != null && !newPassword.isBlank())) && (newPassword == null || newPassword.length() < MIN_PASSWORD))
            br.reject("validation.password");
        // never lock out the last active administrator
        boolean self = !isNew && u.getUsername().equalsIgnoreCase(AuditService.currentUser());
        if (self && (form.getRole() != AppUser.Role.ADMIN || !form.isEnabled())) br.reject("admin.error.self");
        if (br.hasErrors()) {
            m.addAttribute("roles", AppUser.Role.values());
            return "admin/user-form";
        }
        if (isNew) u.setUsername(form.getUsername());
        u.setFullName(form.getFullName());
        u.setRole(form.getRole());
        u.setEnabled(form.isEnabled());
        u.setAssemblyNo(form.getAssemblyNo());
        if (newPassword != null && !newPassword.isBlank()) u.setPasswordHash(userService.encode(newPassword));
        users.save(u);
        audit.log(isNew ? "CREATE" : "UPDATE", "USER", u.getId(), u.getUsername() + " " + u.getRole() + (u.isEnabled() ? "" : " disabled")
                + (newPassword != null && !newPassword.isBlank() && !isNew ? " password reset" : ""));
        ra.addFlashAttribute("success", "flash.saved");
        return "redirect:/admin/users";
    }

    @PostMapping("/users/{id}/delete")
    public String deleteUser(@PathVariable Long id, RedirectAttributes ra) {
        AppUser u = users.findById(id).orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND));
        if (u.getUsername().equalsIgnoreCase(AuditService.currentUser())) {
            ra.addFlashAttribute("error", "admin.error.self");
            return "redirect:/admin/users";
        }
        users.delete(u);
        audit.log("DELETE", "USER", id, u.getUsername());
        ra.addFlashAttribute("success", "flash.deleted");
        return "redirect:/admin/users";
    }

    // ---------------------------------------------------------------- audit log
    @GetMapping("/audit")
    public String auditLog(@RequestParam(required = false) String user, @RequestParam(required = false) String action,
                           @RequestParam(required = false) String entity, @RequestParam(defaultValue = "0") int page, Model m) {
        m.addAttribute("page", auditRepo.search(blank(user), blank(action), blank(entity), PageRequest.of(Math.max(page, 0), 50)));
        m.addAttribute("user", user);
        m.addAttribute("action", action);
        m.addAttribute("entity", entity);
        m.addAttribute("actions", List.of("CREATE", "UPDATE", "DELETE", "IMPORT", "RESULT_SAVE", "LOGIN", "LOGIN_FAILED", "PASSWORD_CHANGE", "MSG_CAMPAIGN", "MSG_CANCEL", "MSG_TEST", "OPT_OUT"));
        m.addAttribute("entities", List.of("VOTER", "USER", "ASSEMBLY", "BOOTH", "PARTY", "ELECTION", "CANDIDATE", "RESULT", "CAMPAIGN", "TEMPLATE", "OPTOUT"));
        return "admin/audit";
    }

    private static String blank(String s) { return s == null || s.isBlank() ? null : s; }

    // ---------------------------------------------------------------- system info
    @GetMapping("/system")
    public String system(Model m) throws Exception {
        Map<String, Object> info = new LinkedHashMap<>();
        try (Connection c = dataSource.getConnection()) {
            DatabaseMetaData md = c.getMetaData();
            info.put("admin.sys.db", md.getDatabaseProductName() + " " + md.getDatabaseProductVersion());
            info.put("admin.sys.url", md.getURL().replaceAll("password=[^&;]*", "password=***"));
            if (md.getDatabaseProductName().toLowerCase(Locale.ROOT).contains("mysql")) {
                info.put("admin.sys.charset", jdbc.queryForObject(
                        "select concat(default_character_set_name, ' / ', default_collation_name) from information_schema.schemata where schema_name = database()", String.class));
                info.put("admin.sys.tableCharset", jdbc.queryForObject(
                        "select table_collation from information_schema.tables where table_schema = database() and table_name = 'voters'", String.class));
            } else {
                info.put("admin.sys.charset", "Unicode (UTF-8)");
            }
        }
        info.put("admin.sys.java", System.getProperty("java.version"));
        info.put("admin.sys.voters", voters.count());
        info.put("admin.sys.users", users.count());
        info.put("admin.sys.audit", auditRepo.count());
        m.addAttribute("info", info);
        return "admin/system";
    }
}
