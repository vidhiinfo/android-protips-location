package com.vidhi.voters.export;

import com.vidhi.voters.model.Voter;

import java.util.List;
import java.util.function.Function;

/** Single definition of the exported columns, shared by Excel, PDF and the import template. */
public final class Columns {

    public record Col(String header, Function<Voter, Object> value, float pdfWidth) {}

    public static final List<Col> ALL = List.of(
            new Col("Assembly No", Voter::getAssemblyNo, 5),
            new Col("Assembly Name", Voter::getAssemblyName, 9),
            new Col("Booth No", Voter::getBoothNo, 4),
            new Col("Serial No", Voter::getSerialNo, 4),
            new Col("EPIC No", Voter::getEpicNo, 9),
            new Col("Voter Name", Voter::getName, 12),
            new Col("Relation", v -> v.getRelationType() == null ? "" : v.getRelationType().getLabel(), 0),
            new Col("Father/Husband Name", Voter::getRelativeName, 12),
            new Col("Gender", v -> v.getGender() == null ? "" : v.getGender().getLabel(), 5),
            new Col("Age", Voter::getAge, 3),
            new Col("House No", Voter::getHouseNo, 5),
            new Col("Address", Voter::getAddress, 0),
            new Col("Village/Ward", Voter::getVillageWard, 8),
            new Col("District", Voter::getDistrict, 0),
            new Col("Pincode", Voter::getPincode, 0),
            new Col("Mobile", Voter::getMobile, 8),
            new Col("Alt Mobile", Voter::getAltMobile, 0),
            new Col("Email", Voter::getEmail, 0),
            new Col("Booth Name", Voter::getBoothName, 0),
            new Col("Category", Voter::getCategory, 0),
            new Col("Remarks", Voter::getRemarks, 0));

    /** Narrower set for landscape A4 PDF (pdfWidth > 0). */
    public static List<Col> pdf() {
        return ALL.stream().filter(c -> c.pdfWidth() > 0).toList();
    }

    private Columns() {}
}
