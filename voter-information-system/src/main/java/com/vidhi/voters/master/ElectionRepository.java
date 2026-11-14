package com.vidhi.voters.master;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Modifying;
import org.springframework.data.jpa.repository.Query;

import java.util.List;
import java.util.Optional;

public interface ElectionRepository extends JpaRepository<Election, Long> {
    List<Election> findAllByOrderByYearDescNameAsc();
    Optional<Election> findFirstByCurrentTrue();

    @Modifying
    @Query("update Election e set e.current = false where e.id <> :id")
    void clearCurrentExcept(Long id);
}
