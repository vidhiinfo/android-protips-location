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
import org.springframework.beans.propertyeditors.StringTrimmerEditor;
import org.springframework.context.i18n.LocaleContextHolder;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.web.bind.WebDataBinder;
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
@org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_VIEW')")
public class VoterController {

    private final VoterService service;
    private final ExcelExporter excel;
    private final PdfExporter pdf;
    private final ExcelImporter importer;
    private final Messages msg;
    private final com.vidhi.voters.admin.AuditService audit;

    private final com.vidhi.voters.config.WorkContext work;

    @org.springframework.beans.factory.annotation.Autowired
    private com.vidhi.voters.messaging.MessagingService messaging;

    public VoterController(VoterService service, ExcelExporter excel, PdfExporter pdf, ExcelImporter importer, Messages msg,
                           com.vidhi.voters.admin.AuditService audit, com.vidhi.voters.config.WorkContext work) {
        this.audit = audit;
        this.work = work;
        this.service = service;
        this.excel = excel;
        this.pdf = pdf;
        this.importer = importer;
        this.msg = msg;
    }

    /** Accept native digits (e.g. ९८७६५४३२१० or ৯৮৭৬৫৪৩২১০) in number fields; blank text fields become null. */
    @InitBinder
    void initBinder(WebDataBinder binder) {
        binder.registerCustomEditor(String.class, new StringTrimmerEditor(true));
        StringTrimmerEditor digits = new StringTrimmerEditor(true) {
            @Override
            public void setAsText(String text) {
                super.setAsText(text == null ? null : Voter.asciiDigits(text).replaceAll("[\\s-]", ""));
            }
        };
        for (String f : new String[]{"mobile", "altMobile", "pincode"}) binder.registerCustomEditor(String.class, f, digits);
    }

    @ModelAttribute
    void common(Model model) {
        model.addAttribute("genders", Gender.values());
        model.addAttribute("relations", RelationType.values());
    }

    // ------------------------------------------------------------ list / search
    @GetMapping
    public String list(@ModelAttribute("f") VoterFilter f, Model model) {
        f.setAssemblyNo(work.restrict(f.getAssemblyNo()));
        var page = service.search(f);
        model.addAttribute("page", page);
        model.addAttribute("startPage", Math.max(0, page.getNumber() - 3));
        model.addAttribute("endPage", Math.max(0, Math.min(page.getTotalPages() - 1, page.getNumber() + 3)));
        model.addAttribute("assemblies", service.assemblies());
        model.addAttribute("booths", service.booths(f.getAssemblyNo()));
        model.addAttribute("districts", service.districts(f.getAssemblyNo()));
        model.addAttribute("villages", service.villages(f.getAssemblyNo()));
        model.addAttribute("total", service.total(work.getAssemblyNo()));
        model.addAttribute("genderCounts", service.genderCounts(work.getAssemblyNo()));
        return "voters/list";
    }

    @GetMapping("/{id}")
    public String view(@PathVariable Long id, Model model) {
        Voter v = load(id);
        model.addAttribute("voter", v);
        model.addAttribute("household", service.household(v));
        model.addAttribute("optedOut", messaging.isOptedOut(v.getMobile(), com.vidhi.voters.messaging.Channel.SMS));
        return "voters/view";
    }

    /** Everyone at the same house no. + address. Opened in a popup from the House No link; "fragment" returns just the table. */
    @GetMapping("/{id}/household")
    public String household(@PathVariable Long id, @RequestParam(required = false) String fragment, Model model) {
        Voter v = load(id);
        model.addAttribute("voter", v);
        model.addAttribute("household", service.household(v));
        return fragment != null ? "voters/household :: content" : "voters/household";
    }

    // ------------------------------------------------------------ create / update / delete
    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_EDIT')")
    @GetMapping("/new")
    public String createForm(Model model) {
        Voter v = new Voter();
        v.setAssemblyNo(work.getAssemblyNo());
        model.addAttribute("voter", v);
        return "voters/form";
    }

    /** Assembly / booth suggestions from the masters for the voter form. */
    @ModelAttribute("masterAssemblies")
    List<com.vidhi.voters.master.Assembly> masterAssemblies() { return service.assemblyMaster(); }

    @ModelAttribute("masterBooths")
    List<com.vidhi.voters.master.Booth> masterBooths() { return service.boothMaster(); }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_EDIT')")
    @PostMapping
    public String create(@Valid @ModelAttribute("voter") Voter voter, BindingResult br, RedirectAttributes ra) {
        voter.setId(null);
        if (work.isScoped()) voter.setAssemblyNo(work.getAssemblyNo());
        return saveVoter(voter, br, ra, "flash.added");
    }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_EDIT')")
    @GetMapping("/{id}/edit")
    public String editForm(@PathVariable Long id, Model model) {
        model.addAttribute("voter", load(id));
        return "voters/form";
    }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_EDIT')")
    @PostMapping("/{id}")
    public String update(@PathVariable Long id, @Valid @ModelAttribute("voter") Voter voter,
                         BindingResult br, RedirectAttributes ra) {
        Voter existing = load(id);
        voter.setId(existing.getId());
        if (work.isScoped()) voter.setAssemblyNo(work.getAssemblyNo());
        return saveVoter(voter, br, ra, "flash.updated");
    }

    private String saveVoter(Voter voter, BindingResult br, RedirectAttributes ra, String msg) {
        if (service.epicTaken(voter.getEpicNo(), voter.getId())) {
            br.rejectValue("epicNo", "validation.epic.duplicate");
        }
        if (br.hasErrors()) return "voters/form";
        try {
            Voter saved = service.save(voter);
            ra.addFlashAttribute("success", msg);
            return "redirect:/voters/" + saved.getId();
        } catch (DataIntegrityViolationException e) {
            br.rejectValue("epicNo", "validation.epic.duplicate");
            return "voters/form";
        }
    }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_DELETE')")
    @PostMapping("/{id}/delete")
    public String delete(@PathVariable Long id, RedirectAttributes ra) {
        load(id);
        service.delete(id);
        ra.addFlashAttribute("success", "flash.deleted");
        return "redirect:/voters";
    }

    // ------------------------------------------------------------ export / print
    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_EXPORT')")
    @GetMapping("/export/excel")
    public void exportExcel(@ModelAttribute VoterFilter f, HttpServletResponse resp) throws IOException {
        f.setAssemblyNo(work.restrict(f.getAssemblyNo()));
        resp.setContentType("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        resp.setHeader("Content-Disposition", "attachment; filename=\"voters-" + LocalDate.now() + ".xlsx\"");
        excel.write(service.searchAll(f), title(f), LocaleContextHolder.getLocale(), resp.getOutputStream());
    }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_EXPORT')")
    @GetMapping("/export/pdf")
    public void exportPdf(@ModelAttribute VoterFilter f, HttpServletResponse resp) throws IOException {
        f.setAssemblyNo(work.restrict(f.getAssemblyNo()));
        resp.setContentType("application/pdf");
        resp.setHeader("Content-Disposition", "attachment; filename=\"voters-" + LocalDate.now() + ".pdf\"");
        pdf.write(service.searchAll(f), title(f), summary(f), LocaleContextHolder.getLocale(), resp.getOutputStream());
    }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_EXPORT')")
    @GetMapping("/print")
    public String print(@ModelAttribute("f") VoterFilter f,
                        @RequestParam(required = false) String autoprint, Model model) {
        model.addAttribute("autoprint", autoprint != null);
        f.setAssemblyNo(work.restrict(f.getAssemblyNo()));
        model.addAttribute("voters", service.searchAll(f));
        model.addAttribute("title", title(f));
        model.addAttribute("summary", summary(f));
        return "voters/print";
    }

    // ------------------------------------------------------------ bulk import
    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_IMPORT')")
    @GetMapping("/import")
    public String importForm() { return "voters/import"; }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_IMPORT')")
    @GetMapping("/import/template")
    public void template(HttpServletResponse resp) throws IOException {
        resp.setContentType("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        resp.setHeader("Content-Disposition", "attachment; filename=\"voter-import-template.xlsx\"");
        excel.writeTemplate(LocaleContextHolder.getLocale(), resp.getOutputStream());
    }

    @org.springframework.security.access.prepost.PreAuthorize("hasAuthority('VOTER_IMPORT')")
    @PostMapping("/import")
    public String doImport(@RequestParam("file") MultipartFile file, Model model) {
        if (file.isEmpty() || file.getOriginalFilename() == null || !file.getOriginalFilename().toLowerCase().endsWith(".xlsx")) {
            model.addAttribute("error", msg.get("import.error.file"));
            return "voters/import";
        }
        try {
            ExcelImporter.Result r = importer.importXlsx(file.getInputStream(), LocaleContextHolder.getLocale(), work.getAssemblyNo());
            model.addAttribute("result", r);
            audit.log("IMPORT", "VOTER", null, file.getOriginalFilename() + ": " + r.inserted() + " added, "
                    + r.updated() + " updated, " + r.errors().size() + " errors");
        } catch (Exception e) {
            model.addAttribute("error", msg.get("import.error.read") + " " + e.getMessage());
        }
        return "voters/import";
    }

    // ------------------------------------------------------------ helpers
    private Voter load(Long id) {
        return service.find(id).filter(v -> work.allows(v.getAssemblyNo())).orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, msg.get("error.notFound")));
    }

    private String title(VoterFilter f) {
        StringBuilder t = new StringBuilder(msg.get("list.title"));
        if (f.getAssemblyNo() != null) {
            String name = service.assemblies().getOrDefault(f.getAssemblyNo(), "");
            t.append(" - ").append(msg.get("col.assemblyNo")).append(" ").append(f.getAssemblyNo())
             .append(name.isBlank() ? "" : " (" + name + ")");
        }
        if (f.getBoothNo() != null) t.append(" - ").append(msg.get("col.boothNo")).append(" ").append(f.getBoothNo());
        return t.toString();
    }

    private String summary(VoterFilter f) {
        List<String> s = new ArrayList<>();
        add(s, "filter.search", f.getQ());
        add(s, "col.name", f.getName());
        add(s, "col.relativeName", f.getRelativeName());
        add(s, "col.epicNo", f.getEpicNo());
        add(s, "col.mobile", f.getMobile());
        add(s, "col.houseNo", f.getHouseNo());
        if (f.getGender() != null) s.add(msg.get("col.gender") + ": " + msg.get("gender." + f.getGender()));
        if (f.getAgeMin() != null || f.getAgeMax() != null)
            s.add(msg.get("col.age") + ": " + (f.getAgeMin() == null ? "18" : f.getAgeMin()) + "-" + (f.getAgeMax() == null ? "+" : f.getAgeMax()));
        add(s, "col.district", f.getDistrict());
        add(s, "col.villageWard", f.getVillageWard());
        if (f.getHasMobile() != null) s.add(msg.get(f.getHasMobile() ? "filter.withMobile" : "filter.withoutMobile"));
        return String.join(", ", s);
    }

    private void add(List<String> s, String key, String value) {
        if (value != null && !value.isBlank()) s.add(msg.get(key) + ": " + value);
    }
}
