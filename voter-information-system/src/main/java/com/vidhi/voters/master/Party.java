package com.vidhi.voters.master;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

/** Political party. Use short name "IND" for independents and "NOTA" for None of the Above. */
@Entity
@Table(name = "parties", uniqueConstraints = @UniqueConstraint(name = "uk_party_short", columnNames = "short_name"))
public class Party {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotBlank(message = "{validation.required}") @Size(max = 150, message = "{validation.size}")
    @Column(nullable = false, length = 150)
    private String name;

    @Size(max = 150, message = "{validation.size}")
    @Column(name = "name_local", length = 150)
    private String nameLocal;

    @NotBlank(message = "{validation.required}") @Size(max = 20, message = "{validation.size}")
    @Column(name = "short_name", nullable = false, length = 20)
    private String shortName;

    @Size(max = 100, message = "{validation.size}")
    @Column(length = 100)
    private String symbol;

    /** Hex colour used in result charts, e.g. #FF9933. */
    @Pattern(regexp = "^$|^#[0-9A-Fa-f]{6}$", message = "{validation.color}")
    @Column(length = 7)
    private String color;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getNameLocal() { return nameLocal; }
    public void setNameLocal(String nameLocal) { this.nameLocal = nameLocal; }
    public String getShortName() { return shortName; }
    public void setShortName(String shortName) { this.shortName = shortName == null ? null : shortName.trim().toUpperCase(java.util.Locale.ROOT); }
    public String getSymbol() { return symbol; }
    public void setSymbol(String symbol) { this.symbol = symbol; }
    public String getColor() { return color; }
    public void setColor(String color) { this.color = color; }
}
