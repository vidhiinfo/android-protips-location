package com.vidhi.voters.messaging;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface MessageTemplateRepository extends JpaRepository<MessageTemplate, Long> {
    List<MessageTemplate> findAllByOrderByChannelAscLanguageAscNameAsc();
    List<MessageTemplate> findByChannelAndActiveTrueOrderByLanguageAscNameAsc(Channel channel);
}
