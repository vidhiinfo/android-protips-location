package com.vidhi.voters.master;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

/** Polling booth / part of an assembly constituency. */
@Entity
@Table(name = "booths", uniqueConstraints = @UniqueConstraint(name = "uk_booth", columnNames = {"assembly_no", "booth_no"}))
public class Booth {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotNull(message = "{validation.required}") @Min(value = 1, message = "{validation.positive}")
    @Column(name = "assembly_no", nullable = false)
    private Integer assemblyNo;

    @NotNull(message = "{validation.required}") @Min(value = 1, message = "{validation.positive}")
    @Column(name = "booth_no", nullable = false)
    private Integer boothNo;

    @NotBlank(message = "{validation.required}") @Size(max = 200, message = "{validation.size}")
    @Column(nullable = false, length = 200)
    private String name;                 // polling station

    @Size(max = 200, message = "{validation.size}")
    @Column(name = "name_local", length = 200)
    private String nameLocal;

    @Size(max = 300, message = "{validation.size}")
    @Column(length = 300)
    private String location;

    @Size(max = 100, message = "{validation.size}")
    @Column(name = "village_ward", length = 100)
    private String villageWard;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public Integer getBoothNo() { return boothNo; }
    public void setBoothNo(Integer boothNo) { this.boothNo = boothNo; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getNameLocal() { return nameLocal; }
    public void setNameLocal(String nameLocal) { this.nameLocal = nameLocal; }
    public String getLocation() { return location; }
    public void setLocation(String location) { this.location = location; }
    public String getVillageWard() { return villageWard; }
    public void setVillageWard(String villageWard) { this.villageWard = villageWard; }
}
