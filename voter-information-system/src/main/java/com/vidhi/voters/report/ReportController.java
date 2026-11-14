package com.vidhi.voters.report;

import com.vidhi.voters.master.AssemblyRepository;
import com.vidhi.voters.master.Election;
import com.vidhi.voters.master.ElectionRepository;
import com.vidhi.voters.service.VoterService;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.context.i18n.LocaleContextHolder;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.io.IOException;
import java.time.LocalDate;

@Controller
@RequestMapping("/reports")
public class ReportController {

    private final ReportService reports;
    private final TableExporter exporter;
    private final VoterService voters;
    private final AssemblyRepository assemblies;
    private final ElectionRepository elections;
    private final com.vidhi.voters.config.WorkContext work;

    public ReportController(ReportService reports, TableExporter exporter, VoterService voters,
                            AssemblyRepository assemblies, ElectionRepository elections, com.vidhi.voters.config.WorkContext work) {
        this.work = work;
        this.reports = reports;
        this.exporter = exporter;
        this.voters = voters;
        this.assemblies = assemblies;
        this.elections = elections;
    }

    @GetMapping
    public String index(Model m) {
        m.addAttribute("reports", ReportService.REPORTS);
        return "reports/index";
    }

    @GetMapping("/{id}")
    public String view(@PathVariable String id, @RequestParam(required = false) Integer assemblyNo,
                       @RequestParam(required = false) Integer boothNo, @RequestParam(required = false) Long electionId,
                       @RequestParam(required = false) String print, Model m) {
        check(id);
        assemblyNo = work.restrict(assemblyNo);
        m.addAttribute("reportId", id);
        m.addAttribute("report", reports.build(id, assemblyNo, boothNo, election(electionId)));
        m.addAttribute("assemblyNo", assemblyNo);
        m.addAttribute("boothNo", boothNo);
        m.addAttribute("electionId", electionId);
        m.addAttribute("assemblies", voters.assemblies());
        m.addAttribute("booths", voters.booths(assemblyNo));
        m.addAttribute("elections", elections.findAllByOrderByYearDescNameAsc());
        m.addAttribute("autoprint", print != null);
        return print != null ? "reports/print" : "reports/view";
    }

    @GetMapping("/{id}/export/{format}")
    public void export(@PathVariable String id, @PathVariable String format,
                       @RequestParam(required = false) Integer assemblyNo, @RequestParam(required = false) Integer boothNo,
                       @RequestParam(required = false) Long electionId, HttpServletResponse resp) throws IOException {
        check(id);
        assemblyNo = work.restrict(assemblyNo);
        Report r = reports.build(id, assemblyNo, boothNo, election(electionId));
        String file = id + "-" + LocalDate.now();
        if ("pdf".equals(format)) {
            resp.setContentType("application/pdf");
            resp.setHeader("Content-Disposition", "attachment; filename=\"" + file + ".pdf\"");
            exporter.pdf(r, LocaleContextHolder.getLocale(), resp.getOutputStream());
        } else {
            resp.setContentType("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            resp.setHeader("Content-Disposition", "attachment; filename=\"" + file + ".xlsx\"");
            exporter.excel(r, LocaleContextHolder.getLocale(), resp.getOutputStream());
        }
    }

    private Election election(Long id) {
        return id != null ? elections.findById(id).orElse(null) : elections.findFirstByCurrentTrue().orElse(null);
    }

    private static void check(String id) {
        if (!ReportService.REPORTS.contains(id)) throw new ResponseStatusException(HttpStatus.NOT_FOUND);
    }
}
