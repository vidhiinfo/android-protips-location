package com.vidhi.voters.export;

import com.vidhi.voters.model.Voter;

import java.util.List;
import java.util.function.BiFunction;
import java.util.function.Function;

/**
 * Single definition of the exported columns, shared by Excel, PDF, the import template and the importer.
 * Headers are message keys ("col.name" ...) translated into the user's language.
 */
public final class Columns {

    /**
     * @param key      message key of the header, also the importer's column id
     * @param value    cell value; the second argument translates message keys (for gender / relation)
     * @param pdfWidth relative width in the PDF, 0 = not in the PDF
     */
    public record Col(String key, BiFunction<Voter, Function<String, String>, Object> value, float pdfWidth) {}

    public static final List<Col> ALL = List.of(
            new Col("col.assemblyNo", (v, t) -> v.getAssemblyNo(), 4),
            new Col("col.assemblyName", (v, t) -> v.getAssemblyName(), 0),
            new Col("col.boothNo", (v, t) -> v.getBoothNo(), 4),
            new Col("col.serialNo", (v, t) -> v.getSerialNo(), 4),
            new Col("col.epicNo", (v, t) -> v.getEpicNo(), 9),
            new Col("col.name", (v, t) -> v.getName(), 11),
            new Col("col.nameLocal", (v, t) -> v.getNameLocal(), 11),
            new Col("col.relation", (v, t) -> v.getRelationType() == null ? null : t.apply("relation." + v.getRelationType()), 0),
            new Col("col.relativeName", (v, t) -> v.getRelativeName(), 11),
            new Col("col.relativeNameLocal", (v, t) -> v.getRelativeNameLocal(), 11),
            new Col("col.gender", (v, t) -> v.getGender() == null ? null : t.apply("gender." + v.getGender()), 5),
            new Col("col.age", (v, t) -> v.getAge(), 3),
            new Col("col.houseNo", (v, t) -> v.getHouseNo(), 4),
            new Col("col.address", (v, t) -> v.getAddress(), 9),
            new Col("col.addressLocal", (v, t) -> v.getAddressLocal(), 0),
            new Col("col.villageWard", (v, t) -> v.getVillageWard(), 0),
            new Col("col.district", (v, t) -> v.getDistrict(), 0),
            new Col("col.pincode", (v, t) -> v.getPincode(), 0),
            new Col("col.mobile", (v, t) -> v.getMobile(), 8),
            new Col("col.altMobile", (v, t) -> v.getAltMobile(), 0),
            new Col("col.email", (v, t) -> v.getEmail(), 0),
            new Col("col.boothName", (v, t) -> v.getBoothName(), 0),
            new Col("col.category", (v, t) -> v.getCategory(), 0),
            new Col("col.remarks", (v, t) -> v.getRemarks(), 0));

    /** Narrower set for landscape A4 PDF (pdfWidth > 0). */
    public static List<Col> pdf() {
        return ALL.stream().filter(c -> c.pdfWidth() > 0).toList();
    }

    private Columns() {}
}
