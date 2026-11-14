package com.vidhi.voters.master;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

/** Assembly constituency (Vidhan Sabha). */
@Entity
@Table(name = "assemblies", uniqueConstraints = @UniqueConstraint(name = "uk_assembly_no", columnNames = "assembly_no"))
public class Assembly {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotNull(message = "{validation.required}") @Min(value = 1, message = "{validation.positive}")
    @Column(name = "assembly_no", nullable = false)
    private Integer assemblyNo;

    @NotBlank(message = "{validation.required}") @Size(max = 100, message = "{validation.size}")
    @Column(nullable = false, length = 100)
    private String name;

    @Size(max = 100, message = "{validation.size}")
    @Column(name = "name_local", length = 100)
    private String nameLocal;

    @Size(max = 100, message = "{validation.size}")
    @Column(length = 100)
    private String district;

    @Size(max = 100, message = "{validation.size}")
    @Column(length = 100)
    private String state;

    /** GEN / SC / ST reservation. */
    @Size(max = 10, message = "{validation.size}")
    @Column(name = "reserved_for", length = 10)
    private String reservedFor;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getNameLocal() { return nameLocal; }
    public void setNameLocal(String nameLocal) { this.nameLocal = nameLocal; }
    public String getDistrict() { return district; }
    public void setDistrict(String district) { this.district = district; }
    public String getState() { return state; }
    public void setState(String state) { this.state = state; }
    public String getReservedFor() { return reservedFor; }
    public void setReservedFor(String reservedFor) { this.reservedFor = reservedFor; }

    public String getDisplay() { return assemblyNo + " - " + name + (nameLocal == null ? "" : " / " + nameLocal); }
}
