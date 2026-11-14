package com.vidhi.voters.export;

import com.vidhi.voters.model.Gender;
import com.vidhi.voters.model.RelationType;
import com.vidhi.voters.model.Voter;
import com.vidhi.voters.repo.VoterRepository;
import jakarta.validation.ConstraintViolation;
import jakarta.validation.Validator;
import org.apache.poi.ss.usermodel.*;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

import java.io.InputStream;
import java.util.*;

/**
 * Bulk upload from an .xlsx that uses the same headers as the export / template.
 * Rows are matched on EPIC No: existing voters are updated, new ones inserted.
 */
@Component
public class ExcelImporter {

    public record Result(int inserted, int updated, List<String> errors) {}

    private final VoterRepository repo;
    private final Validator validator;
    private final DataFormatter fmt = new DataFormatter();

    public ExcelImporter(VoterRepository repo, Validator validator) {
        this.repo = repo;
        this.validator = validator;
    }

    @Transactional
    public Result importXlsx(InputStream in) throws Exception {
        int inserted = 0, updated = 0;
        List<String> errors = new ArrayList<>();

        try (Workbook wb = WorkbookFactory.create(in)) {
            Sheet sheet = wb.getSheetAt(0);
            Map<String, Integer> idx = null;
            int headerRow = -1;

            // locate the header row (the export has a title row above it)
            for (int r = 0; r <= Math.min(5, sheet.getLastRowNum()); r++) {
                Map<String, Integer> m = headerMap(sheet.getRow(r));
                if (m.containsKey("epic no") && m.containsKey("voter name")) { idx = m; headerRow = r; break; }
            }
            if (idx == null) {
                errors.add("Header row not found. Download the template and keep its column headings.");
                return new Result(0, 0, errors);
            }

            for (int r = headerRow + 1; r <= sheet.getLastRowNum(); r++) {
                Row row = sheet.getRow(r);
                if (row == null) continue;
                String epic = get(row, idx, "epic no");
                if (epic.isBlank() && get(row, idx, "voter name").isBlank()) continue;

                try {
                    Voter v = epic.isBlank() ? new Voter()
                            : repo.findByEpicNoIgnoreCase(epic.trim()).orElseGet(Voter::new);
                    boolean isNew = v.getId() == null;

                    v.setEpicNo(epic);
                    v.setName(get(row, idx, "voter name"));
                    v.setRelationType(RelationType.parse(get(row, idx, "relation")));
                    v.setRelativeName(blankToNull(get(row, idx, "father/husband name")));
                    v.setGender(Gender.parse(get(row, idx, "gender")));
                    v.setAge(toInt(get(row, idx, "age")));
                    v.setAssemblyNo(toInt(get(row, idx, "assembly no")));
                    v.setAssemblyName(blankToNull(get(row, idx, "assembly name")));
                    v.setBoothNo(toInt(get(row, idx, "booth no")));
                    v.setBoothName(blankToNull(get(row, idx, "booth name")));
                    v.setSerialNo(toInt(get(row, idx, "serial no")));
                    v.setHouseNo(blankToNull(get(row, idx, "house no")));
                    v.setAddress(blankToNull(get(row, idx, "address")));
                    v.setVillageWard(blankToNull(get(row, idx, "village/ward")));
                    v.setDistrict(blankToNull(get(row, idx, "district")));
                    v.setPincode(blankToNull(get(row, idx, "pincode")));
                    v.setMobile(blankToNull(digits(get(row, idx, "mobile"))));
                    v.setAltMobile(blankToNull(digits(get(row, idx, "alt mobile"))));
                    v.setEmail(blankToNull(get(row, idx, "email")));
                    v.setCategory(blankToNull(get(row, idx, "category")));
                    v.setRemarks(blankToNull(get(row, idx, "remarks")));

                    Set<ConstraintViolation<Voter>> violations = validator.validate(v);
                    if (!violations.isEmpty()) {
                        ConstraintViolation<Voter> cv = violations.iterator().next();
                        errors.add("Row " + (r + 1) + ": " + cv.getPropertyPath() + " " + cv.getMessage());
                        continue;
                    }
                    repo.save(v);
                    if (isNew) inserted++; else updated++;
                } catch (Exception e) {
                    errors.add("Row " + (r + 1) + ": " + e.getMessage());
                }
                if (errors.size() > 200) { errors.add("Too many errors, stopped."); break; }
            }
        }
        return new Result(inserted, updated, errors);
    }

    private Map<String, Integer> headerMap(Row row) {
        Map<String, Integer> m = new HashMap<>();
        if (row == null) return m;
        for (Cell c : row) m.put(fmt.formatCellValue(c).trim().toLowerCase(), c.getColumnIndex());
        return m;
    }

    private String get(Row row, Map<String, Integer> idx, String header) {
        Integer i = idx.get(header);
        if (i == null) return "";
        Cell c = row.getCell(i);
        return c == null ? "" : fmt.formatCellValue(c).trim();
    }

    private static Integer toInt(String s) {
        if (s == null || s.isBlank()) return null;
        return (int) Double.parseDouble(s.trim());
    }

    private static String digits(String s) {
        String d = s.replaceAll("\\D", "");
        return d.length() == 12 && d.startsWith("91") ? d.substring(2) : d;
    }

    private static String blankToNull(String s) { return s == null || s.isBlank() ? null : s; }
}
