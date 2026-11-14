package com.vidhi.voters.messaging;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;

import java.util.List;
import java.util.Set;

public interface OptOutRepository extends JpaRepository<OptOut, Long> {
    List<OptOut> findAllByOrderByCreatedAtDesc();

    @Query("select o.mobile from OptOut o where o.channel is null or o.channel = :channel")
    Set<String> blockedNumbers(Channel channel);

    /** Numbers that asked not to be contacted at all (channel = every channel). */
    @Query("select o.mobile from OptOut o where o.channel is null")
    Set<String> blockedEverywhere();

    boolean existsByMobileAndChannel(String mobile, Channel channel);

    boolean existsByMobileAndChannelIsNull(String mobile);
}
