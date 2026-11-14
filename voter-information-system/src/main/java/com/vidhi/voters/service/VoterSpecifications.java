package com.vidhi.voters.service;

import com.vidhi.voters.model.Voter;
import jakarta.persistence.criteria.CriteriaBuilder;
import jakarta.persistence.criteria.Root;
import jakarta.persistence.criteria.Predicate;
import org.springframework.data.jpa.domain.Specification;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/**
 * Converts a {@link VoterFilter} into a JPA Criteria query (parameterised, SQL-injection safe).
 * Text search covers both the English and the local-language columns, and search terms are
 * NFC-normalised the same way stored values are, so Hindi / Bengali / Urdu text typed with any keyboard matches.
 */
public final class VoterSpecifications {

    private VoterSpecifications() {}

    public static Specification<Voter> from(VoterFilter f) {
        return (root, query, cb) -> {
            List<Predicate> p = new ArrayList<>();

            if (notBlank(f.getQ())) {
                for (String term : Voter.nfc(f.getQ()).split("\\s+")) {
                    String like = "%" + escape(term.toLowerCase(Locale.ROOT)) + "%";
                    p.add(cb.or(
                            cb.like(cb.lower(root.get("name")), like, '\\'),
                            cb.like(cb.lower(root.get("nameLocal")), like, '\\'),
                            cb.like(cb.lower(root.get("relativeName")), like, '\\'),
                            cb.like(cb.lower(root.get("relativeNameLocal")), like, '\\'),
                            cb.like(cb.lower(root.get("addressLocal")), like, '\\'),
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
                p.add(anyLike(cb, root, contains(f.getName()), "name", "nameLocal"));
            if (notBlank(f.getRelativeName()))
                p.add(anyLike(cb, root, contains(f.getRelativeName()), "relativeName", "relativeNameLocal"));
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

    private static Predicate anyLike(CriteriaBuilder cb, Root<Voter> root, String like, String... fields) {
        return cb.or(java.util.Arrays.stream(fields)
                .map(fl -> cb.like(cb.lower(root.get(fl)), like, '\\'))
                .toArray(Predicate[]::new));
    }

    private static boolean notBlank(String s) { return s != null && !s.isBlank(); }

    private static String contains(String s) { return "%" + escape(Voter.nfc(s).toLowerCase(Locale.ROOT)) + "%"; }

    private static String escape(String s) {
        return s.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_");
    }
}
