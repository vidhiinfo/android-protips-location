package com.vidhi.voters.model;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

import java.text.Normalizer;
import java.time.LocalDateTime;

/**
 * All text columns are Unicode (MySQL utf8mb4), so any field can hold Hindi, Bengali, Urdu, Tamil, etc.
 * The *_local columns hold the regional-language spelling next to the English one, as on Indian electoral rolls.
 */
@Entity
@Table(name = "voters",
        uniqueConstraints = @UniqueConstraint(name = "uk_voter_epic", columnNames = "epic_no"),
        indexes = {
                @Index(name = "idx_voter_name", columnList = "name"),
                @Index(name = "idx_voter_name_local", columnList = "name_local"),
                @Index(name = "idx_voter_assembly_booth", columnList = "assembly_no, booth_no"),
                @Index(name = "idx_voter_mobile", columnList = "mobile"),
                @Index(name = "idx_voter_district", columnList = "district"),
                @Index(name = "idx_voter_village", columnList = "village_ward")
        })
public class Voter {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    /** EPIC / Voter ID card number. */
    @NotBlank(message = "{validation.required}") @Size(max = 20, message = "{validation.size}")
    @Column(name = "epic_no", nullable = false, length = 20)
    private String epicNo;

    @NotBlank(message = "{validation.required}") @Size(max = 120, message = "{validation.size}")
    @Column(nullable = false, length = 120)
    private String name;                    // as written on the roll in English

    @Size(max = 120, message = "{validation.size}")
    @Column(name = "name_local", length = 120)
    private String nameLocal;               // same name in Hindi / Bengali / Urdu / ...

    @Size(max = 120, message = "{validation.size}")
    @Column(name = "relative_name", length = 120)
    private String relativeName;            // Father / Husband name

    @Size(max = 120, message = "{validation.size}")
    @Column(name = "relative_name_local", length = 120)
    private String relativeNameLocal;

    @Enumerated(EnumType.STRING)
    @Column(name = "relation_type", length = 10)
    private RelationType relationType = RelationType.FATHER;

    @NotNull(message = "{validation.required}")
    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 10)
    private Gender gender;

    @Min(value = 18, message = "{validation.age}") @Max(value = 130, message = "{validation.age}")
    private Integer age;

    @NotNull(message = "{validation.required}") @Min(value = 1, message = "{validation.positive}")
    @Column(name = "assembly_no", nullable = false)
    private Integer assemblyNo;

    @Size(max = 100, message = "{validation.size}")
    @Column(name = "assembly_name", length = 100)
    private String assemblyName;

    @NotNull(message = "{validation.required}") @Min(value = 1, message = "{validation.positive}")
    @Column(name = "booth_no", nullable = false)
    private Integer boothNo;                // Part / Booth number

    @Size(max = 200, message = "{validation.size}")
    @Column(name = "booth_name", length = 200)
    private String boothName;               // Polling station

    @Min(value = 1, message = "{validation.positive}")
    @Column(name = "serial_no")
    private Integer serialNo;               // Serial no. in the electoral roll part

    @Size(max = 30, message = "{validation.size}")
    @Column(name = "house_no", length = 30)
    private String houseNo;

    @Size(max = 300, message = "{validation.size}")
    @Column(length = 300)
    private String address;

    @Size(max = 300, message = "{validation.size}")
    @Column(name = "address_local", length = 300)
    private String addressLocal;

    @Size(max = 100, message = "{validation.size}")
    @Column(name = "village_ward", length = 100)
    private String villageWard;

    @Size(max = 100, message = "{validation.size}")
    @Column(length = 100)
    private String district;

    @Pattern(regexp = "^$|^[0-9]{6}$", message = "{validation.pincode}")
    @Column(length = 6)
    private String pincode;

    @Pattern(regexp = "^$|^[6-9][0-9]{9}$", message = "{validation.mobile}")
    @Column(length = 10)
    private String mobile;

    @Pattern(regexp = "^$|^[6-9][0-9]{9}$", message = "{validation.mobile}")
    @Column(name = "alt_mobile", length = 10)
    private String altMobile;

    @Email(message = "{validation.email}") @Size(max = 120, message = "{validation.size}")
    @Column(length = 120)
    private String email;

    @Size(max = 50, message = "{validation.size}")
    @Column(length = 50)
    private String category;                // e.g. GEN / OBC / SC / ST (optional)

    @Size(max = 500, message = "{validation.size}")
    @Column(length = 500)
    private String remarks;

    @Column(name = "created_at", nullable = false, updatable = false)
    private LocalDateTime createdAt;

    @Column(name = "updated_at", nullable = false)
    private LocalDateTime updatedAt;

    @PrePersist
    void onCreate() {
        createdAt = updatedAt = LocalDateTime.now();
        normalize();
    }

    @PreUpdate
    void onUpdate() {
        updatedAt = LocalDateTime.now();
        normalize();
    }

    private void normalize() {
        if (epicNo != null) epicNo = asciiDigits(epicNo.trim()).toUpperCase(java.util.Locale.ROOT);
        mobile = asciiDigits(mobile);
        altMobile = asciiDigits(altMobile);
        pincode = asciiDigits(pincode);
        name = nfc(name);
        nameLocal = nfc(nameLocal);
        relativeName = nfc(relativeName);
        relativeNameLocal = nfc(relativeNameLocal);
        address = nfc(address);
        addressLocal = nfc(addressLocal);
        villageWard = nfc(villageWard);
        district = nfc(district);
        assemblyName = nfc(assemblyName);
        boothName = nfc(boothName);
        remarks = nfc(remarks);
    }

    /** Converts native digits (०-९, ০-৯, ۰-۹, ௦-௯ ...) to 0-9, leaving everything else unchanged. */
    public static String asciiDigits(String s) {
        if (s == null) return null;
        StringBuilder b = new StringBuilder(s.length());
        s.codePoints().forEach(c -> b.append(Character.isDigit(c) ? (char) ('0' + Character.digit(c, 10)) : new String(Character.toChars(c))));
        return b.toString();
    }

    /**
     * Trim and convert to Unicode NFC. Different keyboards / input tools can produce the same
     * Indic or Urdu text as different code point sequences; NFC makes them identical so search matches.
     */
    public static String nfc(String s) {
        if (s == null) return null;
        String t = Normalizer.normalize(s.strip(), Normalizer.Form.NFC);
        return t.isEmpty() ? null : t;
    }

    // ---------- getters / setters ----------
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getEpicNo() { return epicNo; }
    public void setEpicNo(String epicNo) { this.epicNo = epicNo; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getNameLocal() { return nameLocal; }
    public void setNameLocal(String nameLocal) { this.nameLocal = nameLocal; }
    public String getRelativeNameLocal() { return relativeNameLocal; }
    public void setRelativeNameLocal(String relativeNameLocal) { this.relativeNameLocal = relativeNameLocal; }
    public String getAddressLocal() { return addressLocal; }
    public void setAddressLocal(String addressLocal) { this.addressLocal = addressLocal; }
    public String getRelativeName() { return relativeName; }
    public void setRelativeName(String relativeName) { this.relativeName = relativeName; }
    public RelationType getRelationType() { return relationType; }
    public void setRelationType(RelationType relationType) { this.relationType = relationType; }
    public Gender getGender() { return gender; }
    public void setGender(Gender gender) { this.gender = gender; }
    public Integer getAge() { return age; }
    public void setAge(Integer age) { this.age = age; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public String getAssemblyName() { return assemblyName; }
    public void setAssemblyName(String assemblyName) { this.assemblyName = assemblyName; }
    public Integer getBoothNo() { return boothNo; }
    public void setBoothNo(Integer boothNo) { this.boothNo = boothNo; }
    public String getBoothName() { return boothName; }
    public void setBoothName(String boothName) { this.boothName = boothName; }
    public Integer getSerialNo() { return serialNo; }
    public void setSerialNo(Integer serialNo) { this.serialNo = serialNo; }
    public String getHouseNo() { return houseNo; }
    public void setHouseNo(String houseNo) { this.houseNo = houseNo; }
    public String getAddress() { return address; }
    public void setAddress(String address) { this.address = address; }
    public String getVillageWard() { return villageWard; }
    public void setVillageWard(String villageWard) { this.villageWard = villageWard; }
    public String getDistrict() { return district; }
    public void setDistrict(String district) { this.district = district; }
    public String getPincode() { return pincode; }
    public void setPincode(String pincode) { this.pincode = pincode; }
    public String getMobile() { return mobile; }
    public void setMobile(String mobile) { this.mobile = mobile; }
    public String getAltMobile() { return altMobile; }
    public void setAltMobile(String altMobile) { this.altMobile = altMobile; }
    public String getEmail() { return email; }
    public void setEmail(String email) { this.email = email; }
    public String getCategory() { return category; }
    public void setCategory(String category) { this.category = category; }
    public String getRemarks() { return remarks; }
    public void setRemarks(String remarks) { this.remarks = remarks; }
    public LocalDateTime getCreatedAt() { return createdAt; }
    public LocalDateTime getUpdatedAt() { return updatedAt; }

    /**
     * Message key for the relation prefix (S/O, D/O, W/O, C/O); translated in messages_*.properties.
     */
    public String getRelationKey() {
        return switch (relationType == null ? RelationType.FATHER : relationType) {
            case FATHER, MOTHER -> gender == Gender.FEMALE ? "relation.prefix.daughter" : "relation.prefix.son";
            case HUSBAND -> "relation.prefix.wife";
            case OTHER -> "relation.prefix.care";
        };
    }

    /** Address without the house no. (shown in its own column). */
    public String getFullAddress() {
        StringBuilder sb = new StringBuilder();
        for (String p : new String[]{address, villageWard, district, pincode}) {
            if (p != null && !p.isBlank()) {
                if (!sb.isEmpty()) sb.append(", ");
                sb.append(p);
            }
        }
        return sb.toString();
    }
}
