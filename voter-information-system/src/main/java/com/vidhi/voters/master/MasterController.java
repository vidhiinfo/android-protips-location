package com.vidhi.voters.master;

import com.vidhi.voters.admin.AuditService;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.stereotype.Controller;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.ui.Model;
import org.springframework.validation.BindingResult;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.servlet.mvc.support.RedirectAttributes;

/**
 * Masters menu: Assemblies, Booths, Parties, Elections, Candidates.
 * Everyone can view; only ADMIN can add, edit or delete.
 */
@Controller
@RequestMapping("/masters")
public class MasterController {

    private final AssemblyRepository assemblies;
    private final BoothRepository booths;
    private final PartyRepository parties;
    private final ElectionRepository elections;
    private final CandidateRepository candidates;
    private final com.vidhi.voters.result.BoothResultRepository results;
    private final AuditService audit;
    private com.vidhi.voters.config.WorkContext work;

    @org.springframework.beans.factory.annotation.Autowired
    void setWork(com.vidhi.voters.config.WorkContext work) { this.work = work; }

    public MasterController(AssemblyRepository assemblies, BoothRepository booths, PartyRepository parties,
                            ElectionRepository elections, CandidateRepository candidates,
                            com.vidhi.voters.result.BoothResultRepository results, AuditService audit) {
        this.assemblies = assemblies;
        this.booths = booths;
        this.parties = parties;
        this.elections = elections;
        this.candidates = candidates;
        this.results = results;
        this.audit = audit;
    }

    private static ResponseStatusException notFound() { return new ResponseStatusException(HttpStatus.NOT_FOUND); }

    private String done(RedirectAttributes ra, String type, String flash) {
        ra.addFlashAttribute("success", flash);
        return "redirect:/masters/" + type;
    }

    // ================================================================ assemblies
    @GetMapping("/assemblies")
    public String assemblies(Model m) {
        m.addAttribute("items", assemblies.findAllByOrderByAssemblyNoAsc());
        return "masters/assemblies";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @GetMapping({"/assemblies/new", "/assemblies/{id}/edit"})
    public String assemblyForm(@PathVariable(required = false) Long id, Model m) {
        m.addAttribute("item", id == null ? new Assembly() : assemblies.findById(id).orElseThrow(MasterController::notFound));
        return "masters/assembly-form";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/assemblies")
    public String saveAssembly(@Valid @ModelAttribute("item") Assembly a, BindingResult br, RedirectAttributes ra) {
        if (a.getAssemblyNo() != null && (a.getId() == null ? assemblies.existsByAssemblyNo(a.getAssemblyNo())
                : assemblies.existsByAssemblyNoAndIdNot(a.getAssemblyNo(), a.getId())))
            br.rejectValue("assemblyNo", "validation.duplicate");
        if (br.hasErrors()) return "masters/assembly-form";
        boolean isNew = a.getId() == null;
        assemblies.save(a);
        audit.log(isNew ? "CREATE" : "UPDATE", "ASSEMBLY", a.getId(), a.getAssemblyNo() + " " + a.getName());
        return done(ra, "assemblies", "flash.saved");
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/assemblies/{id}/delete")
    public String deleteAssembly(@PathVariable Long id, RedirectAttributes ra) {
        Assembly a = assemblies.findById(id).orElseThrow(MasterController::notFound);
        if (booths.countByAssemblyNo(a.getAssemblyNo()) > 0) {
            ra.addFlashAttribute("error", "flash.inUse");
            return "redirect:/masters/assemblies";
        }
        assemblies.delete(a);
        audit.log("DELETE", "ASSEMBLY", id, a.getAssemblyNo() + " " + a.getName());
        return done(ra, "assemblies", "flash.deleted");
    }

    // ================================================================ booths
    @GetMapping("/booths")
    public String booths(@RequestParam(required = false) Integer assemblyNo, Model m) {
        if (assemblyNo == null) assemblyNo = work.getAssemblyNo();
        m.addAttribute("items", assemblyNo == null ? booths.findAllByOrderByAssemblyNoAscBoothNoAsc()
                : booths.findByAssemblyNoOrderByBoothNoAsc(assemblyNo));
        m.addAttribute("assemblies", assemblies.findAllByOrderByAssemblyNoAsc());
        m.addAttribute("assemblyNo", assemblyNo);
        return "masters/booths";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @GetMapping({"/booths/new", "/booths/{id}/edit"})
    public String boothForm(@PathVariable(required = false) Long id, @RequestParam(required = false) Integer assemblyNo, Model m) {
        Booth b = id == null ? new Booth() : booths.findById(id).orElseThrow(MasterController::notFound);
        if (id == null) b.setAssemblyNo(assemblyNo);
        m.addAttribute("item", b);
        m.addAttribute("assemblies", assemblies.findAllByOrderByAssemblyNoAsc());
        return "masters/booth-form";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/booths")
    public String saveBooth(@Valid @ModelAttribute("item") Booth b, BindingResult br, Model m, RedirectAttributes ra) {
        if (b.getAssemblyNo() != null && b.getBoothNo() != null && (b.getId() == null
                ? booths.existsByAssemblyNoAndBoothNo(b.getAssemblyNo(), b.getBoothNo())
                : booths.existsByAssemblyNoAndBoothNoAndIdNot(b.getAssemblyNo(), b.getBoothNo(), b.getId())))
            br.rejectValue("boothNo", "validation.duplicate");
        if (br.hasErrors()) {
            m.addAttribute("assemblies", assemblies.findAllByOrderByAssemblyNoAsc());
            return "masters/booth-form";
        }
        boolean isNew = b.getId() == null;
        booths.save(b);
        audit.log(isNew ? "CREATE" : "UPDATE", "BOOTH", b.getId(), b.getAssemblyNo() + "/" + b.getBoothNo() + " " + b.getName());
        ra.addFlashAttribute("success", "flash.saved");
        return "redirect:/masters/booths?assemblyNo=" + b.getAssemblyNo();
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/booths/{id}/delete")
    public String deleteBooth(@PathVariable Long id, RedirectAttributes ra) {
        Booth b = booths.findById(id).orElseThrow(MasterController::notFound);
        booths.delete(b);
        audit.log("DELETE", "BOOTH", id, b.getAssemblyNo() + "/" + b.getBoothNo());
        return done(ra, "booths", "flash.deleted");
    }

    // ================================================================ parties
    @GetMapping("/parties")
    public String parties(Model m) {
        m.addAttribute("items", parties.findAllByOrderByNameAsc());
        return "masters/parties";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @GetMapping({"/parties/new", "/parties/{id}/edit"})
    public String partyForm(@PathVariable(required = false) Long id, Model m) {
        m.addAttribute("item", id == null ? new Party() : parties.findById(id).orElseThrow(MasterController::notFound));
        return "masters/party-form";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/parties")
    public String saveParty(@Valid @ModelAttribute("item") Party p, BindingResult br, RedirectAttributes ra) {
        if (p.getShortName() != null && (p.getId() == null ? parties.existsByShortNameIgnoreCase(p.getShortName())
                : parties.existsByShortNameIgnoreCaseAndIdNot(p.getShortName(), p.getId())))
            br.rejectValue("shortName", "validation.duplicate");
        if (br.hasErrors()) return "masters/party-form";
        boolean isNew = p.getId() == null;
        parties.save(p);
        audit.log(isNew ? "CREATE" : "UPDATE", "PARTY", p.getId(), p.getShortName());
        return done(ra, "parties", "flash.saved");
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/parties/{id}/delete")
    public String deleteParty(@PathVariable Long id, RedirectAttributes ra) {
        Party p = parties.findById(id).orElseThrow(MasterController::notFound);
        if (candidates.existsByPartyId(id)) {
            ra.addFlashAttribute("error", "flash.inUse");
            return "redirect:/masters/parties";
        }
        parties.delete(p);
        audit.log("DELETE", "PARTY", id, p.getShortName());
        return done(ra, "parties", "flash.deleted");
    }

    // ================================================================ elections
    @GetMapping("/elections")
    public String elections(Model m) {
        m.addAttribute("items", elections.findAllByOrderByYearDescNameAsc());
        return "masters/elections";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @GetMapping({"/elections/new", "/elections/{id}/edit"})
    public String electionForm(@PathVariable(required = false) Long id, Model m) {
        m.addAttribute("item", id == null ? new Election() : elections.findById(id).orElseThrow(MasterController::notFound));
        m.addAttribute("types", Election.Type.values());
        return "masters/election-form";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/elections")
    @Transactional
    public String saveElection(@Valid @ModelAttribute("item") Election e, BindingResult br, Model m, RedirectAttributes ra) {
        if (br.hasErrors()) {
            m.addAttribute("types", Election.Type.values());
            return "masters/election-form";
        }
        boolean isNew = e.getId() == null;
        elections.save(e);
        if (e.isCurrent()) elections.clearCurrentExcept(e.getId());
        audit.log(isNew ? "CREATE" : "UPDATE", "ELECTION", e.getId(), e.getName());
        return done(ra, "elections", "flash.saved");
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/elections/{id}/delete")
    public String deleteElection(@PathVariable Long id, RedirectAttributes ra) {
        Election e = elections.findById(id).orElseThrow(MasterController::notFound);
        if (candidates.existsByElectionId(id)) {
            ra.addFlashAttribute("error", "flash.inUse");
            return "redirect:/masters/elections";
        }
        elections.delete(e);
        audit.log("DELETE", "ELECTION", id, e.getName());
        return done(ra, "elections", "flash.deleted");
    }

    // ================================================================ candidates
    @GetMapping("/candidates")
    public String candidates(@RequestParam(required = false) Long electionId, @RequestParam(required = false) Integer assemblyNo, Model m) {
        if (assemblyNo == null) assemblyNo = work.getAssemblyNo();
        Long eid = electionId != null ? electionId : elections.findFirstByCurrentTrue().map(Election::getId).orElse(null);
        m.addAttribute("items", eid == null ? candidates.findAllByOrderByElectionIdDescAssemblyNoAscBallotNoAsc()
                : assemblyNo == null ? candidates.findByElectionIdOrderByAssemblyNoAscBallotNoAsc(eid)
                : candidates.findByElectionIdAndAssemblyNoOrderByBallotNoAscNameAsc(eid, assemblyNo));
        m.addAttribute("elections", elections.findAllByOrderByYearDescNameAsc());
        m.addAttribute("assemblies", assemblies.findAllByOrderByAssemblyNoAsc());
        m.addAttribute("electionId", eid);
        m.addAttribute("assemblyNo", assemblyNo);
        return "masters/candidates";
    }

    @PreAuthorize("hasRole('ADMIN')")
    @GetMapping({"/candidates/new", "/candidates/{id}/edit"})
    public String candidateForm(@PathVariable(required = false) Long id, @RequestParam(required = false) Long electionId,
                                @RequestParam(required = false) Integer assemblyNo, Model m) {
        Candidate c = id == null ? new Candidate() : candidates.findById(id).orElseThrow(MasterController::notFound);
        if (id == null) {
            if (electionId != null) elections.findById(electionId).ifPresent(c::setElection);
            else elections.findFirstByCurrentTrue().ifPresent(c::setElection);
            c.setAssemblyNo(assemblyNo);
        }
        m.addAttribute("item", c);
        candidateFormData(m);
        return "masters/candidate-form";
    }

    private void candidateFormData(Model m) {
        m.addAttribute("elections", elections.findAllByOrderByYearDescNameAsc());
        m.addAttribute("assemblies", assemblies.findAllByOrderByAssemblyNoAsc());
        m.addAttribute("parties", parties.findAllByOrderByNameAsc());
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/candidates")
    public String saveCandidate(@Valid @ModelAttribute("item") Candidate c, BindingResult br, Model m, RedirectAttributes ra) {
        if (br.hasErrors()) {
            candidateFormData(m);
            return "masters/candidate-form";
        }
        boolean isNew = c.getId() == null;
        candidates.save(c);
        audit.log(isNew ? "CREATE" : "UPDATE", "CANDIDATE", c.getId(), c.getName() + " (" + c.getPartyShort() + ")");
        ra.addFlashAttribute("success", "flash.saved");
        return "redirect:/masters/candidates?electionId=" + c.getElection().getId() + "&assemblyNo=" + c.getAssemblyNo();
    }

    @PreAuthorize("hasRole('ADMIN')")
    @PostMapping("/candidates/{id}/delete")
    public String deleteCandidate(@PathVariable Long id, RedirectAttributes ra) {
        Candidate c = candidates.findById(id).orElseThrow(MasterController::notFound);
        if (results.existsByCandidateId(id)) {
            ra.addFlashAttribute("error", "flash.inUse");
            return "redirect:/masters/candidates";
        }
        candidates.delete(c);
        audit.log("DELETE", "CANDIDATE", id, c.getName());
        return done(ra, "candidates", "flash.deleted");
    }
}
