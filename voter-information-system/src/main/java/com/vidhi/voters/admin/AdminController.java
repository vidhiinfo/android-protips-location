package com.vidhi.voters.admin;

import com.vidhi.voters.repo.VoterRepository;
import jakarta.validation.Valid;
import org.springframework.data.domain.PageRequest;
import org.springframework.http.HttpStatus;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.stereotype.Controller;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import javax.sql.DataSource;
import java.sql.Connection;
import java.sql.DatabaseMetaData;
import java.util.*;

/** Admin menu: users and roles (USER_ADMIN), audit log (AUDIT_VIEW), system information (SYSTEM_VIEW). */
@Controller
@RequestMapping("/admin")
public class AdminController {

    static final int MIN_PASSWORD = 8;

    private final AppUserRepository users;
    private final RoleRepository roles;
    private final UserService userService;
    private final AuditLogRepository auditRepo;
    private final AuditService audit;
    private final VoterRepository voters;
    private final DataSource dataSource;
    private final JdbcTemplate jdbc;
    private final com.vidhi.voters.master.AssemblyRepository assemblyRepo;
    private final SecurityProperties securityProps;

    public AdminController(AppUserRepository users, RoleRepository roles, UserService userService, AuditLogRepository auditRepo,
                           AuditService audit, VoterRepository voters, DataSource dataSource, JdbcTemplate jdbc,
                           com.vidhi.voters.master.AssemblyRepository assemblyRepo, SecurityProperties securityProps) {
        this.users = users;
        this.roles = roles;
        this.userService = userService;
        this.auditRepo = auditRepo;
        this.audit = audit;
        this.voters = voters;
        this.dataSource = dataSource;
        this.jdbc = jdbc;
        this.assemblyRepo = assemblyRepo;
        this.securityProps = securityProps;
    }

    private static ResponseStatusException notFound() { return new ResponseStatusException(HttpStatus.NOT_FOUND); }

    @ModelAttribute("externalUsers")
    boolean externalUsers() { return securityProps.isExternal(); }

    // ================================================================ users
    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @GetMapping("/users")
    public String users(Model m) {
        m.addAttribute("items", users.findAllByOrderByUsernameAsc());
        m.addAttribute("roleList", roles.findAllByOrderBySystemRoleDescNameAsc());
        return "admin/users";
    }

    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @GetMapping({"/users/new", "/users/{id}/edit"})
    public String userForm(@PathVariable(required = false) Long id, Model m) {
        if (securityProps.isExternal()) return "redirect:/admin/users";
        m.addAttribute("item", id == null ? new AppUser() : users.findById(id).orElseThrow(AdminController::notFound));
        userFormData(m);
        return "admin/user-form";
    }

    private void userFormData(Model m) {
        m.addAttribute("roleList", roles.findAllByOrderBySystemRoleDescNameAsc());
        m.addAttribute("assemblyList", assemblyRepo.findAllByOrderByAssemblyNoAsc());
    }

    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @PostMapping("/users")
    @Transactional
    public String saveUser(@Valid @ModelAttribute("item") AppUser form, BindingResult br,
                           @RequestParam(name = "roleIds", required = false) List<Long> roleIds,
                           @RequestParam(required = false) String newPassword, Model m, RedirectAttributes ra) {
        if (securityProps.isExternal()) throw new ResponseStatusException(HttpStatus.FORBIDDEN);
        boolean isNew = form.getId() == null;
        AppUser u = isNew ? new AppUser() : users.findById(form.getId()).orElseThrow(AdminController::notFound);
        Set<Role> chosen = new LinkedHashSet<>(roleIds == null ? List.of() : roles.findAllById(roleIds));

        if (isNew && form.getUsername() != null && users.existsByUsernameIgnoreCase(form.getUsername()))
            br.rejectValue("username", "validation.duplicate");
        if ((isNew || (newPassword != null && !newPassword.isBlank())) && (newPassword == null || newPassword.length() < MIN_PASSWORD))
            br.reject("validation.password");
        if (chosen.isEmpty()) br.reject("admin.error.noRole");
        // never lock yourself out: you must keep a role that can manage users, and stay enabled
        boolean self = !isNew && u.getUsername().equalsIgnoreCase(AuditService.currentUser());
        if (self && (!form.isEnabled() || chosen.stream().noneMatch(r -> r.has(Permission.USER_ADMIN)))) br.reject("admin.error.self");
        if (br.hasErrors()) {
            form.setRoles(chosen);
            userFormData(m);
            return "admin/user-form";
        }
        if (isNew) u.setUsername(form.getUsername());
        u.setFullName(form.getFullName());
        u.setRoles(chosen);
        u.setEnabled(form.isEnabled());
        u.setAssemblyNo(form.getAssemblyNo());
        if (newPassword != null && !newPassword.isBlank()) u.setPasswordHash(userService.encode(newPassword));
        users.save(u);
        audit.log(isNew ? "CREATE" : "UPDATE", "USER", u.getId(), u.getUsername() + " roles="
                + String.join(",", chosen.stream().map(Role::getCode).toList()) + (u.isEnabled() ? "" : " disabled")
                + (newPassword != null && !newPassword.isBlank() && !isNew ? " password reset" : ""));
        ra.addFlashAttribute("success", "flash.saved");
        return "redirect:/admin/users";
    }

    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @PostMapping("/users/{id}/delete")
    public String deleteUser(@PathVariable Long id, RedirectAttributes ra) {
        AppUser u = users.findById(id).orElseThrow(AdminController::notFound);
        if (u.getUsername().equalsIgnoreCase(AuditService.currentUser())) {
            ra.addFlashAttribute("error", "admin.error.self");
            return "redirect:/admin/users";
        }
        users.delete(u);
        audit.log("DELETE", "USER", id, u.getUsername());
        ra.addFlashAttribute("success", "flash.deleted");
        return "redirect:/admin/users";
    }

    // ================================================================ roles
    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @GetMapping("/roles")
    public String roles(Model m) {
        List<Role> list = roles.findAllByOrderBySystemRoleDescNameAsc();
        Map<Long, Long> counts = new HashMap<>();
        list.forEach(r -> counts.put(r.getId(), roles.countUsers(r.getId())));
        m.addAttribute("items", list);
        m.addAttribute("userCounts", counts);
        m.addAttribute("permissions", Permission.values());
        return "admin/roles";
    }

    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @GetMapping({"/roles/new", "/roles/{id}/edit"})
    public String roleForm(@PathVariable(required = false) Long id, Model m) {
        m.addAttribute("item", id == null ? new Role() : roles.findById(id).orElseThrow(AdminController::notFound));
        m.addAttribute("groups", permissionGroups());
        return "admin/role-form";
    }

    static Map<String, List<Permission>> permissionGroups() {
        Map<String, List<Permission>> g = new LinkedHashMap<>();
        for (Permission p : Permission.values()) g.computeIfAbsent(p.getGroup(), k -> new ArrayList<>()).add(p);
        return g;
    }

    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @PostMapping("/roles")
    @Transactional
    public String saveRole(@Valid @ModelAttribute("item") Role form, BindingResult br,
                           @RequestParam(name = "perms", required = false) List<Permission> perms, Model m, RedirectAttributes ra) {
        boolean isNew = form.getId() == null;
        Role r = isNew ? new Role() : roles.findById(form.getId()).orElseThrow(AdminController::notFound);
        Set<Permission> chosen = perms == null || perms.isEmpty() ? EnumSet.noneOf(Permission.class) : EnumSet.copyOf(perms);
        if (form.getCode() != null && (isNew ? roles.existsByCodeIgnoreCase(form.getCode())
                : roles.existsByCodeIgnoreCaseAndIdNot(form.getCode(), form.getId())))
            br.rejectValue("code", "validation.duplicate");
        if (!isNew && r.isSystemRole() && !r.getCode().equalsIgnoreCase(form.getCode())) br.rejectValue("code", "admin.error.systemRole");
        // the ADMIN role always keeps user administration, so nobody is locked out
        if (!isNew && "ADMIN".equals(r.getCode()) && !chosen.contains(Permission.USER_ADMIN)) br.reject("admin.error.adminRole");
        if (br.hasErrors()) {
            form.setPermissions(chosen);
            m.addAttribute("groups", permissionGroups());
            return "admin/role-form";
        }
        r.setCode(form.getCode());
        r.setName(form.getName());
        r.setDescription(form.getDescription());
        r.setPermissions(chosen);
        roles.save(r);
        audit.log(isNew ? "CREATE" : "UPDATE", "ROLE", r.getId(), r.getCode() + ": " + String.join(",", chosen.stream().map(Enum::name).toList()));
        ra.addFlashAttribute("success", "admin.flash.roleSaved");
        return "redirect:/admin/roles";
    }

    @PreAuthorize("hasAuthority('USER_ADMIN')")
    @PostMapping("/roles/{id}/delete")
    public String deleteRole(@PathVariable Long id, RedirectAttributes ra) {
        Role r = roles.findById(id).orElseThrow(AdminController::notFound);
        if (r.isSystemRole() || roles.countUsers(id) > 0) {
            ra.addFlashAttribute("error", r.isSystemRole() ? "admin.error.systemRole" : "flash.inUse");
            return "redirect:/admin/roles";
        }
        roles.delete(r);
        audit.log("DELETE", "ROLE", id, r.getCode());
        ra.addFlashAttribute("success", "flash.deleted");
        return "redirect:/admin/roles";
    }

    // ================================================================ audit log
    @PreAuthorize("hasAuthority('AUDIT_VIEW')")
    @GetMapping("/audit")
    public String auditLog(@RequestParam(required = false) String user, @RequestParam(required = false) String action,
                           @RequestParam(required = false) String entity, @RequestParam(defaultValue = "0") int page, Model m) {
        m.addAttribute("page", auditRepo.search(blank(user), blank(action), blank(entity), PageRequest.of(Math.max(page, 0), 50)));
        m.addAttribute("user", user);
        m.addAttribute("action", action);
        m.addAttribute("entity", entity);
        m.addAttribute("actions", List.of("CREATE", "UPDATE", "DELETE", "IMPORT", "RESULT_SAVE", "LOGIN", "LOGIN_FAILED", "PASSWORD_CHANGE",
                "MSG_CAMPAIGN", "MSG_CANCEL", "MSG_TEST", "OPT_OUT"));
        m.addAttribute("entities", List.of("VOTER", "USER", "ROLE", "ASSEMBLY", "BOOTH", "PARTY", "ELECTION", "CANDIDATE", "RESULT",
                "CAMPAIGN", "TEMPLATE", "OPTOUT"));
        return "admin/audit";
    }

    private static String blank(String s) { return s == null || s.isBlank() ? null : s; }

    // ================================================================ system info
    @PreAuthorize("hasAuthority('SYSTEM_VIEW')")
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
        info.put("admin.sys.userSource", securityProps.isExternal() ? "external" : "app");
        info.put("admin.sys.java", System.getProperty("java.version"));
        info.put("admin.sys.voters", voters.count());
        info.put("admin.sys.users", users.count());
        info.put("admin.sys.roles", roles.count());
        info.put("admin.sys.audit", auditRepo.count());
        m.addAttribute("info", info);
        return "admin/system";
    }
}
