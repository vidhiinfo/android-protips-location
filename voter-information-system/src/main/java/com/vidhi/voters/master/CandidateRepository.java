package com.vidhi.voters.master;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface CandidateRepository extends JpaRepository<Candidate, Long> {
    List<Candidate> findByElectionIdAndAssemblyNoOrderByBallotNoAscNameAsc(Long electionId, Integer assemblyNo);
    List<Candidate> findByElectionIdOrderByAssemblyNoAscBallotNoAsc(Long electionId);
    List<Candidate> findAllByOrderByElectionIdDescAssemblyNoAscBallotNoAsc();
    boolean existsByPartyId(Long partyId);
    boolean existsByElectionId(Long electionId);
}
