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

    @Query("select distinct v.boothNo from Voter v where (:a is null or v.assemblyNo = :a) order by v.boothNo")
    List<Integer> findBooths(Integer a);

    @Query("select distinct v.district from Voter v where (:a is null or v.assemblyNo = :a) and v.district is not null and v.district <> '' order by v.district")
    List<String> findDistricts(Integer a);

    @Query("select distinct v.villageWard from Voter v where (:a is null or v.assemblyNo = :a) and v.villageWard is not null and v.villageWard <> '' order by v.villageWard")
    List<String> findVillages(Integer a);

    @Query("select v.gender, count(v) from Voter v group by v.gender")
    List<Object[]> countByGender();

    long countByAssemblyNo(Integer assemblyNo);

    /** [assemblyNo, boothNo, category, count] - social category per booth. */
    @Query("select v.assemblyNo, v.boothNo, upper(trim(coalesce(v.category, ''))), count(v) from Voter v" +
           " where (:assemblyNo is null or v.assemblyNo = :assemblyNo)" +
           " group by v.assemblyNo, v.boothNo, upper(trim(coalesce(v.category, ''))) order by v.assemblyNo, v.boothNo")
    List<Object[]> categoryByBooth(Integer assemblyNo);

    /** Voters per house (same house no. + address + village/ward) in an assembly. */
    @Query("select count(v) from Voter v where v.assemblyNo = :assemblyNo and v.houseNo is not null and v.houseNo <> ''" +
           " group by lower(v.houseNo), lower(coalesce(v.address, '')), lower(coalesce(v.villageWard, ''))")
    List<Long> householdSizes(Integer assemblyNo);

    @Query("select v.gender, count(v) from Voter v where (:assemblyNo is null or v.assemblyNo = :assemblyNo) group by v.gender")
    List<Object[]> countByGender(Integer assemblyNo);

    @Query("select distinct v.boothNo from Voter v where v.assemblyNo = :assemblyNo order by v.boothNo")
    List<Integer> findBoothsOfAssembly(Integer assemblyNo);

    // ---------------- report aggregates ----------------
    String COUNTS = """
            count(v),
            sum(case when v.gender = com.vidhi.voters.model.Gender.MALE then 1 else 0 end),
            sum(case when v.gender = com.vidhi.voters.model.Gender.FEMALE then 1 else 0 end),
            sum(case when v.gender = com.vidhi.voters.model.Gender.OTHER then 1 else 0 end),
            sum(case when v.mobile is not null and v.mobile <> '' then 1 else 0 end),
            sum(case when v.age between 18 and 25 then 1 else 0 end),
            sum(case when v.age >= 60 then 1 else 0 end)""";

    /** [assemblyNo, max(assemblyName), booths, total, male, female, other, withMobile, age18to25, age60plus] */
    @Query("select v.assemblyNo, max(v.assemblyName), count(distinct v.boothNo), " + COUNTS +
           " from Voter v group by v.assemblyNo order by v.assemblyNo")
    List<Object[]> assemblySummary();

    /** [assemblyNo, boothNo, max(boothName), total, male, female, other, withMobile, age18to25, age60plus] */
    @Query("select v.assemblyNo, v.boothNo, max(v.boothName), " + COUNTS +
           " from Voter v where (:assemblyNo is null or v.assemblyNo = :assemblyNo)" +
           " group by v.assemblyNo, v.boothNo order by v.assemblyNo, v.boothNo")
    List<Object[]> boothSummary(Integer assemblyNo);

    /** [villageWard, total, male, female, other, withMobile, age18to25, age60plus] */
    @Query("select coalesce(v.villageWard, '-'), " + COUNTS +
           " from Voter v where (:assemblyNo is null or v.assemblyNo = :assemblyNo)" +
           " group by v.villageWard order by count(v) desc")
    List<Object[]> villageSummary(Integer assemblyNo);

    /** [age, gender, count] */
    @Query("select v.age, v.gender, count(v) from Voter v where (:assemblyNo is null or v.assemblyNo = :assemblyNo)" +
           " and (:boothNo is null or v.boothNo = :boothNo) group by v.age, v.gender")
    List<Object[]> ageGender(Integer assemblyNo, Integer boothNo);
}
