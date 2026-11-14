package com.vidhi.voters;

import com.vidhi.voters.model.Voter;
import com.vidhi.voters.repo.VoterRepository;
import org.apache.poi.ss.usermodel.WorkbookFactory;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.mock.web.MockHttpSession;
import org.springframework.mock.web.MockMultipartFile;
import org.springframework.security.test.context.support.WithMockUser;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;

import java.io.ByteArrayInputStream;
import java.util.Locale;

import static org.assertj.core.api.Assertions.assertThat;
import static org.hamcrest.Matchers.*;
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

/** Runs against the "demo" profile: in-memory H2 with Hindi / Urdu / Bengali sample voters (assemblies 45, 46, 52). */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("demo")
class VoterApplicationTests {

    @Autowired MockMvc mvc;
    @Autowired VoterRepository voters;

    private Voter anyVoterOf(int assembly) {
        return voters.findAll().stream().filter(v -> v.getAssemblyNo() == assembly && v.getHouseNo() != null).findFirst().orElseThrow();
    }

    // ------------------------------------------------------------------ login + assembly
    @Test
    void anonymousIsRedirectedToLogin() throws Exception {
        mvc.perform(get("/voters")).andExpect(status().is3xxRedirection()).andExpect(redirectedUrlPattern("**/login"));
    }

    @Test
    void loginPageHasUsernamePasswordAndAssemblyDropDown() throws Exception {
        mvc.perform(get("/login"))
           .andExpect(status().isOk())
           .andExpect(content().string(containsString("name=\"username\"")))
           .andExpect(content().string(containsString("name=\"password\"")))
           .andExpect(content().string(containsString("name=\"assemblyNo\"")))
           .andExpect(content().string(containsString("45 - Jaipur North")));
    }

    @Test
    void loginWithAssemblyScopesTheSession() throws Exception {
        MockHttpSession session = (MockHttpSession) mvc.perform(post("/login").with(csrf())
                        .param("username", "admin").param("password", "admin@123").param("assemblyNo", "46"))
                .andExpect(redirectedUrl("/dashboard"))
                .andReturn().getRequest().getSession(false);
        // only assembly 46 voters are listed, even if another assembly is requested
        mvc.perform(get("/voters").param("assemblyNo", "45").param("size", "500").session(session))
           .andExpect(status().isOk())
           .andExpect(content().string(containsString("Lucknow West")))
           .andExpect(content().string(not(containsString("Shastri Nagar"))));
        // a voter of another assembly cannot be opened
        mvc.perform(get("/voters/" + anyVoterOf(45).getId()).session(session)).andExpect(status().isNotFound());
    }

    @Test
    void userAssignedToAnAssemblyCannotLogInToAnother() throws Exception {
        mvc.perform(post("/login").with(csrf()).param("username", "jaipur").param("password", "jaipur@123").param("assemblyNo", "52"))
           .andExpect(redirectedUrl("/login?error=assembly"));
        mvc.perform(post("/login").with(csrf()).param("username", "jaipur").param("password", "jaipur@123").param("assemblyNo", ""))
           .andExpect(redirectedUrl("/login?error=assembly"));
        mvc.perform(post("/login").with(csrf()).param("username", "jaipur").param("password", "jaipur@123").param("assemblyNo", "45"))
           .andExpect(redirectedUrl("/dashboard"));
        mvc.perform(post("/login").with(csrf()).param("username", "jaipur").param("password", "wrong").param("assemblyNo", "45"))
           .andExpect(redirectedUrl("/login?error=credentials"));
    }

    // ------------------------------------------------------------------ languages
    @Test
    @WithMockUser(roles = "VIEWER")
    void uiSwitchesLanguageAndUrduIsRightToLeft() throws Exception {
        mvc.perform(get("/voters").param("lang", "hi"))
           .andExpect(content().string(containsString("मतदाता सूची")))
           .andExpect(content().string(containsString("lang=\"hi\"")))
           .andExpect(cookie().value("vis-lang", "hi"));
        mvc.perform(get("/voters").locale(Locale.forLanguageTag("ur")))
           .andExpect(content().string(containsString("dir=\"rtl\"")))
           .andExpect(content().string(containsString("bootstrap.rtl.min.css")))
           .andExpect(content().string(containsString("ووٹر فہرست")));
        for (String lang : new String[]{"bn", "mr", "gu", "pa", "ta", "te"})
            mvc.perform(get("/dashboard").param("lang", lang)).andExpect(status().isOk())
               .andExpect(content().string(not(containsString("Campaign Dashboard"))));
        // unsupported language falls back to English
        mvc.perform(get("/voters").param("lang", "xx")).andExpect(content().string(containsString("Voter List")));
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void searchFindsLocalLanguageNames() throws Exception {
        mvc.perform(get("/voters").param("q", "शर्मा"))
           .andExpect(content().string(containsString("Sharma")));
        mvc.perform(get("/voters").param("q", "خان"))
           .andExpect(content().string(containsString("Khan")));
        mvc.perform(get("/voters").param("q", "ব্যানার্জি"))
           .andExpect(content().string(containsString("Banerjee")));
    }

    // ------------------------------------------------------------------ voters
    @Test
    @WithMockUser(roles = {"OPERATOR", "VIEWER"})
    void createVoterWithLocalNamesAndNativeDigits() throws Exception {
        mvc.perform(post("/voters").with(csrf())
                        .param("epicNo", "uni0000001").param("name", "Meera Devi").param("nameLocal", "मीरा देवी")
                        .param("gender", "FEMALE").param("relationType", "HUSBAND").param("relativeName", "Mohan Lal")
                        .param("relativeNameLocal", "मोहन लाल").param("assemblyNo", "45").param("boothNo", "3")
                        .param("houseNo", "7A").param("address", "Temple Road").param("addressLocal", "मंदिर मार्ग")
                        .param("mobile", "९८७६५४३२१०"))           // Devanagari digits
           .andExpect(status().is3xxRedirection());
        Voter v = voters.findByEpicNoIgnoreCase("UNI0000001").orElseThrow();
        assertThat(v.getMobile()).isEqualTo("9876543210");
        assertThat(v.getNameLocal()).isEqualTo("मीरा देवी");
        assertThat(v.getBoothName()).isNotBlank();                  // filled from the booth master
        mvc.perform(get("/voters/" + v.getId()).param("lang", "hi"))
           .andExpect(content().string(containsString("पत्नी Mohan Lal")));

        mvc.perform(post("/voters").with(csrf()).param("lang", "hi")
                        .param("epicNo", "UNI0000001").param("name", "Dup").param("gender", "MALE")
                        .param("assemblyNo", "45").param("boothNo", "7"))
           .andExpect(content().string(containsString("इस EPIC नंबर का मतदाता पहले से मौजूद है")));
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void houseNoLinkOpensEveryoneInThatHouse() throws Exception {
        Voter v = anyVoterOf(45);
        long sameHouse = voters.findAll().stream().filter(o -> o.getAssemblyNo().equals(v.getAssemblyNo())
                && v.getHouseNo().equalsIgnoreCase(o.getHouseNo()) && v.getAddress().equalsIgnoreCase(o.getAddress())
                && v.getVillageWard().equalsIgnoreCase(o.getVillageWard())).count();
        mvc.perform(get("/voters").param("epicNo", v.getEpicNo()))
           .andExpect(content().string(containsString("/voters/" + v.getId() + "/household")));
        String html = mvc.perform(get("/voters/" + v.getId() + "/household").param("fragment", "1"))
                .andExpect(status().isOk()).andReturn().getResponse().getContentAsString();
        assertThat(html).doesNotContain("<html").contains(v.getEpicNo()).contains(sameHouse + " voter(s)");
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void viewerCannotAddVotersOrOpenAdminOrMasters() throws Exception {
        mvc.perform(get("/voters/new")).andExpect(status().isForbidden());
        mvc.perform(get("/admin/users")).andExpect(status().isForbidden());
        mvc.perform(get("/masters/parties/new")).andExpect(status().isForbidden());
        mvc.perform(get("/results/entry")).andExpect(status().isForbidden());
        mvc.perform(get("/masters/parties")).andExpect(status().isOk());
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void exportsInHindiWithUnicodeFonts() throws Exception {
        byte[] xlsx = mvc.perform(get("/voters/export/excel").param("assemblyNo", "45").param("lang", "hi"))
                .andExpect(status().isOk()).andReturn().getResponse().getContentAsByteArray();
        try (var wb = WorkbookFactory.create(new ByteArrayInputStream(xlsx))) {
            var header = wb.getSheetAt(0).getRow(2);
            assertThat(header.getCell(1).getStringCellValue()).isEqualTo("विधानसभा सं.");
            assertThat(header.getCell(7).getStringCellValue()).isEqualTo("मतदाता का नाम (स्थानीय)");
        }
        byte[] pdf = mvc.perform(get("/voters/export/pdf").param("assemblyNo", "46").param("lang", "ur"))
                .andExpect(status().isOk()).andReturn().getResponse().getContentAsByteArray();
        String raw = new String(pdf, java.nio.charset.StandardCharsets.ISO_8859_1);
        assertThat(raw).startsWith("%PDF-").contains("NotoNaskhArabic").contains("NotoSans");
        mvc.perform(get("/voters/print").param("assemblyNo", "52").param("lang", "bn"))
           .andExpect(status().isOk()).andExpect(content().string(containsString("ভোটার তালিকা")));
    }

    @Test
    @WithMockUser(roles = {"OPERATOR", "VIEWER"})
    void importAcceptsTemplateInAnyLanguage() throws Exception {
        byte[] template = mvc.perform(get("/voters/import/template").param("lang", "ta"))
                .andReturn().getResponse().getContentAsByteArray();
        mvc.perform(multipart("/voters/import").file(new MockMultipartFile("file", "t.xlsx", null, template)).with(csrf()))
           .andExpect(status().isOk())
           .andExpect(content().string(containsString("1 added")));
        assertThat(voters.findByEpicNoIgnoreCase("ABC1234567").orElseThrow().getNameLocal()).isEqualTo("रमेश कुमार");
    }

    // ------------------------------------------------------------------ masters, results, reports, dashboard
    @Test
    @WithMockUser(username = "admin", roles = {"ADMIN", "OPERATOR", "VIEWER"})
    void adminManagesMastersAndUsers() throws Exception {
        mvc.perform(post("/masters/parties").with(csrf()).param("name", "Test Party").param("shortName", "tp").param("color", "#123456"))
           .andExpect(redirectedUrl("/masters/parties"));
        mvc.perform(get("/masters/parties")).andExpect(content().string(containsString("TP")));
        mvc.perform(post("/masters/parties").with(csrf()).param("name", "Again").param("shortName", "TP"))
           .andExpect(content().string(containsString("already exists")));
        mvc.perform(post("/admin/users").with(csrf()).param("username", "booth.agent").param("fullName", "Agent")
                        .param("role", "VIEWER").param("enabled", "true").param("assemblyNo", "52").param("newPassword", "secret123"))
           .andExpect(redirectedUrl("/admin/users"));
        mvc.perform(get("/admin/audit")).andExpect(content().string(containsString("booth.agent")));
        mvc.perform(get("/admin/system")).andExpect(content().string(containsString("H2")));
    }

    @Test
    @WithMockUser(username = "operator", roles = {"OPERATOR", "VIEWER"})
    void resultsEntryAndView() throws Exception {
        String page = mvc.perform(get("/results/entry").param("assemblyNo", "45").param("boothNo", "12"))
                .andExpect(status().isOk()).andReturn().getResponse().getContentAsString();
        var m = java.util.regex.Pattern.compile("name=\"votes\\[(\\d+)]\"").matcher(page);
        var req = post("/results/entry").with(csrf()).param("electionId", "1").param("assemblyNo", "45").param("boothNo", "12");
        int n = 0;
        while (m.find()) { req = req.param("votes[" + m.group(1) + "]", String.valueOf(100 + n++)); }
        assertThat(n).isEqualTo(5);
        mvc.perform(req).andExpect(status().is3xxRedirection());
        mvc.perform(get("/results/contest").param("assemblyNo", "45"))
           .andExpect(status().isOk()).andExpect(content().string(containsString("11/12")));
        mvc.perform(get("/results")).andExpect(content().string(containsString("Kolkata Port")));
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void reportsRenderAndExport() throws Exception {
        for (String id : new String[]{"assembly-summary", "booth-summary", "village-summary", "age-gender", "results-summary", "party-performance"}) {
            mvc.perform(get("/reports/" + id).param("lang", "hi")).andExpect(status().isOk());
            mvc.perform(get("/reports/" + id + "/export/pdf")).andExpect(status().isOk());
            mvc.perform(get("/reports/" + id + "/export/xlsx")).andExpect(status().isOk());
        }
        mvc.perform(get("/reports/assembly-summary")).andExpect(content().string(containsString("Lucknow West")));
        mvc.perform(get("/reports/nope")).andExpect(status().isNotFound());
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void dashboardDataForCandidate() throws Exception {
        mvc.perform(get("/dashboard").param("assemblyNo", "52")).andExpect(status().isOk())
           .andExpect(content().string(containsString("chartMargin")));
        mvc.perform(get("/dashboard/data").param("assemblyNo", "52").param("party", "LSD").param("lang", "bn"))
           .andExpect(status().isOk())
           .andExpect(jsonPath("$.election.focusParty").value("LSD"))
           .andExpect(jsonPath("$.boothVoters.labels.length()").value(12))
           .andExpect(jsonPath("$.ageGender.series[0].name").value("পুরুষ"))
           .andExpect(jsonPath("$.election.margin.series[0].data.length()").value(10))
           .andExpect(jsonPath("$.household.labels[5]").value("6+"));
    }
}
