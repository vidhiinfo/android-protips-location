package com.vidhi.voters.master;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

import java.time.LocalDate;

@Entity
@Table(name = "elections")
public class Election {
    public enum Type { ASSEMBLY, LOK_SABHA, MUNICIPAL, PANCHAYAT, BY_ELECTION }

    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotBlank(message = "{validation.required}") @Size(max = 150, message = "{validation.size}")
    @Column(nullable = false, length = 150)
    private String name;

    @NotNull(message = "{validation.required}")
    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 20)
    private Type type = Type.ASSEMBLY;

    @NotNull(message = "{validation.required}") @Min(value = 1950, message = "{validation.year}") @Max(value = 2100, message = "{validation.year}")
    @Column(name = "election_year", nullable = false)
    private Integer year;

    @org.springframework.format.annotation.DateTimeFormat(iso = org.springframework.format.annotation.DateTimeFormat.ISO.DATE)
    @Column(name = "poll_date")
    private LocalDate pollDate;

    /** Only one election is "current"; it is pre-selected on the results screens. */
    @Column(nullable = false)
    private boolean current;

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public Type getType() { return type; }
    public void setType(Type type) { this.type = type; }
    public Integer getYear() { return year; }
    public void setYear(Integer year) { this.year = year; }
    public LocalDate getPollDate() { return pollDate; }
    public void setPollDate(LocalDate pollDate) { this.pollDate = pollDate; }
    public boolean isCurrent() { return current; }
    public void setCurrent(boolean current) { this.current = current; }
}
