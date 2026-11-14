package com.vidhi.voters.result;

import com.vidhi.voters.master.Candidate;
import jakarta.persistence.*;

import java.time.LocalDateTime;

/** Votes polled by one candidate at one booth. */
@Entity
@Table(name = "booth_results",
        uniqueConstraints = @UniqueConstraint(name = "uk_booth_result", columnNames = {"candidate_id", "booth_no"}),
        indexes = @Index(name = "idx_result_booth", columnList = "election_id, assembly_no, booth_no"))
public class BoothResult {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "election_id", nullable = false)
    private Long electionId;

    @Column(name = "assembly_no", nullable = false)
    private Integer assemblyNo;

    @Column(name = "booth_no", nullable = false)
    private Integer boothNo;

    @ManyToOne(optional = false, fetch = FetchType.EAGER)
    @JoinColumn(name = "candidate_id", nullable = false)
    private Candidate candidate;

    @Column(nullable = false)
    private int votes;

    @Column(name = "entered_by", length = 50)
    private String enteredBy;

    @Column(name = "updated_at")
    private LocalDateTime updatedAt;

    @PrePersist @PreUpdate
    void touch() { updatedAt = LocalDateTime.now(); }

    public Long getId() { return id; }
    public Long getElectionId() { return electionId; }
    public void setElectionId(Long electionId) { this.electionId = electionId; }
    public Integer getAssemblyNo() { return assemblyNo; }
    public void setAssemblyNo(Integer assemblyNo) { this.assemblyNo = assemblyNo; }
    public Integer getBoothNo() { return boothNo; }
    public void setBoothNo(Integer boothNo) { this.boothNo = boothNo; }
    public Candidate getCandidate() { return candidate; }
    public void setCandidate(Candidate candidate) { this.candidate = candidate; }
    public int getVotes() { return votes; }
    public void setVotes(int votes) { this.votes = votes; }
    public String getEnteredBy() { return enteredBy; }
    public void setEnteredBy(String enteredBy) { this.enteredBy = enteredBy; }
    public LocalDateTime getUpdatedAt() { return updatedAt; }
}
