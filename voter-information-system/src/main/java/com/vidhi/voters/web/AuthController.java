package com.vidhi.voters.web;

import com.vidhi.voters.service.VoterService;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;

@Controller
public class AuthController {

    private final VoterService voters;

    public AuthController(VoterService voters) { this.voters = voters; }

    /** Login page: username, password and the assembly to work on. */
    @GetMapping("/login")
    public String login(Model m) {
        m.addAttribute("loginAssemblies", voters.assemblies());
        return "login";
    }

    @GetMapping("/")
    public String home() { return "redirect:/dashboard"; }
}
