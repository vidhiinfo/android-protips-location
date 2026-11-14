package com.vidhi.voters.admin;

import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

/** Any logged-in user can change their own password. */
@Controller
public class AccountController {

    private final AppUserRepository users;
    private final UserService userService;
    private final AuditService audit;

    public AccountController(AppUserRepository users, UserService userService, AuditService audit) {
        this.users = users;
        this.userService = userService;
        this.audit = audit;
    }

    @GetMapping("/account/password")
    public String form() { return "account/password"; }

    @PostMapping("/account/password")
    public String change(@RequestParam String current, @RequestParam String newPassword, @RequestParam String confirm,
                         Model m, RedirectAttributes ra) {
        AppUser u = users.findByUsernameIgnoreCase(AuditService.currentUser()).orElse(null);
        String error = null;
        if (u == null || !userService.matches(current, u.getPasswordHash())) error = "account.error.current";
        else if (newPassword.length() < AdminController.MIN_PASSWORD) error = "validation.password";
        else if (!newPassword.equals(confirm)) error = "account.error.mismatch";
        if (error != null) {
            m.addAttribute("error", error);
            return "account/password";
        }
        u.setPasswordHash(userService.encode(newPassword));
        users.save(u);
        audit.log("PASSWORD_CHANGE", "USER", u.getId(), null);
        ra.addFlashAttribute("success", "flash.passwordChanged");
        return "redirect:/voters";
    }
}
