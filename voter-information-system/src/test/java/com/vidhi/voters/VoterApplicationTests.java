package com.vidhi.voters;

import com.vidhi.voters.export.ExcelImporter;
import org.apache.poi.ss.usermodel.WorkbookFactory;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.mock.web.MockMultipartFile;
import org.springframework.security.test.context.support.WithMockUser;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;

import java.io.ByteArrayInputStream;

import static org.assertj.core.api.Assertions.assertThat;
import static org.hamcrest.Matchers.*;
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.csrf;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("demo")
class VoterApplicationTests {

    @Autowired MockMvc mvc;

    @Test
    void anonymousIsRedirectedToLogin() throws Exception {
        mvc.perform(get("/voters")).andExpect(status().is3xxRedirection())
           .andExpect(redirectedUrlPattern("**/login"));
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void viewerCanSearchSortAndFilter() throws Exception {
        mvc.perform(get("/voters").param("q", "RJX1000012"))
           .andExpect(status().isOk())
           .andExpect(content().string(containsString("RJX1000012")))
           .andExpect(content().string(not(containsString("RJX1000013"))));

        mvc.perform(get("/voters").param("assemblyNo", "52").param("gender", "FEMALE")
                        .param("ageMin", "30").param("ageMax", "40").param("sort", "name").param("dir", "desc"))
           .andExpect(status().isOk())
           .andExpect(content().string(containsString("filtered")));
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void viewerCannotAddVoters() throws Exception {
        mvc.perform(get("/voters/new")).andExpect(status().isForbidden());
    }

    @Test
    @WithMockUser(roles = "VIEWER")
    void exportsExcelPdfAndPrint() throws Exception {
        byte[] xlsx = mvc.perform(get("/voters/export/excel").param("boothNo", "3"))
                .andExpect(status().isOk()).andReturn().getResponse().getContentAsByteArray();
        try (var wb = WorkbookFactory.create(new ByteArrayInputStream(xlsx))) {
            assertThat(wb.getSheetAt(0).getRow(2).getCell(1).getStringCellValue()).isEqualTo("Assembly No");
            assertThat(wb.getSheetAt(0).getLastRowNum()).isGreaterThan(3);
        }
        byte[] pdf = mvc.perform(get("/voters/export/pdf").param("district", "Jaipur"))
                .andExpect(status().isOk()).andReturn().getResponse().getContentAsByteArray();
        assertThat(new String(pdf, 0, 5)).isEqualTo("%PDF-");

        mvc.perform(get("/voters/print").param("assemblyNo", "45"))
           .andExpect(status().isOk()).andExpect(content().string(containsString("Assembly 45")));
    }

    @Test
    @WithMockUser(roles = {"ADMIN", "VIEWER"})
    void adminCanCreateAndRejectsDuplicateEpic() throws Exception {
        mvc.perform(post("/voters").with(csrf())
                        .param("epicNo", "new0000001").param("name", "Test Voter").param("gender", "MALE")
                        .param("relationType", "FATHER").param("relativeName", "Test Father")
                        .param("assemblyNo", "45").param("boothNo", "7").param("mobile", "9876543210"))
           .andExpect(status().is3xxRedirection());

        mvc.perform(get("/voters").param("epicNo", "NEW0000001"))
           .andExpect(content().string(containsString("S/O Test Father")));

        mvc.perform(post("/voters").with(csrf())
                        .param("epicNo", "NEW0000001").param("name", "Dup").param("gender", "MALE")
                        .param("assemblyNo", "45").param("boothNo", "7"))
           .andExpect(status().isOk())
           .andExpect(content().string(containsString("already exists")));

        mvc.perform(post("/voters").with(csrf())
                        .param("epicNo", "X1").param("name", "Bad").param("gender", "MALE")
                        .param("assemblyNo", "45").param("boothNo", "7").param("mobile", "12345"))
           .andExpect(content().string(containsString("valid 10-digit mobile")));
    }

    @Test
    @WithMockUser(roles = {"ADMIN", "VIEWER"})
    void importRoundTripsTemplate() throws Exception {
        byte[] template = mvc.perform(get("/voters/import/template"))
                .andReturn().getResponse().getContentAsByteArray();
        mvc.perform(multipart("/voters/import").file(new MockMultipartFile("file", "t.xlsx", null, template)).with(csrf()))
           .andExpect(status().isOk())
           .andExpect(content().string(containsString("1 added")));
        mvc.perform(get("/voters").param("q", "ABC1234567"))
           .andExpect(content().string(containsString("Ramesh Kumar")));
    }
}
