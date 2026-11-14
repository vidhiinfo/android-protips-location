package com.vidhi.voters.messaging;

import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface CampaignRepository extends JpaRepository<Campaign, Long> {
    Page<Campaign> findAllByOrderByIdDesc(Pageable pageable);
    List<Campaign> findByStatusIn(List<Campaign.Status> statuses);
    boolean existsByTemplateId(Long templateId);
}
