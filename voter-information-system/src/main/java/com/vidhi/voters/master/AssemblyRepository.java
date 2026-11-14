package com.vidhi.voters.master;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface AssemblyRepository extends JpaRepository<Assembly, Long> {
    List<Assembly> findAllByOrderByAssemblyNoAsc();
    Optional<Assembly> findByAssemblyNo(Integer assemblyNo);
    boolean existsByAssemblyNoAndIdNot(Integer assemblyNo, Long id);
    boolean existsByAssemblyNo(Integer assemblyNo);
}
