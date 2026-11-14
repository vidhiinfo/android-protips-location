package com.vidhi.voters.service;

import com.vidhi.voters.admin.AuditService;
import com.vidhi.voters.master.Assembly;
import com.vidhi.voters.master.AssemblyRepository;
import com.vidhi.voters.master.Booth;
import com.vidhi.voters.master.BoothRepository;
import com.vidhi.voters.model.Gender;
import com.vidhi.voters.model.Voter;
import com.vidhi.voters.repo.VoterRepository;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.*;

@Service
@Transactional(readOnly = true)
public class VoterService {

    /** Safety cap for a single export / print so a huge roll can't exhaust memory. */
    public static final int MAX_EXPORT_ROWS = 100_000;

    private final VoterRepository repo;
    private final AssemblyRepository assemblyMaster;
    private final BoothRepository boothMaster;
    private final AuditService audit;

    public VoterService(VoterRepository repo, AssemblyRepository assemblyMaster, BoothRepository boothMaster, AuditService audit) {
        this.repo = repo;
        this.assemblyMaster = assemblyMaster;
        this.boothMaster = boothMaster;
        this.audit = audit;
    }

    /**
     * Everyone registered at the same house: same assembly, house no., address and village/ward
     * (compared ignoring case and surrounding spaces; an empty value only matches empty).
     */
    public List<Voter> household(Voter v) {
        return repo.findAll((root, q, cb) -> cb.and(
                cb.equal(root.get("assemblyNo"), v.getAssemblyNo()),
                same(cb, root.get("houseNo"), v.getHouseNo()),
                same(cb, root.get("address"), v.getAddress()),
                same(cb, root.get("villageWard"), v.getVillageWard())),
                org.springframework.data.domain.Sort.by("serialNo", "age").and(org.springframework.data.domain.Sort.by("id")));
    }

    private static jakarta.persistence.criteria.Predicate same(jakarta.persistence.criteria.CriteriaBuilder cb,
                                                              jakarta.persistence.criteria.Path<String> path, String value) {
        if (value == null || value.isBlank()) return cb.or(cb.isNull(path), cb.equal(path, ""));
        return cb.equal(cb.lower(cb.trim(path)), value.trim().toLowerCase(java.util.Locale.ROOT));
    }

    public Page<Voter> search(VoterFilter f) {
        return repo.findAll(VoterSpecifications.from(f), PageRequest.of(f.getPage(), f.getSize(), f.toSort()));
    }

    public List<Voter> searchAll(VoterFilter f) {
        return repo.findAll(VoterSpecifications.from(f), PageRequest.of(0, MAX_EXPORT_ROWS, f.toSort())).getContent();
    }

    public Optional<Voter> find(Long id) { return repo.findById(id); }

    public boolean epicTaken(String epic, Long excludeId) {
        if (epic == null) return false;
        return excludeId == null ? repo.existsByEpicNoIgnoreCase(epic.trim())
                                 : repo.existsByEpicNoIgnoreCaseAndIdNot(epic.trim(), excludeId);
    }

    /** Saves the voter; assembly and booth names are filled from the masters when left empty. */
    @Transactional
    public Voter save(Voter v) {
        boolean isNew = v.getId() == null;
        if (v.getAssemblyName() == null || v.getAssemblyName().isBlank())
            assemblyMaster.findByAssemblyNo(v.getAssemblyNo()).map(Assembly::getName).ifPresent(v::setAssemblyName);
        if (v.getBoothName() == null || v.getBoothName().isBlank())
            boothMaster.findByAssemblyNoAndBoothNo(v.getAssemblyNo(), v.getBoothNo()).map(Booth::getName).ifPresent(v::setBoothName);
        Voter saved = repo.save(v);
        audit.log(isNew ? "CREATE" : "UPDATE", "VOTER", saved.getId(), saved.getEpicNo() + " " + saved.getName());
        return saved;
    }

    @Transactional
    public void delete(Long id) {
        repo.findById(id).ifPresent(v -> {
            repo.delete(v);
            audit.log("DELETE", "VOTER", id, v.getEpicNo() + " " + v.getName());
        });
    }

    /** Assembly no -> name, from the voters table and the assembly master. */
    public Map<Integer, String> assemblies() {
        Map<Integer, String> m = new TreeMap<>();
        for (Object[] r : repo.findAssemblies()) {
            m.merge((Integer) r[0], r[1] == null ? "" : (String) r[1], (a, b) -> a.isBlank() ? b : a);
        }
        for (Assembly a : assemblyMaster.findAllByOrderByAssemblyNoAsc()) m.put(a.getAssemblyNo(), a.getName());
        return m;
    }

    public List<Assembly> assemblyMaster() { return assemblyMaster.findAllByOrderByAssemblyNoAsc(); }
    public List<Booth> boothMaster() { return boothMaster.findAllByOrderByAssemblyNoAscBoothNoAsc(); }

    /** Filter drop-down values, limited to one assembly when given (the login assembly). */
    public List<Integer> booths(Integer assemblyNo) { return repo.findBooths(assemblyNo); }
    public List<String> districts(Integer assemblyNo) { return repo.findDistricts(assemblyNo); }
    public List<String> villages(Integer assemblyNo) { return repo.findVillages(assemblyNo); }

    public long total() { return repo.count(); }

    public long total(Integer assemblyNo) { return assemblyNo == null ? repo.count() : repo.countByAssemblyNo(assemblyNo); }

    /** Keyed by enum name ("MALE", "FEMALE", "OTHER") for easy use in templates. */
    public Map<String, Long> genderCounts(Integer assemblyNo) {
        Map<String, Long> m = new LinkedHashMap<>();
        for (Gender g : Gender.values()) m.put(g.name(), 0L);
        for (Object[] r : repo.countByGender(assemblyNo)) m.put(((Gender) r[0]).name(), (Long) r[1]);
        return m;
    }
}
