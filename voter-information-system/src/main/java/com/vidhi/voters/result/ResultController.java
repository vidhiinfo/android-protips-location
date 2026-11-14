package com.vidhi.voters.result;

import com.vidhi.voters.admin.AuditService;
import com.vidhi.voters.master.*;
import com.vidhi.voters.report.Report;
import com.vidhi.voters.report.TableExporter;
import com.vidhi.voters.web.Messages;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.context.i18n.LocaleContextHolder;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import java.io.IOException;
import java.util.*;

/** Results menu: booth-wise vote entry (RESULT_ENTRY) and result views (RESULT_VIEW). */
@Controller
@RequestMapping("/results")
@PreAuthorize("hasAuthority('RESULT_VIEW')")
public class ResultController {

    private final ResultService service;
    private final ElectionRepository elections;
    private final AssemblyRepository assemblies;
    private final BoothRepository booths;
    private final CandidateRepository candidates;
    private final TableExporter exporter;
    private final Messages msg;
    private final com.vidhi.voters.config.WorkContext work;

    public ResultController(ResultService service, ElectionRepository elections, AssemblyRepository assemblies,
                            BoothRepository booths, CandidateRepository candidates, TableExporter exporter, Messages msg,
                            com.vidhi.voters.config.WorkContext work) {
        this.work = work;
        this.service = service;
        this.elections = elections;
        this.assemblies = assemblies;
        this.booths = booths;
        this.candidates = candidates;
        this.exporter = exporter;
        this.msg = msg;
    }

    private Election election(Long id) {
        return id != null ? elections.findById(id).orElse(null) : elections.findFirstByCurrentTrue()
                .orElseGet(() -> elections.findAllByOrderByYearDescNameAsc().stream().findFirst().orElse(null));
    }

    /** Seats contested in the election (from the candidate master). */
    private List<Integer> seats(Election e) {
        if (e == null) return List.of();
        return candidates.findByElectionIdOrderByAssemblyNoAscBallotNoAsc(e.getId()).stream()
                .map(Candidate::getAssemblyNo).filter(work::allows).distinct().sorted().toList();
    }

    private void common(Model m, Election e) {
        m.addAttribute("elections", elections.findAllByOrderByYearDescNameAsc());
        m.addAttribute("election", e);
        Map<Integer, String> names = new LinkedHashMap<>();
        for (Integer a : seats(e)) names.put(a, assemblies.findByAssemblyNo(a).map(Assembly::getName).orElse(""));
        m.addAttribute("seats", names);
    }

    // ---------------------------------------------------------------- overview of an election
    @GetMapping
    public String overview(@RequestParam(required = false) Long electionId, Model m) {
        Election e = election(electionId);
        common(m, e);
        m.addAttribute("contests", e == null ? List.of()
                : service.electionContests(e).stream().filter(c -> work.allows(c.assemblyNo())).toList());
        return "results/overview";
    }

    // ---------------------------------------------------------------- one seat
    @GetMapping("/contest")
    public String contest(@RequestParam(required = false) Long electionId, @RequestParam Integer assemblyNo, Model m) {
        assemblyNo = work.restrict(assemblyNo);
        Election e = election(electionId);
        if (e == null) return "redirect:/results";
        common(m, e);
        m.addAttribute("c", service.contest(e, assemblyNo));
        return "results/contest";
    }

    @GetMapping("/contest/export/{format}")
    public void exportContest(@PathVariable String format, @RequestParam Long electionId, @RequestParam Integer assemblyNo,
                              HttpServletResponse resp) throws IOException {
        assemblyNo = work.restrict(assemblyNo);
        Election e = elections.findById(electionId).orElseThrow();
        ResultService.Contest c = service.contest(e, assemblyNo);
        Report r = new Report(msg.get("result.title") + " - " + msg.get("col.assemblyNo") + " " + assemblyNo + " " + c.assemblyName(),
                List.of(msg.get("result.rank"), msg.get("result.candidate"), msg.get("result.party"), msg.get("result.votes"), msg.get("result.voteSharePct")))
                .subtitle(e.getName() + " | " + msg.get("result.turnoutPct") + ": " + String.format(Locale.ROOT, "%.2f", c.getTurnout())
                        + " | " + msg.get("result.margin") + ": " + c.getMargin());
        for (ResultService.CandidateTotal t : c.totals())
            r.addRow(List.of(t.rank(), t.candidate().getName() + (t.candidate().getNameLocal() == null ? "" : " / " + t.candidate().getNameLocal()),
                    t.candidate().getPartyShort(), t.votes(), Math.round(t.share() * 100) / 100.0));
        r.setTotals(List.of("", msg.get("report.total"), "", c.totalVotes(), c.totalVotes() == 0 ? 0.0 : 100.0));
        send(r, format, "result-" + assemblyNo, resp);
    }

    private void send(Report r, String format, String name, HttpServletResponse resp) throws IOException {
        if ("pdf".equals(format)) {
            resp.setContentType("application/pdf");
            resp.setHeader("Content-Disposition", "attachment; filename=\"" + name + ".pdf\"");
            exporter.pdf(r, LocaleContextHolder.getLocale(), resp.getOutputStream());
        } else {
            resp.setContentType("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            resp.setHeader("Content-Disposition", "attachment; filename=\"" + name + ".xlsx\"");
            exporter.excel(r, LocaleContextHolder.getLocale(), resp.getOutputStream());
        }
    }

    // ---------------------------------------------------------------- booth-wise entry
    @PreAuthorize("hasAuthority('RESULT_ENTRY')")
    @GetMapping("/entry")
    public String entry(@RequestParam(required = false) Long electionId, @RequestParam(required = false) Integer assemblyNo,
                        @RequestParam(required = false) Integer boothNo, Model m) {
        assemblyNo = work.restrict(assemblyNo);
        Election e = election(electionId);
        common(m, e);
        m.addAttribute("assemblyNo", assemblyNo);
        m.addAttribute("boothNo", boothNo);
        if (e != null && assemblyNo != null) {
            m.addAttribute("boothList", booths.findByAssemblyNoOrderByBoothNoAsc(assemblyNo));
            m.addAttribute("reported", service.reportedBooths(e.getId(), assemblyNo));
            if (boothNo != null) m.addAttribute("entries", service.boothEntry(e.getId(), assemblyNo, boothNo));
        }
        return "results/entry";
    }

    @PreAuthorize("hasAuthority('RESULT_ENTRY')")
    @PostMapping("/entry")
    public String saveEntry(@RequestParam Long electionId, @RequestParam Integer assemblyNo, @RequestParam Integer boothNo,
                            @RequestParam Map<String, String> params, RedirectAttributes ra) {
        if (!work.allows(assemblyNo)) throw new org.springframework.web.server.ResponseStatusException(org.springframework.http.HttpStatus.FORBIDDEN);
        Map<Long, Integer> votes = new HashMap<>();
        params.forEach((k, v) -> {
            if (k.startsWith("votes[") && v != null && !v.isBlank()) {
                try {
                    votes.put(Long.valueOf(k.substring(6, k.length() - 1)),
                            Integer.valueOf(com.vidhi.voters.model.Voter.asciiDigits(v.trim())));
                } catch (NumberFormatException ignored) { }
            }
        });
        service.saveBooth(electionId, assemblyNo, boothNo, votes, AuditService.currentUser());
        ra.addFlashAttribute("success", "flash.saved");
        // move on to the next booth for faster data entry
        Integer next = booths.findByAssemblyNoOrderByBoothNoAsc(assemblyNo).stream().map(Booth::getBoothNo)
                .filter(b -> b > boothNo).findFirst().orElse(boothNo);
        return "redirect:/results/entry?electionId=" + electionId + "&assemblyNo=" + assemblyNo + "&boothNo=" + next;
    }
}
