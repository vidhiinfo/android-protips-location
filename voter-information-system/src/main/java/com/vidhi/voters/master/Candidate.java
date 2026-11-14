package com.vidhi.voters.master;

import jakarta.persistence.*;
import jakarta.validation.constraints.*;

/** A candidate contesting one assembly seat in one election. */
@Entity
@Table(name = "candidates", indexes = @Index(name = "idx_candidate_contest", columnList = "election_id, assembly_no"))
public class Candidate {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @NotNull(message = "{validation.required}")
    @ManyToOne(optional = false, fetch = FetchType.EAGER)
    @JoinColumn(name = "election_id", nullable = false)
    private Election election;

    @NotNull(message = "{validation.required}") @Min(value = 1, message = "{validation.positive}")
    @Column(name = "assembly_no", nullable = false)
    private Integer assemblyNo;

    @NotBlank(message = "{validation.required}") @Size(max = 120, message = "{validation.size}")
    @Column(nullable = false, length = 120)
    private String name;

    @Size(max = 120, message = "{validation.size}")
    @Column(name = "name_local", length = 120)
    private String nameLocal;

    @ManyToOne(fetch = FetchType.EAGER)
    @JoinColumn(name = "party_id")
    private Party party;                  // null = independent

    @Min(value = 1, message = "{validation.positive}")
    @Column(name = "ballot_no")
    private Integer ballotNo;             // position on the EVM / ballot

    public Long getId() { return id; }
    public void setId(Long id) { this.id = id; }
    public Election getElection() { return election; }
    public void setElection(Election election) { this.election = election; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public String getName() { return name; }
    public void setName(String name) { this.name = name; }
    public String getNameLocal() { return nameLocal; }
    public void setNameLocal(String nameLocal) { this.nameLocal = nameLocal; }
    public Party getParty() { return party; }
    public void setParty(Party party) { this.party = party; }
    public Integer getBallotNo() { return ballotNo; }
    public void setBallotNo(Integer ballotNo) { this.ballotNo = ballotNo; }

    public String getPartyShort() { return party == null ? "IND" : party.getShortName(); }
}
