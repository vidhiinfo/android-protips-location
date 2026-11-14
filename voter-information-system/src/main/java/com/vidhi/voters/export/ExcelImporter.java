package com.vidhi.voters.export;

import com.vidhi.voters.config.Languages;
import com.vidhi.voters.model.Gender;
import com.vidhi.voters.model.RelationType;
import com.vidhi.voters.model.Voter;
import com.vidhi.voters.repo.VoterRepository;
import jakarta.validation.ConstraintViolation;
import jakarta.validation.Validator;
import org.apache.poi.ss.usermodel.*;
import org.springframework.context.MessageSource;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

import java.io.InputStream;
import java.util.*;

/**
 * Bulk upload from an .xlsx that uses the same headers as the export / template.
 * Headers and gender / relation values are recognised in every supported language, so a file exported
 * in Hindi or Urdu can be uploaded again as is. Rows are matched on EPIC No: existing voters are updated,
 * new ones inserted.
 */
@Component
public class ExcelImporter {

    public record Result(int inserted, int updated, List<String> errors) {
        public int getInserted() { return inserted; }
        public int getUpdated() { return updated; }
        public List<String> getErrors() { return errors; }
    }

    private final VoterRepository repo;
    private final Validator validator;
    private final MessageSource messages;
    private final DataFormatter fmt = new DataFormatter();

    /** any-language header text -> column key */
    private final Map<String, String> headerToKey = new HashMap<>();
    private final Map<String, Gender> genderWords = new HashMap<>();
    private final Map<String, RelationType> relationWords = new HashMap<>();

    public ExcelImporter(VoterRepository repo, Validator validator, MessageSource messages) {
        this.repo = repo;
        this.validator = validator;
        this.messages = messages;
        for (Languages.Lang l : Languages.ALL) {
            for (Columns.Col c : Columns.ALL) headerToKey.put(norm(msg(l.locale(), c.key())), c.key());
            for (Gender g : Gender.values()) genderWords.put(norm(msg(l.locale(), "gender." + g)), g);
            for (RelationType r : RelationType.values()) relationWords.put(norm(msg(l.locale(), "relation." + r)), r);
        }
    }

    @Transactional
    /** @param onlyAssembly when not null (assembly chosen at login), rows of other assemblies are rejected */
    public Result importXlsx(InputStream in, Locale locale, Integer onlyAssembly) throws Exception {
        int inserted = 0, updated = 0;
        List<String> errors = new ArrayList<>();

        try (Workbook wb = WorkbookFactory.create(in)) {
            Sheet sheet = wb.getSheetAt(0);
            Map<String, Integer> idx = null;
            int headerRow = -1;

            // locate the header row (the export has a title row above it)
            for (int r = 0; r <= Math.min(5, sheet.getLastRowNum()); r++) {
                Map<String, Integer> m = headerMap(sheet.getRow(r));
                if (m.containsKey("col.epicNo") && m.containsKey("col.name")) { idx = m; headerRow = r; break; }
            }
            if (idx == null) {
                errors.add(msg(locale, "import.error.header"));
                return new Result(0, 0, errors);
            }

            for (int r = headerRow + 1; r <= sheet.getLastRowNum(); r++) {
                Row row = sheet.getRow(r);
                if (row == null) continue;
                String epic = get(row, idx, "col.epicNo");
                if (epic.isBlank() && get(row, idx, "col.name").isBlank()) continue;
                String rowLabel = msg(locale, "import.row", r + 1);

                try {
                    Voter v = epic.isBlank() ? new Voter()
                            : repo.findByEpicNoIgnoreCase(epic.trim()).orElseGet(Voter::new);
                    boolean isNew = v.getId() == null;
                    Integer rowAssembly = toInt(get(row, idx, "col.assemblyNo"));
                    if (onlyAssembly != null && (!onlyAssembly.equals(rowAssembly)
                            || (!isNew && !onlyAssembly.equals(v.getAssemblyNo())))) {
                        errors.add(rowLabel + ": " + msg(locale, "import.error.assembly", onlyAssembly));
                        continue;
                    }

                    v.setEpicNo(epic);
                    v.setName(get(row, idx, "col.name"));
                    v.setNameLocal(blankToNull(get(row, idx, "col.nameLocal")));
                    v.setRelationType(parseRelation(get(row, idx, "col.relation")));
                    v.setRelativeName(blankToNull(get(row, idx, "col.relativeName")));
                    v.setRelativeNameLocal(blankToNull(get(row, idx, "col.relativeNameLocal")));
                    v.setGender(parseGender(get(row, idx, "col.gender")));
                    v.setAge(toInt(get(row, idx, "col.age")));
                    v.setAssemblyNo(toInt(get(row, idx, "col.assemblyNo")));
                    v.setAssemblyName(blankToNull(get(row, idx, "col.assemblyName")));
                    v.setBoothNo(toInt(get(row, idx, "col.boothNo")));
                    v.setBoothName(blankToNull(get(row, idx, "col.boothName")));
                    v.setSerialNo(toInt(get(row, idx, "col.serialNo")));
                    v.setHouseNo(blankToNull(get(row, idx, "col.houseNo")));
                    v.setAddress(blankToNull(get(row, idx, "col.address")));
                    v.setAddressLocal(blankToNull(get(row, idx, "col.addressLocal")));
                    v.setVillageWard(blankToNull(get(row, idx, "col.villageWard")));
                    v.setDistrict(blankToNull(get(row, idx, "col.district")));
                    v.setPincode(blankToNull(get(row, idx, "col.pincode")));
                    v.setMobile(blankToNull(digits(get(row, idx, "col.mobile"))));
                    v.setAltMobile(blankToNull(digits(get(row, idx, "col.altMobile"))));
                    v.setEmail(blankToNull(get(row, idx, "col.email")));
                    v.setCategory(blankToNull(get(row, idx, "col.category")));
                    v.setRemarks(blankToNull(get(row, idx, "col.remarks")));

                    Set<ConstraintViolation<Voter>> violations = validator.validate(v);
                    if (!violations.isEmpty()) {
                        ConstraintViolation<Voter> cv = violations.iterator().next();
                        String field = cv.getPropertyPath().toString();
                        errors.add(rowLabel + ": " + msg(locale, "field." + field) + " - " + cv.getMessage());
                        continue;
                    }
                    repo.save(v);
                    if (isNew) inserted++; else updated++;
                } catch (Exception e) {
                    errors.add(rowLabel + ": " + e.getMessage());
                }
                if (errors.size() > 200) { errors.add(msg(locale, "import.error.tooMany")); break; }
            }
        }
        return new Result(inserted, updated, errors);
    }

    private Gender parseGender(String s) {
        Gender g = genderWords.get(norm(s));
        return g != null ? g : Gender.parse(s);
    }

    private RelationType parseRelation(String s) {
        RelationType r = relationWords.get(norm(s));
        return r != null ? r : RelationType.parse(s);
    }

    private Map<String, Integer> headerMap(Row row) {
        Map<String, Integer> m = new HashMap<>();
        if (row == null) return m;
        for (Cell c : row) {
            String key = headerToKey.get(norm(fmt.formatCellValue(c)));
            if (key != null) m.putIfAbsent(key, c.getColumnIndex());
        }
        return m;
    }

    private String get(Row row, Map<String, Integer> idx, String key) {
        Integer i = idx.get(key);
        if (i == null) return "";
        Cell c = row.getCell(i);
        return c == null ? "" : fmt.formatCellValue(c).trim();
    }

    private String msg(Locale l, String key, Object... args) {
        return messages.getMessage(key, args.length == 0 ? null : args, key, l);
    }

    private static String norm(String s) {
        String n = Voter.nfc(s);
        return n == null ? "" : n.toLowerCase(Locale.ROOT);
    }

    private static Integer toInt(String s) {
        if (s == null || s.isBlank()) return null;
        return (int) Double.parseDouble(Voter.asciiDigits(s.trim()));
    }

    private static String digits(String s) {
        String d = Voter.asciiDigits(s).replaceAll("[^0-9]", "");
        return d.length() == 12 && d.startsWith("91") ? d.substring(2) : d;
    }

    private static String blankToNull(String s) { return s == null || s.isBlank() ? null : s; }
}
