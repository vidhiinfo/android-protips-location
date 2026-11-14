package com.vidhi.voters.service;

import com.vidhi.voters.model.Gender;
import org.springframework.data.domain.Sort;
import org.springframework.web.util.UriComponentsBuilder;

import java.util.Map;

/**
 * All search / filter / sort / paging parameters. Bound from the query string,
 * so the same URL parameters drive the table, the Excel/PDF export and the print view.
 */
public class VoterFilter {

    /** Whitelist: UI sort key -> entity property. Prevents sorting on arbitrary properties. */
    public static final Map<String, String> SORTABLE = Map.ofEntries(
            Map.entry("epicNo", "epicNo"),
            Map.entry("name", "name"),
            Map.entry("nameLocal", "nameLocal"),
            Map.entry("relativeName", "relativeName"),
            Map.entry("gender", "gender"),
            Map.entry("age", "age"),
            Map.entry("assemblyNo", "assemblyNo"),
            Map.entry("boothNo", "boothNo"),
            Map.entry("serialNo", "serialNo"),
            Map.entry("villageWard", "villageWard"),
            Map.entry("district", "district"),
            Map.entry("mobile", "mobile"),
            Map.entry("updatedAt", "updatedAt"));

    private String q;               // free text: name, father name, EPIC, mobile, address, house no
    private String name;
    private String relativeName;
    private String epicNo;
    private String mobile;
    private Integer assemblyNo;
    private Integer boothNo;
    private Gender gender;
    private Integer ageMin;
    private Integer ageMax;
    private String district;
    private String villageWard;
    private String houseNo;
    private Boolean hasMobile;

    private String sort = "assemblyNo";
    private String dir = "asc";
    private int page = 0;
    private int size = 25;

    public Sort toSort() {
        String prop = SORTABLE.getOrDefault(sort, "assemblyNo");
        Sort.Direction d = "desc".equalsIgnoreCase(dir) ? Sort.Direction.DESC : Sort.Direction.ASC;
        Sort s = Sort.by(d, prop);
        // stable secondary ordering, like a printed electoral roll
        if (!prop.equals("assemblyNo")) s = s.and(Sort.by("assemblyNo"));
        return s.and(Sort.by("boothNo")).and(Sort.by("serialNo")).and(Sort.by("id"));
    }

    /** Query string for the current filters (without page/sort), used by links. */
    public String filterQuery() {
        UriComponentsBuilder b = UriComponentsBuilder.newInstance();
        add(b, "q", q); add(b, "name", name); add(b, "relativeName", relativeName);
        add(b, "epicNo", epicNo); add(b, "mobile", mobile);
        add(b, "assemblyNo", assemblyNo); add(b, "boothNo", boothNo);
        add(b, "gender", gender == null ? null : gender.name());
        add(b, "ageMin", ageMin); add(b, "ageMax", ageMax);
        add(b, "district", district); add(b, "villageWard", villageWard);
        add(b, "houseNo", houseNo); add(b, "hasMobile", hasMobile);
        String s = b.build().encode().getQuery();
        return s == null ? "" : s;
    }

    /** Filters + current sort, used by export / print links. */
    public String queryWithSort() {
        String f = filterQuery();
        return (f.isEmpty() ? "" : f + "&") + "sort=" + sort + "&dir=" + dir;
    }

    /** Link for a sortable column header: toggles direction when it is already the active column. */
    public String sortLink(String column) {
        String newDir = column.equals(sort) && "asc".equalsIgnoreCase(dir) ? "desc" : "asc";
        String f = filterQuery();
        return "?" + (f.isEmpty() ? "" : f + "&") + "sort=" + column + "&dir=" + newDir + "&size=" + size;
    }

    public String pageLink(int p) {
        return "?" + queryWithSort() + "&size=" + size + "&page=" + p;
    }

    /** Current filters + sort as name/value pairs, for hidden form fields. */
    public Map<String, String> hiddenParams() {
        Map<String, String> m = new java.util.LinkedHashMap<>();
        UriComponentsBuilder.fromUriString("?" + queryWithSort()).build().getQueryParams()
                .forEach((k, v) -> m.put(k, java.net.URLDecoder.decode(v.get(0), java.nio.charset.StandardCharsets.UTF_8)));
        return m;
    }

    public boolean isActive() {
        return !filterQuery().isEmpty();
    }

    private static void add(UriComponentsBuilder b, String k, Object v) {
        if (v != null && !(v instanceof String s && s.isBlank())) b.queryParam(k, v);
    }

    // ---------- getters / setters ----------
    public String getQ() { return q; }
    public void setQ(String q) { this.q = q; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getRelativeName() { return relativeName; }
    public void setRelativeName(String relativeName) { this.relativeName = relativeName; }
    public String getEpicNo() { return epicNo; }
    public void setEpicNo(String epicNo) { this.epicNo = epicNo; }
    public String getMobile() { return mobile; }
    public void setMobile(String mobile) { this.mobile = mobile; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public Integer getBoothNo() { return boothNo; }
    public void setBoothNo(Integer boothNo) { this.boothNo = boothNo; }
    public Gender getGender() { return gender; }
    public void setGender(Gender gender) { this.gender = gender; }
    public Integer getAgeMin() { return ageMin; }
    public void setAgeMin(Integer ageMin) { this.ageMin = ageMin; }
    public Integer getAgeMax() { return ageMax; }
    public void setAgeMax(Integer ageMax) { this.ageMax = ageMax; }
    public String getDistrict() { return district; }
    public void setDistrict(String district) { this.district = district; }
    public String getVillageWard() { return villageWard; }
    public void setVillageWard(String villageWard) { this.villageWard = villageWard; }
    public String getHouseNo() { return houseNo; }
    public void setHouseNo(String houseNo) { this.houseNo = houseNo; }
    public Boolean getHasMobile() { return hasMobile; }
    public void setHasMobile(Boolean hasMobile) { this.hasMobile = hasMobile; }
    public String getSort() { return sort; }
    public void setSort(String sort) { this.sort = SORTABLE.containsKey(sort) ? sort : "assemblyNo"; }
    public String getDir() { return dir; }
    public void setDir(String dir) { this.dir = "desc".equalsIgnoreCase(dir) ? "desc" : "asc"; }
    public int getPage() { return page; }
    public void setPage(int page) { this.page = Math.max(0, page); }
    public int getSize() { return size; }
    public void setSize(int size) { this.size = Math.min(Math.max(size, 10), 500); }
}
