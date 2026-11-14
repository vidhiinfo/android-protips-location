package com.vidhi.voters.dashboard;

import com.vidhi.voters.master.Election;
import com.vidhi.voters.model.Gender;
import com.vidhi.voters.repo.VoterRepository;
import com.vidhi.voters.result.ResultService;
import com.vidhi.voters.web.Messages;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.*;

/**
 * Numbers behind the campaign dashboard for one assembly: who the voters are (age, gender, booth size),
 * how to reach them (mobile, households, young voters) and how the focus party did last time, booth by booth.
 * Returned as plain maps/lists that are serialised to JSON for the charts.
 */
@Service
@Transactional(readOnly = true)
public class DashboardService {

    private final VoterRepository voters;
    private final ResultService results;
    private final Messages msg;

    public DashboardService(VoterRepository voters, ResultService results, Messages msg) {
        this.voters = voters;
        this.results = results;
        this.msg = msg;
    }

    private static long l(Object o) { return o == null ? 0 : ((Number) o).longValue(); }
    private static double pct(long part, long whole) { return whole == 0 ? 0 : Math.round(1000.0 * part / whole) / 10.0; }

    private static Map<String, Object> series(String name, String role, List<? extends Number> data) {
        Map<String, Object> m = new LinkedHashMap<>();
        m.put("name", name);
        m.put("role", role);     // colour role, mapped to the palette in dashboard.js
        m.put("data", data);
        return m;
    }

    private static Map<String, Object> chart(List<String> labels, List<Map<String, Object>> series) {
        Map<String, Object> m = new LinkedHashMap<>();
        m.put("labels", labels);
        m.put("series", series);
        return m;
    }

    public Map<String, Object> build(int assemblyNo, Election election, String focusParty) {
        Map<String, Object> out = new LinkedHashMap<>();
        Map<String, Object> tiles = new LinkedHashMap<>();

        // ---------------- booth-level voter numbers
        List<String> booths = new ArrayList<>();
        List<Long> male = new ArrayList<>(), female = new ArrayList<>(), other = new ArrayList<>(), young = new ArrayList<>();
        List<Double> mobilePct = new ArrayList<>();
        Map<Integer, Long> boothVoters = new HashMap<>();
        long total = 0, withMobile = 0, youngAll = 0, seniorAll = 0, otherAll = 0;
        for (Object[] r : voters.boothSummary(assemblyNo)) {
            long t = l(r[3]);
            booths.add(String.valueOf(r[1]));
            boothVoters.put((Integer) r[1], t);
            male.add(l(r[4]));
            female.add(l(r[5]));
            other.add(l(r[6]));
            mobilePct.add(pct(l(r[7]), t));
            young.add(l(r[8]));
            total += t; withMobile += l(r[7]); youngAll += l(r[8]); seniorAll += l(r[9]); otherAll += l(r[6]);
        }
        List<Map<String, Object>> boothSeries = new ArrayList<>(List.of(
                series(msg.get("gender.MALE"), "male", male), series(msg.get("gender.FEMALE"), "female", female)));
        if (otherAll > 0) boothSeries.add(series(msg.get("gender.OTHER"), "other", other));
        out.put("boothVoters", chart(booths, boothSeries));
        out.put("mobile", chart(booths, List.of(series(msg.get("report.mobilePct"), "single", mobilePct))));
        out.put("young", chart(booths, List.of(series(msg.get("dash.tile.young"), "single", young))));

        // ---------------- age x gender
        int[][] groups = {{18, 25}, {26, 35}, {36, 45}, {46, 60}, {61, 200}};
        long[][] ag = new long[groups.length][3];
        for (Object[] r : voters.ageGender(assemblyNo, null)) {
            Integer age = (Integer) r[0];
            if (age == null) continue;
            for (int i = 0; i < groups.length; i++)
                if (age >= groups[i][0] && age <= groups[i][1]) { ag[i][((Gender) r[1]).ordinal()] += l(r[2]); break; }
        }
        List<String> ageLabels = new ArrayList<>();
        for (int[] g : groups) ageLabels.add(g[1] >= 200 ? (g[0] - 1) + "+" : g[0] + "-" + g[1]);
        List<Map<String, Object>> ageSeries = new ArrayList<>(List.of(
                series(msg.get("gender.MALE"), "male", Arrays.stream(ag).map(a -> a[0]).toList()),
                series(msg.get("gender.FEMALE"), "female", Arrays.stream(ag).map(a -> a[1]).toList())));
        if (otherAll > 0) ageSeries.add(series(msg.get("gender.OTHER"), "other", Arrays.stream(ag).map(a -> a[2]).toList()));
        out.put("ageGender", chart(ageLabels, ageSeries));

        // ---------------- households
        long[] sizes = new long[6];   // 1,2,3,4,5,6+
        long houses = 0, big = 0, housed = 0;
        for (Long n : voters.householdSizes(assemblyNo)) {
            sizes[(int) Math.min(n, 6) - 1]++;
            houses++;
            housed += n;
            if (n >= 5) big++;
        }
        out.put("household", chart(List.of("1", "2", "3", "4", "5", "6+"),
                List.of(series(msg.get("dash.houses"), "single", Arrays.stream(sizes).boxed().toList()))));

        tiles.put("list.totalVoters", total);
        tiles.put("report.booths", (long) booths.size());
        tiles.put("dash.tile.young", youngAll + " (" + pct(youngAll, total) + "%)");
        tiles.put("dash.tile.senior", seniorAll + " (" + pct(seniorAll, total) + "%)");
        tiles.put("dash.tile.mobile", withMobile + " (" + pct(withMobile, total) + "%)");
        tiles.put("dash.tile.households", houses);
        tiles.put("dash.tile.avgHousehold", houses == 0 ? "0" : String.format(Locale.ROOT, "%.1f", (double) housed / houses));
        tiles.put("dash.tile.bigHouseholds", big);

        // ---------------- last election, booth by booth
        Map<String, Object> el = null;
        if (election != null) {
            ResultService.Contest c = results.contest(election, assemblyNo);
            if (c.totalVotes() > 0) {
                el = new LinkedHashMap<>();
                el.put("name", election.getName());
                List<String> partyList = c.totals().stream().map(t -> t.candidate().getPartyShort()).distinct().toList();
                String focus = focusParty != null && partyList.contains(focusParty) ? focusParty
                        : c.getWinner().candidate().getPartyShort();
                el.put("parties", partyList);
                el.put("focusParty", focus);

                List<String> pLabels = new ArrayList<>();
                List<Double> pShare = new ArrayList<>();
                for (ResultService.CandidateTotal t : c.totals()) {
                    pLabels.add(t.candidate().getPartyShort() + " - " + t.candidate().getName());
                    pShare.add(Math.round(t.share() * 10) / 10.0);
                }
                el.put("partyShare", chart(pLabels, List.of(series(msg.get("result.voteSharePct"), "single", pShare))));

                Set<Long> focusIds = new HashSet<>();
                c.totals().stream().filter(t -> t.candidate().getPartyShort().equals(focus))
                        .forEach(t -> focusIds.add(t.candidate().getId()));
                List<String> bLabels = new ArrayList<>();
                List<Long> margin = new ArrayList<>();
                List<Double> turnout = new ArrayList<>();
                int ahead = 0;
                for (ResultService.BoothRow b : c.booths()) {
                    long mine = 0, rival = 0;
                    for (Map.Entry<Long, Integer> e : b.votes().entrySet()) {
                        if (focusIds.contains(e.getKey())) mine += e.getValue();
                        else rival = Math.max(rival, e.getValue());
                    }
                    bLabels.add(String.valueOf(b.boothNo()));
                    margin.add(mine - rival);
                    if (mine > rival) ahead++;
                    turnout.add(pct(b.total(), boothVoters.getOrDefault(b.boothNo(), 0L)));
                }
                el.put("margin", chart(bLabels, List.of(series(msg.get("result.margin"), "diverging", margin))));
                el.put("turnout", chart(bLabels, List.of(series(msg.get("result.turnoutPct"), "single", turnout))));

                long focusVotes = c.totals().stream().filter(t -> focusIds.contains(t.candidate().getId()))
                        .mapToLong(ResultService.CandidateTotal::votes).sum();
                tiles.put("dash.tile.share", pct(focusVotes, c.totalVotes()) + "% (" + focus + ")");
                tiles.put("dash.tile.boothsAhead", ahead + " / " + c.booths().size());
            }
        }
        out.put("election", el);

        // translated tile labels, chart axis names
        List<Map<String, Object>> tileList = new ArrayList<>();
        tiles.forEach((k, v) -> tileList.add(Map.of("label", msg.get(k), "value", v)));
        out.put("tiles", tileList);
        out.put("text", Map.of(
                "booth", msg.get("col.boothNo"), "voters", msg.get("dash.voters"), "houses", msg.get("dash.houses"),
                "lead", msg.get("dash.lead"), "trail", msg.get("dash.trail"), "pct", "%",
                "ageGroup", msg.get("report.ageGroup"), "table", msg.get("dash.showTable"), "chart", msg.get("dash.showChart"),
                "party", msg.get("result.party")));
        return out;
    }
}
