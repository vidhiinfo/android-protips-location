package com.vidhi.voters.report;

import com.vidhi.voters.master.*;
import com.vidhi.voters.model.Gender;
import com.vidhi.voters.repo.VoterRepository;
import com.vidhi.voters.result.ResultService;
import com.vidhi.voters.web.Messages;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.*;

/** Builds the reports offered in the Reports menu. All labels come from messages_*.properties. */
@Service
@Transactional(readOnly = true)
public class ReportService {

    /** Report ids, in menu order. */
    public static final List<String> REPORTS = List.of(
            "assembly-summary", "booth-summary", "village-summary", "age-gender", "results-summary", "party-performance");

    private final VoterRepository voters;
    private final AssemblyRepository assemblies;
    private final BoothRepository booths;
    private final ResultService results;
    private final Messages msg;

    public ReportService(VoterRepository voters, AssemblyRepository assemblies, BoothRepository booths,
                         ResultService results, Messages msg) {
        this.voters = voters;
        this.assemblies = assemblies;
        this.booths = booths;
        this.results = results;
        this.msg = msg;
    }

    public Report build(String id, Integer assemblyNo, Integer boothNo, Election election) {
        return switch (id) {
            case "assembly-summary" -> assemblySummary(assemblyNo);
            case "booth-summary" -> boothSummary(assemblyNo);
            case "village-summary" -> villageSummary(assemblyNo);
            case "age-gender" -> ageGender(assemblyNo, boothNo);
            case "results-summary" -> resultsSummary(election, assemblyNo);
            case "party-performance" -> partyPerformance(election, assemblyNo);
            default -> throw new IllegalArgumentException(id);
        };
    }

    private List<String> h(String... keys) { return Arrays.stream(keys).map(msg::get).toList(); }

    private String assemblyName(Integer no, String fallback) {
        return assemblies.findByAssemblyNo(no).map(Assembly::getName).orElse(fallback == null ? "" : fallback);
    }

    private String scope(Integer assemblyNo, Integer boothNo) {
        StringBuilder s = new StringBuilder();
        if (assemblyNo != null) s.append(msg.get("col.assemblyNo")).append(" ").append(assemblyNo).append(" ").append(assemblyName(assemblyNo, ""));
        if (boothNo != null) s.append(s.isEmpty() ? "" : ", ").append(msg.get("col.boothNo")).append(" ").append(boothNo);
        return s.isEmpty() ? msg.get("report.allAssemblies") : s.toString().trim();
    }

    private static long l(Object o) { return o == null ? 0 : ((Number) o).longValue(); }
    private static double pct(long part, long whole) { return whole == 0 ? 0 : Math.round(10000.0 * part / whole) / 100.0; }

    // ---------------------------------------------------------------- voter reports
    Report assemblySummary(Integer only) {
        Report r = new Report(msg.get("report.assembly-summary"), h("col.assemblyNo", "col.assemblyName", "report.booths",
                "report.total", "gender.MALE", "gender.FEMALE", "gender.OTHER", "report.withMobile", "report.mobilePct",
                "report.age18to25", "report.age60plus"));
        long[] tot = new long[9];
        for (Object[] row : voters.assemblySummary()) {
            if (only != null && !only.equals(row[0])) continue;
            long total = l(row[3]);
            r.addRow(List.of(row[0], assemblyName((Integer) row[0], (String) row[1]), l(row[2]), total, l(row[4]), l(row[5]), l(row[6]),
                    l(row[7]), pct(l(row[7]), total), l(row[8]), l(row[9])));
            for (int i = 2; i <= 9; i++) tot[i - 2] += l(row[i]);
        }
        r.setTotals(List.of(msg.get("report.total"), "", tot[0], tot[1], tot[2], tot[3], tot[4], tot[5], pct(tot[5], tot[1]), tot[6], tot[7]));
        return r;
    }

    Report boothSummary(Integer assemblyNo) {
        Report r = new Report(msg.get("report.booth-summary"), h("col.assemblyNo", "col.boothNo", "col.boothName",
                "report.total", "gender.MALE", "gender.FEMALE", "gender.OTHER", "report.genderRatio", "report.withMobile",
                "report.mobilePct", "report.age18to25", "report.age60plus")).subtitle(scope(assemblyNo, null));
        long[] tot = new long[7];
        for (Object[] row : voters.boothSummary(assemblyNo)) {
            long total = l(row[3]), male = l(row[4]), female = l(row[5]);
            String name = booths.findByAssemblyNoAndBoothNo((Integer) row[0], (Integer) row[1]).map(Booth::getName)
                    .orElse(row[2] == null ? "" : (String) row[2]);
            r.addRow(List.of(row[0], row[1], name, total, male, female, l(row[6]), ratio(female, male), l(row[7]),
                    pct(l(row[7]), total), l(row[8]), l(row[9])));
            for (int i = 3; i <= 9; i++) tot[i - 3] += l(row[i]);
        }
        r.setTotals(List.of(msg.get("report.total"), "", "", tot[0], tot[1], tot[2], tot[3], ratio(tot[2], tot[1]), tot[4],
                pct(tot[4], tot[0]), tot[5], tot[6]));
        return r;
    }

    /** Females per 1000 males, as used by the Election Commission. */
    private static long ratio(long female, long male) { return male == 0 ? 0 : Math.round(1000.0 * female / male); }

    Report villageSummary(Integer assemblyNo) {
        Report r = new Report(msg.get("report.village-summary"), h("col.villageWard", "report.total", "gender.MALE",
                "gender.FEMALE", "gender.OTHER", "report.withMobile", "report.mobilePct", "report.age18to25", "report.age60plus"))
                .subtitle(scope(assemblyNo, null));
        for (Object[] row : voters.villageSummary(assemblyNo)) {
            long total = l(row[1]);
            r.addRow(List.of(row[0], total, l(row[2]), l(row[3]), l(row[4]), l(row[5]), pct(l(row[5]), total), l(row[6]), l(row[7])));
        }
        return r;
    }

    Report ageGender(Integer assemblyNo, Integer boothNo) {
        int[][] groups = {{18, 25}, {26, 35}, {36, 45}, {46, 60}, {61, 80}, {81, 200}};
        long[][] counts = new long[groups.length + 1][3];   // last row: age unknown
        for (Object[] row : voters.ageGender(assemblyNo, boothNo)) {
            Integer age = (Integer) row[0];
            int g = ((Gender) row[1]).ordinal();
            int idx = groups.length;
            if (age != null) for (int i = 0; i < groups.length; i++) if (age >= groups[i][0] && age <= groups[i][1]) { idx = i; break; }
            counts[idx][g] += l(row[2]);
        }
        Report r = new Report(msg.get("report.age-gender"), h("report.ageGroup", "gender.MALE", "gender.FEMALE",
                "gender.OTHER", "report.total", "report.sharePct")).subtitle(scope(assemblyNo, boothNo));
        long grand = Arrays.stream(counts).flatMapToLong(Arrays::stream).sum();
        long[] tot = new long[3];
        for (int i = 0; i <= groups.length; i++) {
            long t = counts[i][0] + counts[i][1] + counts[i][2];
            String label = i == groups.length ? msg.get("report.ageUnknown")
                    : groups[i][1] >= 200 ? groups[i][0] + "+" : groups[i][0] + "-" + groups[i][1];
            if (i == groups.length && t == 0) continue;
            r.addRow(List.of(label, counts[i][0], counts[i][1], counts[i][2], t, pct(t, grand)));
            for (int g = 0; g < 3; g++) tot[g] += counts[i][g];
        }
        r.setTotals(List.of(msg.get("report.total"), tot[0], tot[1], tot[2], grand, grand == 0 ? 0.0 : 100.0));
        return r;
    }

    // ---------------------------------------------------------------- election reports
    private List<ResultService.Contest> contests(Election e, Integer only) {
        return results.electionContests(e).stream().filter(c -> only == null || c.assemblyNo() == only).toList();
    }

    Report resultsSummary(Election e, Integer only) {
        Report r = new Report(msg.get("report.results-summary"), h("col.assemblyNo", "col.assemblyName", "result.winner",
                "result.party", "result.votes", "result.runnerUp", "result.party", "result.margin", "result.totalVotes",
                "result.turnoutPct", "result.boothsReported"));
        if (e == null) return r.subtitle(msg.get("result.noElection"));
        r.subtitle(e.getName());
        for (ResultService.Contest c : contests(e, only)) {
            var w = c.getWinner();
            var ru = c.getRunnerUp();
            r.addRow(List.of(c.assemblyNo(), c.assemblyName(),
                    w == null ? "-" : w.candidate().getName(), w == null ? "" : w.candidate().getPartyShort(), w == null ? 0L : w.votes(),
                    ru == null ? "-" : ru.candidate().getName(), ru == null ? "" : ru.candidate().getPartyShort(),
                    c.getMargin(), c.totalVotes(), Math.round(c.getTurnout() * 100) / 100.0, c.boothsReported() + "/" + c.boothsTotal()));
        }
        return r;
    }

    Report partyPerformance(Election e, Integer only) {
        Report r = new Report(msg.get("report.party-performance"), h("result.party", "result.contested", "result.won",
                "result.votes", "result.voteSharePct"));
        if (e == null) return r.subtitle(msg.get("result.noElection"));
        r.subtitle(e.getName());
        Map<String, long[]> m = new TreeMap<>();   // party -> contested, won, votes
        long all = 0;
        for (ResultService.Contest c : contests(e, only)) {
            for (ResultService.CandidateTotal t : c.totals()) {
                long[] a = m.computeIfAbsent(t.candidate().getPartyShort(), k -> new long[3]);
                a[0]++;
                if (c.getWinner() != null && c.getWinner().candidate().getId().equals(t.candidate().getId())) a[1]++;
                a[2] += t.votes();
            }
            all += c.totalVotes();
        }
        final long allVotes = all;
        m.entrySet().stream().sorted((a, b) -> Long.compare(b.getValue()[1] * 1_000_000_000L + b.getValue()[2], a.getValue()[1] * 1_000_000_000L + a.getValue()[2]))
                .forEach(en -> r.addRow(List.of(en.getKey(), en.getValue()[0], en.getValue()[1], en.getValue()[2], pct(en.getValue()[2], allVotes))));
        r.setTotals(List.of(msg.get("report.total"), m.values().stream().mapToLong(a -> a[0]).sum(),
                m.values().stream().mapToLong(a -> a[1]).sum(), allVotes, allVotes == 0 ? 0.0 : 100.0));
        return r;
    }
}
