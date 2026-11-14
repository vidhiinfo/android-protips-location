package com.vidhi.voters.repo;

import com.vidhi.voters.model.Voter;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.JpaSpecificationExecutor;
import org.springframework.data.jpa.repository.Query;

import java.util.List;
import java.util.Optional;

public interface VoterRepository extends JpaRepository<Voter, Long>, JpaSpecificationExecutor<Voter> {

    Optional<Voter> findByEpicNoIgnoreCase(String epicNo);

    boolean existsByEpicNoIgnoreCaseAndIdNot(String epicNo, Long id);

    boolean existsByEpicNoIgnoreCase(String epicNo);

    @Query("select distinct v.assemblyNo, v.assemblyName from Voter v order by v.assemblyNo")
    List<Object[]> findAssemblies();

    @Query("select distinct v.boothNo from Voter v order by v.boothNo")
    List<Integer> findBooths();

    @Query("select distinct v.district from Voter v where v.district is not null and v.district <> '' order by v.district")
    List<String> findDistricts();

    @Query("select distinct v.villageWard from Voter v where v.villageWard is not null and v.villageWard <> '' order by v.villageWard")
    List<String> findVillages();

    @Query("select v.gender, count(v) from Voter v group by v.gender")
    List<Object[]> countByGender();
}
