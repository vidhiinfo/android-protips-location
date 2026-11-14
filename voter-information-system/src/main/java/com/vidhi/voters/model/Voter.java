package com.vidhi.voters.model;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

import java.time.LocalDateTime;

@Entity
@Table(name = "voters",
        uniqueConstraints = @UniqueConstraint(name = "uk_voter_epic", columnNames = "epic_no"),
        indexes = {
                @Index(name = "idx_voter_name", columnList = "name"),
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
    @NotBlank @Size(max = 20)
    @Column(name = "epic_no", nullable = false, length = 20)
    private String epicNo;

    @NotBlank @Size(max = 120)
    @Column(nullable = false, length = 120)
    private String name;

    @Size(max = 120)
    @Column(name = "relative_name", length = 120)
    private String relativeName;            // Father / Husband name

    @Enumerated(EnumType.STRING)
    @Column(name = "relation_type", length = 10)
    private RelationType relationType = RelationType.FATHER;

    @NotNull
    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 10)
    private Gender gender;

    @Min(18) @Max(130)
    private Integer age;

    @NotNull @Min(1)
    @Column(name = "assembly_no", nullable = false)
    private Integer assemblyNo;

    @Size(max = 100)
    @Column(name = "assembly_name", length = 100)
    private String assemblyName;

    @NotNull @Min(1)
    @Column(name = "booth_no", nullable = false)
    private Integer boothNo;                // Part / Booth number

    @Size(max = 200)
    @Column(name = "booth_name", length = 200)
    private String boothName;               // Polling station

    @Min(1)
    @Column(name = "serial_no")
    private Integer serialNo;               // Serial no. in the electoral roll part

    @Size(max = 30)
    @Column(name = "house_no", length = 30)
    private String houseNo;

    @Size(max = 300)
    @Column(length = 300)
    private String address;

    @Size(max = 100)
    @Column(name = "village_ward", length = 100)
    private String villageWard;

    @Size(max = 100)
    @Column(length = 100)
    private String district;

    @Pattern(regexp = "^$|^[0-9]{6}$", message = "Pincode must be 6 digits")
    @Column(length = 6)
    private String pincode;

    @Pattern(regexp = "^$|^[6-9][0-9]{9}$", message = "Enter a valid 10-digit mobile number")
    @Column(length = 10)
    private String mobile;

    @Pattern(regexp = "^$|^[6-9][0-9]{9}$", message = "Enter a valid 10-digit mobile number")
    @Column(name = "alt_mobile", length = 10)
    private String altMobile;

    @Email @Size(max = 120)
    @Column(length = 120)
    private String email;

    @Size(max = 50)
    @Column(length = 50)
    private String category;                // e.g. GEN / OBC / SC / ST (optional)

    @Size(max = 500)
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
        if (epicNo != null) epicNo = epicNo.trim().toUpperCase();
        if (name != null) name = name.trim();
    }

    // ---------- getters / setters ----------
    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getEpicNo() { return epicNo; }
    public void setEpicNo(String epicNo) { this.epicNo = epicNo; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
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

    /** "S/O Ram Kumar", "W/O ..." etc. */
    public String getRelationDisplay() {
        if (relativeName == null || relativeName.isBlank()) return "";
        String prefix = switch (relationType == null ? RelationType.FATHER : relationType) {
            case FATHER -> gender == Gender.FEMALE ? "D/O" : "S/O";
            case HUSBAND -> "W/O";
            case MOTHER -> gender == Gender.FEMALE ? "D/O (M)" : "S/O (M)";
            case OTHER -> "C/O";
        };
        return prefix + " " + relativeName;
    }

    public String getFullAddress() {
        StringBuilder sb = new StringBuilder();
        for (String p : new String[]{houseNo == null ? null : "H.No. " + houseNo, address, villageWard, district, pincode}) {
            if (p != null && !p.isBlank()) {
                if (!sb.isEmpty()) sb.append(", ");
                sb.append(p);
            }
        }
        return sb.toString();
    }
}
