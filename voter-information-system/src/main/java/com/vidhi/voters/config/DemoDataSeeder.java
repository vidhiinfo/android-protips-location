package com.vidhi.voters.config;

import com.vidhi.voters.admin.AppUser;
import com.vidhi.voters.admin.AppUserRepository;
import com.vidhi.voters.master.*;
import com.vidhi.voters.model.Gender;
import com.vidhi.voters.model.RelationType;
import com.vidhi.voters.model.Voter;
import com.vidhi.voters.repo.VoterRepository;
import com.vidhi.voters.result.BoothResult;
import com.vidhi.voters.result.BoothResultRepository;
import org.springframework.boot.CommandLineRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDate;
import java.util.*;

/**
 * Sample data for the in-memory "demo" profile: three assemblies whose voters have local-language
 * names in Hindi, Urdu and Bengali, families sharing a house, masters, an election with booth results,
 * and one user per role. Fictional names and parties.
 */
@Component
@Profile("demo")
@org.springframework.core.annotation.Order(10)
public class DemoDataSeeder implements CommandLineRunner {

    /** English / local spelling pairs for one region. */
    private record Region(int assemblyNo, String assembly, String assemblyLocal, String district, String state,
                          String[][] male, String[][] female, String[][] surname, String[][] areas, String streetLocal) {}

    private static final List<Region> REGIONS = List.of(
            new Region(45, "Jaipur North", "जयपुर उत्तर", "Jaipur", "Rajasthan",
                    pairs("Ramesh:रमेश", "Suresh:सुरेश", "Mahesh:महेश", "Rajesh:राजेश", "Amit:अमित", "Vikas:विकास",
                          "Sunil:सुनील", "Anil:अनिल", "Deepak:दीपक", "Manoj:मनोज", "Rohit:रोहित", "Sanjay:संजय"),
                    pairs("Sunita:सुनीता", "Anita:अनीता", "Kavita:कविता", "Pooja:पूजा", "Neha:नेहा", "Priya:प्रिया",
                          "Rekha:रेखा", "Seema:सीमा", "Geeta:गीता", "Meena:मीना", "Ritu:रितु", "Asha:आशा"),
                    pairs("Sharma:शर्मा", "Verma:वर्मा", "Saini:सैनी", "Gupta:गुप्ता", "Singh:सिंह", "Yadav:यादव",
                          "Meena:मीणा", "Jain:जैन", "Choudhary:चौधरी"),
                    pairs("Shastri Nagar:शास्त्री नगर", "Malviya Nagar:मालवीय नगर", "Vaishali Nagar:वैशाली नगर", "Ward 5:वार्ड 5"),
                    "गली"),
            new Region(46, "Lucknow West", "لکھنؤ مغرب", "Lucknow", "Uttar Pradesh",
                    pairs("Imran:عمران", "Salman:سلمان", "Arif:عارف", "Faisal:فیصل", "Javed:جاوید", "Naved:نوید",
                          "Rizwan:رضوان", "Tariq:طارق", "Asif:آصف", "Shahid:شاہد"),
                    pairs("Ayesha:عائشہ", "Fatima:فاطمہ", "Zainab:زینب", "Sana:ثنا", "Nazia:نازیہ", "Rubina:روبینہ",
                          "Shabana:شبانہ", "Farhana:فرحانہ", "Noor:نور", "Saima:صائمہ"),
                    pairs("Khan:خان", "Ansari:انصاری", "Siddiqui:صدیقی", "Qureshi:قریشی", "Rizvi:رضوی", "Hashmi:ہاشمی"),
                    pairs("Aminabad:امین آباد", "Chowk:چوک", "Nakhas:نخاس"),
                    "گلی"),
            new Region(52, "Kolkata Port", "কলকাতা বন্দর", "Kolkata", "West Bengal",
                    pairs("Subrata:সুব্রত", "Partha:পার্থ", "Arijit:অরিজিৎ", "Sourav:সৌরভ", "Debashis:দেবাশিস", "Amit:অমিত",
                          "Tapas:তাপস", "Biswajit:বিশ্বজিৎ", "Rajib:রাজীব", "Sanjoy:সঞ্জয়"),
                    pairs("Rina:রিনা", "Mousumi:মৌসুমী", "Sumita:সুমিতা", "Ananya:অনন্যা", "Payel:পায়েল", "Moumita:মৌমিতা",
                          "Rupa:রূপা", "Shampa:শম্পা", "Dipa:দীপা", "Tanushree:তনুশ্রী"),
                    pairs("Banerjee:ব্যানার্জি", "Chatterjee:চ্যাটার্জি", "Mukherjee:মুখার্জি", "Das:দাস", "Ghosh:ঘোষ",
                          "Bose:বসু", "Sen:সেন", "Dutta:দত্ত"),
                    pairs("Garden Reach:গার্ডেন রিচ", "Khidirpur:খিদিরপুর", "Metiabruz:মেটিয়াবুরুজ"),
                    "রাস্তা"));

    private static String[][] pairs(String... s) {
        return Arrays.stream(s).map(x -> x.split(":")).toArray(String[][]::new);
    }

    private final VoterRepository voters;
    private final AssemblyRepository assemblies;
    private final BoothRepository booths;
    private final PartyRepository parties;
    private final ElectionRepository elections;
    private final CandidateRepository candidates;
    private final BoothResultRepository results;
    private final AppUserRepository users;
    private final PasswordEncoder encoder;

    @org.springframework.beans.factory.annotation.Autowired
    private com.vidhi.voters.messaging.MessageTemplateRepository templates;

    @org.springframework.beans.factory.annotation.Autowired
    private com.vidhi.voters.admin.RoleRepository roles;

    public DemoDataSeeder(VoterRepository voters, AssemblyRepository assemblies, BoothRepository booths, PartyRepository parties,
                          ElectionRepository elections, CandidateRepository candidates, BoothResultRepository results,
                          AppUserRepository users, PasswordEncoder encoder) {
        this.voters = voters;
        this.assemblies = assemblies;
        this.booths = booths;
        this.parties = parties;
        this.elections = elections;
        this.candidates = candidates;
        this.results = results;
        this.users = users;
        this.encoder = encoder;
    }

    @Override
    @Transactional
    public void run(String... args) {
        if (voters.count() > 0) return;
        Random rnd = new Random(42);
        int epic = 1000000;
        final int boothsPerAssembly = 12;

        for (Region r : REGIONS) {
            Assembly a = new Assembly();
            a.setAssemblyNo(r.assemblyNo());
            a.setName(r.assembly());
            a.setNameLocal(r.assemblyLocal());
            a.setDistrict(r.district());
            a.setState(r.state());
            a.setReservedFor("GEN");
            assemblies.save(a);
            for (int b = 1; b <= boothsPerAssembly; b++) {
                Booth booth = new Booth();
                booth.setAssemblyNo(r.assemblyNo());
                booth.setBoothNo(b);
                booth.setName("Govt. School Room " + ((b - 1) % 4 + 1) + ", " + r.areas()[(b - 1) % r.areas().length][0]);
                booth.setVillageWard(r.areas()[(b - 1) % r.areas().length][0]);
                booths.save(booth);
            }

            // families: 1-5 voters sharing a house no. and address
            int serial = 1;
            for (int fam = 0; fam < 220; fam++) {
                String[] sur = r.surname()[rnd.nextInt(r.surname().length)];
                String[] area = r.areas()[rnd.nextInt(r.areas().length)];
                int street = 1 + rnd.nextInt(30);
                String house = String.valueOf(1 + rnd.nextInt(300));
                int booth = 1 + rnd.nextInt(boothsPerAssembly);
                String[] head = r.male()[rnd.nextInt(r.male().length)];
                String[] grandfather = r.male()[rnd.nextInt(r.male().length)];
                int headAge = 35 + rnd.nextInt(35);
                int members = 1 + rnd.nextInt(5);
                if (fam % 12 == 5) {      // an elderly grandparent (85+, eligible for home voting) in some families
                    Voter g = new Voter();
                    boolean gm = rnd.nextBoolean();
                    String[] gf = gm ? grandfather : r.female()[rnd.nextInt(r.female().length)];
                    g.setEpicNo(String.format("DEM%07d", ++epic));
                    g.setName(gf[0] + " " + sur[0]);
                    g.setNameLocal(gf[1] + " " + sur[1]);
                    g.setGender(gm ? Gender.MALE : Gender.FEMALE);
                    g.setRelationType(gm ? RelationType.FATHER : RelationType.HUSBAND);
                    String[] rel = r.male()[rnd.nextInt(r.male().length)];
                    g.setRelativeName(rel[0] + " " + sur[0]);
                    g.setRelativeNameLocal(rel[1] + " " + sur[1]);
                    g.setAge(85 + rnd.nextInt(12));
                    g.setAssemblyNo(r.assemblyNo());
                    g.setAssemblyName(r.assembly());
                    g.setBoothNo(booth);
                    g.setBoothName("Govt. School Room " + ((booth - 1) % 4 + 1) + ", " + r.areas()[(booth - 1) % r.areas().length][0]);
                    g.setSerialNo(serial++);
                    g.setHouseNo(house);
                    g.setAddress("Street " + street);
                    g.setAddressLocal(r.streetLocal() + " " + street + ", " + area[1]);
                    g.setVillageWard(area[0]);
                    g.setDistrict(r.district());
                    voters.save(g);
                }
                for (int m = 0; m < members; m++) {
                    Voter v = new Voter();
                    boolean isMale;
                    String[] first, relative;
                    RelationType rel;
                    int age;
                    if (m == 0) { isMale = true; first = head; relative = grandfather; rel = RelationType.FATHER; age = headAge; }
                    else if (m == 1) { isMale = false; first = r.female()[rnd.nextInt(r.female().length)]; relative = head; rel = RelationType.HUSBAND; age = headAge - 2 - rnd.nextInt(5); }
                    else { isMale = rnd.nextBoolean(); first = (isMale ? r.male() : r.female())[rnd.nextInt(isMale ? r.male().length : r.female().length)];
                           relative = head; rel = RelationType.FATHER; age = Math.max(18, headAge - 22 - rnd.nextInt(10)); }
                    v.setEpicNo(String.format("DEM%07d", ++epic));
                    v.setName(first[0] + " " + sur[0]);
                    v.setNameLocal(first[1] + " " + sur[1]);
                    v.setGender(isMale ? Gender.MALE : Gender.FEMALE);
                    v.setRelationType(rel);
                    v.setRelativeName(relative[0] + " " + sur[0]);
                    v.setRelativeNameLocal(relative[1] + " " + sur[1]);
                    v.setAge(age);
                    v.setAssemblyNo(r.assemblyNo());
                    v.setAssemblyName(r.assembly());
                    v.setBoothNo(booth);
                    v.setBoothName("Govt. School Room " + ((booth - 1) % 4 + 1) + ", " + r.areas()[(booth - 1) % r.areas().length][0]);
                    v.setSerialNo(serial++);
                    v.setHouseNo(house);
                    v.setAddress("Street " + street);
                    v.setAddressLocal(r.streetLocal() + " " + street + ", " + area[1]);
                    v.setVillageWard(area[0]);
                    v.setDistrict(r.district());
                    v.setPincode(String.valueOf(r.assemblyNo() == 45 ? 302001 + rnd.nextInt(9) : r.assemblyNo() == 46 ? 226001 + rnd.nextInt(9) : 700024));
                    if (rnd.nextInt(10) < 8) v.setMobile(String.valueOf(9000000000L + rnd.nextInt(999999999)));
                    int cat = rnd.nextInt(20);   // optional social category, left blank for some voters
                    v.setCategory(cat < 7 ? "GEN" : cat < 13 ? "OBC" : cat < 16 ? "SC" : cat < 18 ? "ST" : null);
                    voters.save(v);
                    // a few deliberate duplicate registrations (same name + father name, different EPIC) for the report
                    if (fam % 40 == 7 && m == 0) {
                        Voter d = new Voter();
                        d.setEpicNo(String.format("DUP%07d", ++epic));
                        d.setName(v.getName().replace(" ", "  ").toUpperCase());     // spacing/case differ, still a match
                        d.setNameLocal(v.getNameLocal());
                        d.setGender(v.getGender());
                        d.setRelationType(v.getRelationType());
                        d.setRelativeName(v.getRelativeName());
                        d.setAge(v.getAge() + 1);
                        d.setAssemblyNo(v.getAssemblyNo());
                        d.setAssemblyName(v.getAssemblyName());
                        d.setBoothNo(1 + (v.getBoothNo() % boothsPerAssembly));
                        d.setSerialNo(serial++);
                        d.setHouseNo(String.valueOf(1 + rnd.nextInt(300)));
                        d.setAddress("Street " + (1 + rnd.nextInt(30)));
                        d.setVillageWard(v.getVillageWard());
                        d.setDistrict(v.getDistrict());
                        voters.save(d);
                    }
                }
            }
        }

        // parties, election, candidates, booth results
        Party p1 = party("Jan Vikas Party", "जन विकास पार्टी", "JVP", "Lamp", "#FF9933");
        Party p2 = party("Lok Samta Dal", "लोक समता दल", "LSD", "Wheel", "#138808");
        Party p3 = party("Rashtriya Pragati Morcha", "राष्ट्रीय प्रगति मोर्चा", "RPM", "Kite", "#1E90FF");
        Party nota = party("None of the Above", "इनमें से कोई नहीं", "NOTA", "", "#808080");
        Election e = new Election();
        e.setName("Vidhan Sabha General Election 2023");
        e.setType(Election.Type.ASSEMBLY);
        e.setYear(2023);
        e.setPollDate(LocalDate.of(2023, 11, 25));
        e.setCurrent(true);
        elections.save(e);

        for (Region r : REGIONS) {
            List<Candidate> list = new ArrayList<>();
            Party[] ps = {p1, p2, p3, null, nota};
            for (int i = 0; i < ps.length; i++) {
                Candidate c = new Candidate();
                c.setElection(e);
                c.setAssemblyNo(r.assemblyNo());
                c.setParty(ps[i]);
                c.setBallotNo(i + 1);
                if (ps[i] == nota) { c.setName("NOTA"); c.setNameLocal(null); }
                else {
                    String[] f = r.male()[(i * 3 + r.assemblyNo()) % r.male().length];
                    String[] s = r.surname()[(i * 2 + 1) % r.surname().length];
                    c.setName(f[0] + " " + s[0]);
                    c.setNameLocal(f[1] + " " + s[1]);
                }
                list.add(candidates.save(c));
            }
            double[] weight = {0.38 + rnd.nextDouble() * 0.1, 0.30 + rnd.nextDouble() * 0.1, 0.18, 0.08, 0.02};
            for (int b = 1; b <= boothsPerAssembly - 2; b++) {            // last two booths not yet reported
                final int booth = b;
                long electors = voters.findAll().stream().filter(v -> v.getAssemblyNo() == r.assemblyNo() && v.getBoothNo() == booth).count();
                int polled = (int) Math.round(electors * (0.55 + rnd.nextDouble() * 0.3));   // 55-85 % turnout
                for (int i = 0; i < list.size(); i++) {
                    BoothResult br = new BoothResult();
                    br.setElectionId(e.getId());
                    br.setAssemblyNo(r.assemblyNo());
                    br.setBoothNo(b);
                    br.setCandidate(list.get(i));
                    br.setVotes((int) Math.round(polled * weight[i] * (0.75 + rnd.nextDouble() * 0.5)));
                    br.setEnteredBy("demo");
                    results.save(br);
                }
            }
        }

        // message templates (dry-run provider by default: nothing is really sent)
        template("Polling day reminder", com.vidhi.voters.messaging.Channel.SMS, "en",
                "Dear {name}, please vote on polling day. Your booth: {boothNo} {boothName}, serial no. {serialNo}. - Jan Vikas Party", "1107170000000000001", "name,boothNo,boothName,serialNo");
        template("मतदान दिवस अनुस्मारक", com.vidhi.voters.messaging.Channel.SMS, "hi",
                "प्रिय {nameLocal}, कृपया मतदान अवश्य करें। आपका बूथ: {boothNo} {boothName}, क्रम सं. {serialNo}। - जन विकास पार्टी", "1107170000000000002", "nameLocal,boothNo,boothName,serialNo");
        template("ووٹنگ یاد دہانی", com.vidhi.voters.messaging.Channel.SMS, "ur",
                "محترم {nameLocal}، براہ کرم ووٹ ضرور دیں۔ آپ کا بوتھ: {boothNo} {boothName}، سیریل نمبر {serialNo}۔", "1107170000000000003", "nameLocal,boothNo,boothName,serialNo");
        template("ভোটের অনুস্মারক", com.vidhi.voters.messaging.Channel.SMS, "bn",
                "প্রিয় {nameLocal}, অনুগ্রহ করে ভোট দিন। আপনার বুথ: {boothNo} {boothName}, ক্রম নং {serialNo}।", "1107170000000000004", "nameLocal,boothNo,boothName,serialNo");
        template("Booth slip", com.vidhi.voters.messaging.Channel.WHATSAPP, "en",
                "Namaste {name}! Your voter slip: EPIC {epicNo}, booth {boothNo} ({boothName}), serial {serialNo}.", "voter_slip", "name,epicNo,boothNo,boothName,serialNo");
        template("बूथ पर्ची", com.vidhi.voters.messaging.Channel.WHATSAPP, "hi",
                "नमस्ते {nameLocal}! आपकी मतदाता पर्ची: EPIC {epicNo}, बूथ {boothNo} ({boothName}), क्रम सं. {serialNo}।", "voter_slip", "nameLocal,epicNo,boothNo,boothName,serialNo");

        // a custom role: booth agents may look up voters and log results, nothing else
        com.vidhi.voters.admin.Role agent = roles.save(new com.vidhi.voters.admin.Role("BOOTH_AGENT", "Booth Agent",
                "Voter lookup and booth result entry", false, java.util.EnumSet.of(com.vidhi.voters.admin.Permission.VOTER_VIEW,
                com.vidhi.voters.admin.Permission.RESULT_VIEW, com.vidhi.voters.admin.Permission.RESULT_ENTRY)));
        user("operator", "Data Entry Operator", "OPERATOR", "operator@123", null);
        user("viewer", "Read-only Viewer", "VIEWER", "viewer@123", null);
        user("jaipur", "Jaipur North Operator", "OPERATOR", "jaipur@123", 45);
        user("agent", "Booth Agent (Kolkata Port)", agent.getCode(), "agent@1234", 52);
    }

    private void template(String name, com.vidhi.voters.messaging.Channel ch, String lang, String body, String providerId, String params) {
        com.vidhi.voters.messaging.MessageTemplate t = new com.vidhi.voters.messaging.MessageTemplate();
        t.setName(name);
        t.setChannel(ch);
        t.setLanguage(lang);
        t.setBody(body);
        t.setProviderTemplateId(providerId);
        t.setParams(params);
        templates.save(t);
    }

    private Party party(String name, String local, String shortName, String symbol, String color) {
        Party p = new Party();
        p.setName(name);
        p.setNameLocal(local);
        p.setShortName(shortName);
        p.setSymbol(symbol);
        p.setColor(color);
        return parties.save(p);
    }

    private void user(String username, String fullName, String roleCode, String password, Integer assemblyNo) {
        AppUser u = new AppUser();
        u.setUsername(username);
        u.setFullName(fullName);
        u.getRoles().add(roles.findByCodeIgnoreCase(roleCode).orElseThrow());
        u.setPasswordHash(encoder.encode(password));
        u.setAssemblyNo(assemblyNo);
        users.save(u);
    }
}
