package com.vidhi.voters.messaging;

import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Modifying;
import org.springframework.data.jpa.repository.Query;

import java.util.List;

public interface OutboxRepository extends JpaRepository<OutboxMessage, Long> {

    /** Next messages to send for a campaign. */
    @Query("select o from OutboxMessage o where o.campaignId = :campaignId and o.status = :status order by o.id")
    List<OutboxMessage> nextBatch(Long campaignId, OutboxMessage.Status status, Pageable pageable);

    Page<OutboxMessage> findByCampaignIdOrderByIdAsc(Long campaignId, Pageable pageable);

    Page<OutboxMessage> findByCampaignIdAndStatusOrderByIdAsc(Long campaignId, OutboxMessage.Status status, Pageable pageable);

    long countByCampaignIdAndStatus(Long campaignId, OutboxMessage.Status status);

    @Modifying
    @Query("update OutboxMessage o set o.status = com.vidhi.voters.messaging.OutboxMessage.Status.SKIPPED, o.error = :reason " +
           "where o.campaignId = :campaignId and o.status = com.vidhi.voters.messaging.OutboxMessage.Status.QUEUED")
    int skipQueued(Long campaignId, String reason);

    /** After a crash, rows left in SENDING may or may not have gone out: mark them failed rather than risk a duplicate. */
    @Modifying
    @Query("update OutboxMessage o set o.status = com.vidhi.voters.messaging.OutboxMessage.Status.FAILED, " +
           "o.error = 'Interrupted (server restarted while sending)' " +
           "where o.status = com.vidhi.voters.messaging.OutboxMessage.Status.SENDING")
    int failInterrupted();
}
