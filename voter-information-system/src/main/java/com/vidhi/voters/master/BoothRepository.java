package com.vidhi.voters.master;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface BoothRepository extends JpaRepository<Booth, Long> {
    List<Booth> findAllByOrderByAssemblyNoAscBoothNoAsc();
    List<Booth> findByAssemblyNoOrderByBoothNoAsc(Integer assemblyNo);
    Optional<Booth> findByAssemblyNoAndBoothNo(Integer assemblyNo, Integer boothNo);
    boolean existsByAssemblyNoAndBoothNoAndIdNot(Integer assemblyNo, Integer boothNo, Long id);
    boolean existsByAssemblyNoAndBoothNo(Integer assemblyNo, Integer boothNo);
    long countByAssemblyNo(Integer assemblyNo);
}
