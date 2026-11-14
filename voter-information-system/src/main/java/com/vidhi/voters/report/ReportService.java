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

    /** Report ids by section, in menu order. */
    public static final Map<String, List<String>> SECTIONS = new LinkedHashMap<>();
    static {
        SECTIONS.put("voters", List.of("assembly-summary", "booth-summary", "village-summary", "age-gender", "category-summary"));
        SECTIONS.put("outreach", List.of("first-time-voters", "senior-voters", "large-households", "phone-bank"));
        SECTIONS.put("election", List.of("results-summary", "party-performance", "booth-priority"));
        SECTIONS.put("quality", List.of("duplicate-voters"));
    }
    public static final List<String> REPORTS = SECTIONS.values().stream().flatMap(List::stream).toList();

    /** Reports with sensitive personal data (social category) - need the REPORT_SENSITIVE permission. */
    public static final Set<String> ADMIN_ONLY = Set.of("category-summary");

    /** Booth priority: a lead or trail within this share of votes polled is a swing booth. */
    static final double SWING_PCT = 10.0;

    private final VoterRepository voters;
    private final AssemblyRepository assemblies;
    private final BoothRepository booths;
    private final ResultService results;
    private final Messages msg;
    private final com.vidhi.voters.service.VoterService voterService;
    private final com.vidhi.voters.messaging.OptOutRepository optOuts;

    @org.springframework.beans.factory.annotation.Autowired
    public ReportService(VoterRepository voters, AssemblyRepository assemblies, BoothRepository booths,
                         ResultService results, Messages msg, com.vidhi.voters.service.VoterService voterService,
                         com.vidhi.voters.messaging.OptOutRepository optOuts) {
        this.voterService = voterService;
        this.optOuts = optOuts;
        this.voters = voters;
        this.assemblies = assemblies;
        this.booths = booths;
        this.results = results;
        this.msg = msg;
    }

    /** Extra report options. scope: "assembly" | "ward" (duplicates); party: focus party short name (booth priority). */
    public record Options(String scope, String party) {
        public static final Options NONE = new Options(null, null);
    }

    public Report build(String id, Integer assemblyNo, Integer boothNo, Election election) {
        return build(id, assemblyNo, boothNo, election, Options.NONE);
    }

    public Report build(String id, Integer assemblyNo, Integer boothNo, Election election, Options o) {
        return switch (id) {
            case "category-summary" -> categorySummary(assemblyNo);
            case "first-time-voters" -> voterList("first-time-voters", assemblyNo, boothNo, 18, 21, false);
            case "senior-voters" -> voterList("senior-voters", assemblyNo, boothNo, 85, null, false);
            case "phone-bank" -> voterList("phone-bank", assemblyNo, boothNo, null, null, true);
            case "large-households" -> largeHouseholds(assemblyNo);
            case "booth-priority" -> boothPriority(election, assemblyNo, o.party());
            case "duplicate-voters" -> duplicates(assemblyNo, "ward".equals(o.scope()));
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

    // ---------------------------------------------------------------- social category
    Report categorySummary(Integer assemblyNo) {
        String[] cats = {"GEN", "OBC", "SC", "ST"};
        Report r = new Report(msg.get("report.category-summary"), h("col.assemblyNo", "col.boothNo", "col.boothName", "report.cat.GEN",
                "report.cat.OBC", "report.cat.SC", "report.cat.ST", "report.cat.other", "report.cat.none", "report.total"))
                .subtitle(scope(assemblyNo, null) + " | " + msg.get("report.sensitiveNote"));
        Map<String, long[]> byBooth = new LinkedHashMap<>();   // "ac/booth" -> GEN, OBC, SC, ST, other, none
        Map<String, Integer[]> keys = new LinkedHashMap<>();
        for (Object[] row : voters.categoryByBooth(assemblyNo)) {
            String k = row[0] + "/" + row[1];
            keys.putIfAbsent(k, new Integer[]{(Integer) row[0], (Integer) row[1]});
            long[] a = byBooth.computeIfAbsent(k, x -> new long[6]);
            String c = (String) row[2];
            int idx = c.isEmpty() ? 5 : 4;
            for (int i = 0; i < cats.length; i++) if (cats[i].equals(c) || (i == 0 && (c.equals("GENERAL") || c.equals("UR")))) idx = i;
            a[idx] += l(row[3]);
        }
        long[] tot = new long[6];
        byBooth.forEach((k, a) -> {
            Integer[] ab = keys.get(k);
            String name = booths.findByAssemblyNoAndBoothNo(ab[0], ab[1]).map(Booth::getName).orElse("");
            long t = Arrays.stream(a).sum();
            r.addRow(List.of(ab[0], ab[1], name, a[0], a[1], a[2], a[3], a[4], a[5], t));
            for (int i = 0; i < 6; i++) tot[i] += a[i];
        });
        long g = Arrays.stream(tot).sum();
        r.setTotals(List.of(msg.get("report.total"), "", "", tot[0], tot[1], tot[2], tot[3], tot[4], tot[5], g));
        r.addRow(List.of("%", "", "", pct(tot[0], g), pct(tot[1], g), pct(tot[2], g), pct(tot[3], g), pct(tot[4], g), pct(tot[5], g), g == 0 ? 0.0 : 100.0));
        return r;
    }

    // ---------------------------------------------------------------- outreach lists
    private com.vidhi.voters.service.VoterFilter filter(Integer assemblyNo, Integer boothNo) {
        com.vidhi.voters.service.VoterFilter f = new com.vidhi.voters.service.VoterFilter();
        f.setAssemblyNo(assemblyNo);
        f.setBoothNo(boothNo);
        f.setSort("boothNo");
        return f;
    }

    /** First-time voters (18-21), voters aged 85+ (home voting) or the phone-bank list (voters with a mobile, minus opt-outs). */
    Report voterList(String id, Integer assemblyNo, Integer boothNo, Integer ageMin, Integer ageMax, boolean phoneBank) {
        com.vidhi.voters.service.VoterFilter f = filter(assemblyNo, boothNo);
        f.setAgeMin(ageMin);
        f.setAgeMax(ageMax);
        if (phoneBank) f.setHasMobile(true);
        Set<String> blocked = phoneBank ? optOuts.blockedEverywhere() : Set.of();
        Report r = new Report(msg.get("report." + id), h("col.assemblyNo", "col.boothNo", "col.serialNo", "col.epicNo", "col.name",
                "col.nameLocal", "col.relativeName", "col.gender", "col.age", "col.houseNo", "col.address", "col.mobile"))
                .subtitle(scope(assemblyNo, boothNo));
        int skipped = 0;
        for (com.vidhi.voters.model.Voter v : voterService.searchAll(f)) {
            if (phoneBank && blocked.contains(v.getMobile())) { skipped++; continue; }
            r.addRow(Arrays.asList(v.getAssemblyNo(), v.getBoothNo(), v.getSerialNo(), v.getEpicNo(), v.getName(), v.getNameLocal(),
                    v.getRelativeName(), v.getGender() == null ? "" : msg.get("gender." + v.getGender()), v.getAge(), v.getHouseNo(),
                    v.getFullAddress(), v.getMobile()));
        }
        r.subtitle(r.getSubtitle() + " | " + msg.get("report.count", r.getRows().size())
                + (phoneBank && skipped > 0 ? " | " + msg.get("report.optOutsExcluded", skipped) : ""));
        return r;
    }

    Report largeHouseholds(Integer assemblyNo) {
        Report r = new Report(msg.get("report.large-households"), h("col.assemblyNo", "col.boothNo", "col.houseNo", "col.address",
                "col.villageWard", "report.votersInHouse", "report.withMobile", "report.members")).subtitle(scope(assemblyNo, null));
        Map<String, List<com.vidhi.voters.model.Voter>> houses = new LinkedHashMap<>();
        for (com.vidhi.voters.model.Voter v : voterService.searchAll(filter(assemblyNo, null))) {
            if (v.getHouseNo() == null || v.getHouseNo().isBlank()) continue;
            String k = v.getAssemblyNo() + "|" + v.getHouseNo().trim().toLowerCase(Locale.ROOT) + "|"
                    + String.valueOf(v.getAddress()).trim().toLowerCase(Locale.ROOT) + "|" + String.valueOf(v.getVillageWard()).trim().toLowerCase(Locale.ROOT);
            houses.computeIfAbsent(k, x -> new ArrayList<>()).add(v);
        }
        houses.values().stream().filter(l -> l.size() >= 5)
                .sorted((a, b) -> b.size() - a.size())
                .forEach(l -> {
                    com.vidhi.voters.model.Voter v = l.get(0);
                    String names = String.join(", ", l.stream().map(x -> x.getName() + (x.getAge() == null ? "" : " (" + x.getAge() + ")")).toList());
                    r.addRow(Arrays.asList(v.getAssemblyNo(), v.getBoothNo(), v.getHouseNo(), v.getAddress(), v.getVillageWard(),
                            (long) l.size(), l.stream().filter(x -> x.getMobile() != null && !x.getMobile().isBlank()).count(), names));
                });
        r.subtitle(r.getSubtitle() + " | " + msg.get("report.count", r.getRows().size()));
        return r;
    }

    // ---------------------------------------------------------------- swing booths
    Report boothPriority(Election e, Integer assemblyNo, String party) {
        Report r = new Report(msg.get("report.booth-priority"), h("col.assemblyNo", "col.boothNo", "col.boothName", "report.priority",
                "dash.focusParty", "result.votes", "report.topRival", "result.votes", "result.margin", "report.marginPct",
                "result.turnoutPct", "dash.voters"));
        if (e == null) return r.subtitle(msg.get("result.noElection"));
        r.subtitle(e.getName() + " | " + msg.get("report.priorityNote", (int) SWING_PCT));
        List<Object[]> rows = new ArrayList<>();
        for (ResultService.Contest c : contests(e, assemblyNo)) {
            if (c.getWinner() == null) continue;
            String focus = party != null && !party.isBlank() ? party : c.getWinner().candidate().getPartyShort();
            Map<Long, String> partyOf = new HashMap<>();
            c.totals().forEach(t -> partyOf.put(t.candidate().getId(), t.candidate().getPartyShort()));
            Map<Integer, Long> electors = new HashMap<>();
            for (Object[] b : voters.boothSummary(c.assemblyNo())) electors.put((Integer) b[1], l(b[3]));
            for (ResultService.BoothRow b : c.booths()) {
                long mine = 0, rival = 0;
                String rivalParty = "";
                for (Map.Entry<Long, Integer> en : b.votes().entrySet()) {
                    String p = partyOf.get(en.getKey());
                    if (focus.equals(p)) mine += en.getValue();
                    else if (en.getValue() > rival) { rival = en.getValue(); rivalParty = p; }
                }
                long margin = mine - rival;
                double mPct = b.total() == 0 ? 0 : Math.round(1000.0 * margin / b.total()) / 10.0;
                String level = Math.abs(mPct) < SWING_PCT ? "SWING" : mPct < 0 ? "WEAK" : "STRONG";
                long el = electors.getOrDefault(b.boothNo(), 0L);
                rows.add(new Object[]{c.assemblyNo(), b.boothNo(), b.boothName(), level, focus, mine, rivalParty, rival, margin, mPct,
                        pct(b.total(), el), el});
            }
        }
        Map<String, Integer> order = Map.of("SWING", 0, "WEAK", 1, "STRONG", 2);
        rows.sort(Comparator.comparingInt((Object[] x) -> order.get((String) x[3]))
                .thenComparingDouble(x -> Math.abs((Double) x[9])));
        for (Object[] x : rows) {
            x[3] = msg.get("report.priority." + x[3]);
            r.addRow(Arrays.asList(x));
        }
        return r;
    }

    // ---------------------------------------------------------------- duplicates
    /** Letters and digits only, lower case, NFC: "R. K.  Sharma" and "r k sharma" give the same key. */
    static String nameKey(String s) {
        if (s == null) return "";
        String n = java.text.Normalizer.normalize(s, java.text.Normalizer.Form.NFC).toLowerCase(Locale.ROOT);
        StringBuilder b = new StringBuilder();
        n.codePoints().filter(c -> Character.isLetterOrDigit(c) || Character.getType(c) == Character.NON_SPACING_MARK
                || Character.getType(c) == Character.COMBINING_SPACING_MARK).forEach(b::appendCodePoint);
        return b.toString();
    }

    /**
     * Possible duplicate registrations: same voter name and same father/husband name
     * within the same assembly, or (byWard) within the same village/ward of an assembly.
     */
    Report duplicates(Integer assemblyNo, boolean byWard) {
        Report r = new Report(msg.get("report.duplicate-voters"), h("report.group", "col.assemblyNo", "col.villageWard", "col.boothNo",
                "col.serialNo", "col.epicNo", "col.name", "col.relativeName", "col.gender", "col.age", "col.houseNo", "col.address", "col.mobile"));
        Map<String, List<com.vidhi.voters.model.Voter>> groups = new LinkedHashMap<>();
        for (com.vidhi.voters.model.Voter v : voterService.searchAll(filter(assemblyNo, null))) {
            String n = nameKey(v.getName());
            if (n.isEmpty()) continue;
            String k = v.getAssemblyNo() + "|" + (byWard ? nameKey(v.getVillageWard()) + "|" : "") + n + "|" + nameKey(v.getRelativeName());
            groups.computeIfAbsent(k, x -> new ArrayList<>()).add(v);
        }
        int g = 0, people = 0;
        for (List<com.vidhi.voters.model.Voter> list : groups.values()) {
            if (list.size() < 2) continue;
            g++;
            people += list.size();
            for (com.vidhi.voters.model.Voter v : list)
                r.addRow(Arrays.asList(g, v.getAssemblyNo(), v.getVillageWard(), v.getBoothNo(), v.getSerialNo(), v.getEpicNo(), v.getName(),
                        v.getRelativeName(), v.getGender() == null ? "" : msg.get("gender." + v.getGender()), v.getAge(), v.getHouseNo(),
                        v.getFullAddress(), v.getMobile()));
        }
        r.subtitle(scope(assemblyNo, null) + " | " + msg.get(byWard ? "report.dup.byWard" : "report.dup.byAssembly")
                + " | " + msg.get("report.dup.found", g, people));
        return r;
    }
}
