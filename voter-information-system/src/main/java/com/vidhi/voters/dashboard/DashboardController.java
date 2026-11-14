package com.vidhi.voters.dashboard;

import com.vidhi.voters.config.WorkContext;
import com.vidhi.voters.master.Election;
import com.vidhi.voters.master.ElectionRepository;
import com.vidhi.voters.service.VoterService;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseBody;

import java.util.Map;

/** Campaign dashboard: statistics and charts for one assembly (the login assembly when one was chosen). */
@Controller
@RequestMapping("/dashboard")
public class DashboardController {

    private final DashboardService dashboard;
    private final VoterService voters;
    private final ElectionRepository elections;
    private final WorkContext work;

    public DashboardController(DashboardService dashboard, VoterService voters, ElectionRepository elections, WorkContext work) {
        this.dashboard = dashboard;
        this.voters = voters;
        this.elections = elections;
        this.work = work;
    }

    private Integer assembly(Integer requested) {
        if (work.isScoped()) return work.getAssemblyNo();
        if (requested != null) return requested;
        return voters.assemblies().keySet().stream().findFirst().orElse(null);
    }

    private Election election(Long id) {
        return id != null ? elections.findById(id).orElse(null) : elections.findFirstByCurrentTrue().orElse(null);
    }

    @GetMapping
    public String page(@RequestParam(required = false) Integer assemblyNo, @RequestParam(required = false) Long electionId,
                       @RequestParam(required = false) String party, Model m) {
        Integer a = assembly(assemblyNo);
        Election e = election(electionId);
        m.addAttribute("assemblyNo", a);
        m.addAttribute("assemblies", voters.assemblies());
        m.addAttribute("elections", elections.findAllByOrderByYearDescNameAsc());
        m.addAttribute("election", e);
        if (a != null) m.addAttribute("data", dashboard.build(a, e, party));
        return "dashboard";
    }

    /** Chart data as JSON (same parameters as the page). */
    @GetMapping("/data")
    @ResponseBody
    public Map<String, Object> data(@RequestParam(required = false) Integer assemblyNo, @RequestParam(required = false) Long electionId,
                                    @RequestParam(required = false) String party) {
        Integer a = assembly(assemblyNo);
        return a == null ? Map.of() : dashboard.build(a, election(electionId), party);
    }
}
