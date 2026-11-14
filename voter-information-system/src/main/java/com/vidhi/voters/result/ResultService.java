package com.vidhi.voters.result;

import com.vidhi.voters.admin.AuditService;
import com.vidhi.voters.master.*;
import com.vidhi.voters.repo.VoterRepository;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.*;
import java.util.stream.Collectors;

@Service
@Transactional(readOnly = true)
public class ResultService {

    public record CandidateTotal(Candidate candidate, long votes, double share, int rank) {
        public String getPartyColor() {
            return candidate.getParty() != null && candidate.getParty().getColor() != null ? candidate.getParty().getColor() : "#6c757d";
        }
    }

    public record BoothRow(int boothNo, String boothName, Map<Long, Integer> votes, long total, Candidate leader) {
        public Integer votesFor(Long candidateId) { return votes.get(candidateId); }
    }

    /** Result of one assembly seat in one election. */
    public record Contest(Election election, int assemblyNo, String assemblyName, List<CandidateTotal> totals,
                          long totalVotes, long electors, List<BoothRow> booths, int boothsReported, int boothsTotal) {
        public CandidateTotal getWinner() { return totals.isEmpty() || totalVotes == 0 ? null : totals.get(0); }
        public CandidateTotal getRunnerUp() { return totals.size() < 2 || totalVotes == 0 ? null : totals.get(1); }
        public long getMargin() { return getWinner() == null ? 0 : getWinner().votes() - (getRunnerUp() == null ? 0 : getRunnerUp().votes()); }
        public double getTurnout() { return electors == 0 ? 0 : 100.0 * totalVotes / electors; }
        public boolean isComplete() { return boothsTotal > 0 && boothsReported >= boothsTotal; }
    }

    private final CandidateRepository candidates;
    private final BoothResultRepository results;
    private final BoothRepository booths;
    private final AssemblyRepository assemblies;
    private final VoterRepository voters;
    private final AuditService audit;

    public ResultService(CandidateRepository candidates, BoothResultRepository results, BoothRepository booths,
                         AssemblyRepository assemblies, VoterRepository voters, AuditService audit) {
        this.candidates = candidates;
        this.results = results;
        this.booths = booths;
        this.assemblies = assemblies;
        this.voters = voters;
        this.audit = audit;
    }

    public Contest contest(Election election, int assemblyNo) {
        List<Candidate> cands = candidates.findByElectionIdAndAssemblyNoOrderByBallotNoAscNameAsc(election.getId(), assemblyNo);
        List<BoothResult> rows = results.findByElectionIdAndAssemblyNo(election.getId(), assemblyNo);

        Map<Long, Long> perCandidate = new HashMap<>();
        Map<Integer, Map<Long, Integer>> perBooth = new TreeMap<>();
        for (BoothResult r : rows) {
            perCandidate.merge(r.getCandidate().getId(), (long) r.getVotes(), Long::sum);
            perBooth.computeIfAbsent(r.getBoothNo(), b -> new HashMap<>()).put(r.getCandidate().getId(), r.getVotes());
        }
        long total = perCandidate.values().stream().mapToLong(Long::longValue).sum();

        List<Candidate> sorted = new ArrayList<>(cands);
        sorted.sort(Comparator.comparingLong((Candidate c) -> perCandidate.getOrDefault(c.getId(), 0L)).reversed());
        List<CandidateTotal> totals = new ArrayList<>();
        for (int i = 0; i < sorted.size(); i++) {
            long v = perCandidate.getOrDefault(sorted.get(i).getId(), 0L);
            totals.add(new CandidateTotal(sorted.get(i), v, total == 0 ? 0 : 100.0 * v / total, i + 1));
        }

        Map<Integer, String> boothNames = booths.findByAssemblyNoOrderByBoothNoAsc(assemblyNo).stream()
                .collect(Collectors.toMap(Booth::getBoothNo, Booth::getName, (a, b) -> a));
        Map<Long, Candidate> byId = cands.stream().collect(Collectors.toMap(Candidate::getId, c -> c));
        List<BoothRow> boothRows = new ArrayList<>();
        perBooth.forEach((booth, votes) -> {
            long t = votes.values().stream().mapToLong(Integer::longValue).sum();
            Candidate leader = votes.entrySet().stream().max(Map.Entry.comparingByValue())
                    .map(e -> byId.get(e.getKey())).orElse(null);
            boothRows.add(new BoothRow(booth, boothNames.getOrDefault(booth, ""), votes, t, leader));
        });

        int boothsTotal = boothNames.isEmpty() ? voters.findBoothsOfAssembly(assemblyNo).size() : boothNames.size();
        String name = assemblies.findByAssemblyNo(assemblyNo).map(Assembly::getName).orElse("");
        return new Contest(election, assemblyNo, name, totals, total, voters.countByAssemblyNo(assemblyNo),
                boothRows, perBooth.size(), boothsTotal);
    }

    /** All seats contested in an election. */
    public List<Contest> electionContests(Election election) {
        return candidates.findByElectionIdOrderByAssemblyNoAscBallotNoAsc(election.getId()).stream()
                .map(Candidate::getAssemblyNo).distinct().sorted()
                .map(a -> contest(election, a)).toList();
    }

    /** Candidates of a seat with the votes already entered for one booth. */
    public Map<Candidate, Integer> boothEntry(Long electionId, int assemblyNo, int boothNo) {
        Map<Long, Integer> existing = results.findByElectionIdAndAssemblyNoAndBoothNo(electionId, assemblyNo, boothNo).stream()
                .collect(Collectors.toMap(r -> r.getCandidate().getId(), BoothResult::getVotes));
        Map<Candidate, Integer> m = new LinkedHashMap<>();
        for (Candidate c : candidates.findByElectionIdAndAssemblyNoOrderByBallotNoAscNameAsc(electionId, assemblyNo))
            m.put(c, existing.get(c.getId()));
        return m;
    }

    public List<Integer> reportedBooths(Long electionId, int assemblyNo) {
        return results.reportedBooths(electionId, assemblyNo);
    }

    @Transactional
    public void saveBooth(Long electionId, int assemblyNo, int boothNo, Map<Long, Integer> votes, String user) {
        Map<Long, Candidate> valid = candidates.findByElectionIdAndAssemblyNoOrderByBallotNoAscNameAsc(electionId, assemblyNo)
                .stream().collect(Collectors.toMap(Candidate::getId, c -> c));
        long total = 0;
        for (Map.Entry<Long, Integer> e : votes.entrySet()) {
            Candidate c = valid.get(e.getKey());
            if (c == null || e.getValue() == null) continue;   // ignore ids that don't belong to this seat
            BoothResult r = results.findByCandidateIdAndBoothNo(c.getId(), boothNo).orElseGet(BoothResult::new);
            r.setElectionId(electionId);
            r.setAssemblyNo(assemblyNo);
            r.setBoothNo(boothNo);
            r.setCandidate(c);
            r.setVotes(Math.max(0, e.getValue()));
            r.setEnteredBy(user);
            results.save(r);
            total += r.getVotes();
        }
        audit.log("RESULT_SAVE", "RESULT", electionId + "/" + assemblyNo + "/" + boothNo, "total votes " + total);
    }
}
