package com.vidhi.voters.admin;

import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;

public interface AuditLogRepository extends JpaRepository<AuditLog, Long> {
    @Query("""
           select a from AuditLog a
           where (:user is null or lower(a.username) like lower(concat('%', :user, '%')))
             and (:action is null or a.action = :action)
             and (:entity is null or a.entity = :entity)
           order by a.at desc, a.id desc""")
    Page<AuditLog> search(String user, String action, String entity, Pageable pageable);
}
