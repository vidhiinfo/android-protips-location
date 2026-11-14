package com.vidhi.voters.service;

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

    public VoterService(VoterRepository repo) { this.repo = repo; }

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

    @Transactional
    public Voter save(Voter v) { return repo.save(v); }

    @Transactional
    public void delete(Long id) { repo.deleteById(id); }

    public Map<Integer, String> assemblies() {
        Map<Integer, String> m = new LinkedHashMap<>();
        for (Object[] r : repo.findAssemblies()) {
            m.putIfAbsent((Integer) r[0], r[1] == null ? "" : (String) r[1]);
        }
        return m;
    }

    public List<Integer> booths() { return repo.findBooths(); }
    public List<String> districts() { return repo.findDistricts(); }
    public List<String> villages() { return repo.findVillages(); }

    public long total() { return repo.count(); }

    /** Keyed by enum name ("MALE", "FEMALE", "OTHER") for easy use in templates. */
    public Map<String, Long> genderCounts() {
        Map<String, Long> m = new LinkedHashMap<>();
        for (Gender g : Gender.values()) m.put(g.name(), 0L);
        for (Object[] r : repo.countByGender()) m.put(((Gender) r[0]).name(), (Long) r[1]);
        return m;
    }
}
