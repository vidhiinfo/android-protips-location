package com.vidhi.voters.web;

import com.vidhi.voters.export.ExcelExporter;
import com.vidhi.voters.export.ExcelImporter;
import com.vidhi.voters.export.PdfExporter;
import com.vidhi.voters.model.Gender;
import com.vidhi.voters.model.RelationType;
import com.vidhi.voters.model.Voter;
import com.vidhi.voters.service.VoterFilter;
import com.vidhi.voters.service.VoterService;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.validation.Valid;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

import java.io.IOException;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;

@Controller
@RequestMapping("/voters")
public class VoterController {

    private final VoterService service;
    private final ExcelExporter excel;
    private final PdfExporter pdf;
    private final ExcelImporter importer;

    public VoterController(VoterService service, ExcelExporter excel, PdfExporter pdf, ExcelImporter importer) {
        this.service = service;
        this.excel = excel;
        this.pdf = pdf;
        this.importer = importer;
    }

    @ModelAttribute
    void common(Model model) {
        model.addAttribute("genders", Gender.values());
        model.addAttribute("relations", RelationType.values());
    }

    // ------------------------------------------------------------ list / search
    @GetMapping
    public String list(@ModelAttribute("f") VoterFilter f, Model model) {
        var page = service.search(f);
        model.addAttribute("page", page);
        model.addAttribute("startPage", Math.max(0, page.getNumber() - 3));
        model.addAttribute("endPage", Math.max(0, Math.min(page.getTotalPages() - 1, page.getNumber() + 3)));
        model.addAttribute("assemblies", service.assemblies());
        model.addAttribute("booths", service.booths());
        model.addAttribute("districts", service.districts());
        model.addAttribute("villages", service.villages());
        model.addAttribute("total", service.total());
        model.addAttribute("genderCounts", service.genderCounts());
        return "voters/list";
    }

    @GetMapping("/{id}")
    public String view(@PathVariable Long id, Model model) {
        model.addAttribute("voter", load(id));
        return "voters/view";
    }

    // ------------------------------------------------------------ create / update / delete
    @GetMapping("/new")
    public String createForm(Model model) {
        model.addAttribute("voter", new Voter());
        return "voters/form";
    }

    @PostMapping
    public String create(@Valid @ModelAttribute("voter") Voter voter, BindingResult br, RedirectAttributes ra) {
        voter.setId(null);
        return saveVoter(voter, br, ra, "Voter added successfully");
    }

    @GetMapping("/{id}/edit")
    public String editForm(@PathVariable Long id, Model model) {
        model.addAttribute("voter", load(id));
        return "voters/form";
    }

    @PostMapping("/{id}")
    public String update(@PathVariable Long id, @Valid @ModelAttribute("voter") Voter voter,
                         BindingResult br, RedirectAttributes ra) {
        Voter existing = load(id);
        voter.setId(existing.getId());
        return saveVoter(voter, br, ra, "Voter updated successfully");
    }

    private String saveVoter(Voter voter, BindingResult br, RedirectAttributes ra, String msg) {
        if (service.epicTaken(voter.getEpicNo(), voter.getId())) {
            br.rejectValue("epicNo", "duplicate", "A voter with this EPIC number already exists");
        }
        if (br.hasErrors()) return "voters/form";
        try {
            Voter saved = service.save(voter);
            ra.addFlashAttribute("success", msg);
            return "redirect:/voters/" + saved.getId();
        } catch (DataIntegrityViolationException e) {
            br.rejectValue("epicNo", "duplicate", "A voter with this EPIC number already exists");
            return "voters/form";
        }
    }

    @PostMapping("/{id}/delete")
    public String delete(@PathVariable Long id, RedirectAttributes ra) {
        load(id);
        service.delete(id);
        ra.addFlashAttribute("success", "Voter deleted");
        return "redirect:/voters";
    }

    // ------------------------------------------------------------ export / print
    @GetMapping("/export/excel")
    public void exportExcel(@ModelAttribute VoterFilter f, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        resp.setHeader("Content-Disposition", "attachment; filename=\"voters-" + LocalDate.now() + ".xlsx\"");
        excel.write(service.searchAll(f), title(f), resp.getOutputStream());
    }

    @GetMapping("/export/pdf")
    public void exportPdf(@ModelAttribute VoterFilter f, HttpServletResponse resp) throws IOException {
        resp.setContentType("application/pdf");
        resp.setHeader("Content-Disposition", "attachment; filename=\"voters-" + LocalDate.now() + ".pdf\"");
        pdf.write(service.searchAll(f), title(f), summary(f), resp.getOutputStream());
    }

    @GetMapping("/print")
    public String print(@ModelAttribute("f") VoterFilter f,
                        @RequestParam(required = false) String autoprint, Model model) {
        model.addAttribute("autoprint", autoprint != null);
        model.addAttribute("voters", service.searchAll(f));
        model.addAttribute("title", title(f));
        model.addAttribute("summary", summary(f));
        return "voters/print";
    }

    // ------------------------------------------------------------ bulk import
    @GetMapping("/import")
    public String importForm() { return "voters/import"; }

    @GetMapping("/import/template")
    public void template(HttpServletResponse resp) throws IOException {
        resp.setContentType("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        resp.setHeader("Content-Disposition", "attachment; filename=\"voter-import-template.xlsx\"");
        excel.writeTemplate(resp.getOutputStream());
    }

    @PostMapping("/import")
    public String doImport(@RequestParam("file") MultipartFile file, Model model) {
        if (file.isEmpty() || file.getOriginalFilename() == null || !file.getOriginalFilename().toLowerCase().endsWith(".xlsx")) {
            model.addAttribute("error", "Please choose an .xlsx file");
            return "voters/import";
        }
        try {
            model.addAttribute("result", importer.importXlsx(file.getInputStream()));
        } catch (Exception e) {
            model.addAttribute("error", "Could not read file: " + e.getMessage());
        }
        return "voters/import";
    }

    // ------------------------------------------------------------ helpers
    private Voter load(Long id) {
        return service.find(id).orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Voter not found"));
    }

    private String title(VoterFilter f) {
        StringBuilder t = new StringBuilder("Voter List");
        if (f.getAssemblyNo() != null) {
            String name = service.assemblies().getOrDefault(f.getAssemblyNo(), "");
            t.append(" - Assembly ").append(f.getAssemblyNo()).append(name.isBlank() ? "" : " (" + name + ")");
        }
        if (f.getBoothNo() != null) t.append(" - Booth ").append(f.getBoothNo());
        return t.toString();
    }

    private static String summary(VoterFilter f) {
        List<String> s = new ArrayList<>();
        if (f.getQ() != null && !f.getQ().isBlank()) s.add("Search \"" + f.getQ() + "\"");
        if (f.getName() != null && !f.getName().isBlank()) s.add("Name ~ " + f.getName());
        if (f.getRelativeName() != null && !f.getRelativeName().isBlank()) s.add("Father/Husband ~ " + f.getRelativeName());
        if (f.getEpicNo() != null && !f.getEpicNo().isBlank()) s.add("EPIC ~ " + f.getEpicNo());
        if (f.getMobile() != null && !f.getMobile().isBlank()) s.add("Mobile ~ " + f.getMobile());
        if (f.getGender() != null) s.add("Gender " + f.getGender().getLabel());
        if (f.getAgeMin() != null || f.getAgeMax() != null)
            s.add("Age " + (f.getAgeMin() == null ? "18" : f.getAgeMin()) + "-" + (f.getAgeMax() == null ? "+" : f.getAgeMax()));
        if (f.getDistrict() != null && !f.getDistrict().isBlank()) s.add("District " + f.getDistrict());
        if (f.getVillageWard() != null && !f.getVillageWard().isBlank()) s.add("Village/Ward " + f.getVillageWard());
        if (f.getHouseNo() != null && !f.getHouseNo().isBlank()) s.add("House " + f.getHouseNo());
        if (f.getHasMobile() != null) s.add(f.getHasMobile() ? "With mobile" : "Without mobile");
        return String.join(", ", s);
    }
}
