package com.vidhi.voters.result;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;

import java.util.List;
import java.util.Optional;

public interface BoothResultRepository extends JpaRepository<BoothResult, Long> {
    List<BoothResult> findByElectionIdAndAssemblyNo(Long electionId, Integer assemblyNo);
    List<BoothResult> findByElectionIdAndAssemblyNoAndBoothNo(Long electionId, Integer assemblyNo, Integer boothNo);
    Optional<BoothResult> findByCandidateIdAndBoothNo(Long candidateId, Integer boothNo);
    boolean existsByCandidateId(Long candidateId);

    @Query("select distinct r.boothNo from BoothResult r where r.electionId = :electionId and r.assemblyNo = :assemblyNo")
    List<Integer> reportedBooths(Long electionId, Integer assemblyNo);
}
