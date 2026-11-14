package com.vidhi.voters.service;

import com.vidhi.voters.model.Voter;
import jakarta.persistence.criteria.Predicate;
import org.springframework.data.jpa.domain.Specification;

import java.util.ArrayList;
import java.util.List;

/** Converts a {@link VoterFilter} into a JPA Criteria query (parameterised, SQL-injection safe). */
public final class VoterSpecifications {

    private VoterSpecifications() {}

    public static Specification<Voter> from(VoterFilter f) {
        return (root, query, cb) -> {
            List<Predicate> p = new ArrayList<>();

            if (notBlank(f.getQ())) {
                for (String term : f.getQ().trim().split("\\s+")) {
                    String like = "%" + escape(term.toLowerCase()) + "%";
                    p.add(cb.or(
                            cb.like(cb.lower(root.get("name")), like, '\\'),
                            cb.like(cb.lower(root.get("relativeName")), like, '\\'),
                            cb.like(cb.lower(root.get("epicNo")), like, '\\'),
                            cb.like(root.get("mobile"), like, '\\'),
                            cb.like(root.get("altMobile"), like, '\\'),
                            cb.like(cb.lower(root.get("houseNo")), like, '\\'),
                            cb.like(cb.lower(root.get("address")), like, '\\'),
                            cb.like(cb.lower(root.get("villageWard")), like, '\\'),
                            cb.like(cb.lower(root.get("boothName")), like, '\\')));
                }
            }
            if (notBlank(f.getName()))
                p.add(cb.like(cb.lower(root.get("name")), contains(f.getName()), '\\'));
            if (notBlank(f.getRelativeName()))
                p.add(cb.like(cb.lower(root.get("relativeName")), contains(f.getRelativeName()), '\\'));
            if (notBlank(f.getEpicNo()))
                p.add(cb.like(cb.lower(root.get("epicNo")), contains(f.getEpicNo()), '\\'));
            if (notBlank(f.getMobile()))
                p.add(cb.or(cb.like(root.get("mobile"), contains(f.getMobile()), '\\'),
                            cb.like(root.get("altMobile"), contains(f.getMobile()), '\\')));
            if (notBlank(f.getHouseNo()))
                p.add(cb.equal(cb.lower(root.get("houseNo")), f.getHouseNo().trim().toLowerCase()));
            if (f.getAssemblyNo() != null) p.add(cb.equal(root.get("assemblyNo"), f.getAssemblyNo()));
            if (f.getBoothNo() != null) p.add(cb.equal(root.get("boothNo"), f.getBoothNo()));
            if (f.getGender() != null) p.add(cb.equal(root.get("gender"), f.getGender()));
            if (f.getAgeMin() != null) p.add(cb.greaterThanOrEqualTo(root.get("age"), f.getAgeMin()));
            if (f.getAgeMax() != null) p.add(cb.lessThanOrEqualTo(root.get("age"), f.getAgeMax()));
            if (notBlank(f.getDistrict())) p.add(cb.equal(root.get("district"), f.getDistrict()));
            if (notBlank(f.getVillageWard())) p.add(cb.equal(root.get("villageWard"), f.getVillageWard()));
            if (Boolean.TRUE.equals(f.getHasMobile()))
                p.add(cb.and(cb.isNotNull(root.get("mobile")), cb.notEqual(root.get("mobile"), "")));
            if (Boolean.FALSE.equals(f.getHasMobile()))
                p.add(cb.or(cb.isNull(root.get("mobile")), cb.equal(root.get("mobile"), "")));

            return cb.and(p.toArray(Predicate[]::new));
        };
    }

    private static boolean notBlank(String s) { return s != null && !s.isBlank(); }

    private static String contains(String s) { return "%" + escape(s.trim().toLowerCase()) + "%"; }

    private static String escape(String s) {
        return s.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_");
    }
}
