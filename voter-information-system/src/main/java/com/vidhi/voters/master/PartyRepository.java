package com.vidhi.voters.master;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface PartyRepository extends JpaRepository<Party, Long> {
    List<Party> findAllByOrderByNameAsc();
    boolean existsByShortNameIgnoreCaseAndIdNot(String shortName, Long id);
    boolean existsByShortNameIgnoreCase(String shortName);
}
