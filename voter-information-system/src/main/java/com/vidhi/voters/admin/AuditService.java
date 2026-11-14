package com.vidhi.voters.admin;

import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Propagation;
import org.springframework.transaction.annotation.Transactional;

/** Records who changed what. Written in its own transaction so failures elsewhere don't lose the entry. */
@Service
public class AuditService {

    private final AuditLogRepository repo;

    public AuditService(AuditLogRepository repo) { this.repo = repo; }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void log(String action, String entity, Object entityId, String details) {
        log(currentUser(), action, entity, entityId, details);
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void log(String user, String action, String entity, Object entityId, String details) {
        repo.save(new AuditLog(user, action, entity, entityId == null ? null : String.valueOf(entityId), details));
    }

    public static String currentUser() {
        Authentication a = SecurityContextHolder.getContext().getAuthentication();
        return a == null ? "system" : a.getName();
    }
}
