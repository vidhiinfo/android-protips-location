package com.vidhi.voters.admin;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;

import java.util.Collection;
import java.util.List;
import java.util.Optional;

public interface RoleRepository extends JpaRepository<Role, Long> {
    List<Role> findAllByOrderBySystemRoleDescNameAsc();
    Optional<Role> findByCodeIgnoreCase(String code);
    boolean existsByCodeIgnoreCase(String code);
    boolean existsByCodeIgnoreCaseAndIdNot(String code, Long id);
    List<Role> findByCodeIn(Collection<String> codes);

    @Query("select count(u) from AppUser u join u.roles r where r.id = :roleId")
    long countUsers(Long roleId);
}
