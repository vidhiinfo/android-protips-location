package com.vidhi.voters.master;

import org.springframework.context.annotation.Configuration;
import org.springframework.format.FormatterRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

/** Lets forms bind <select name="election"> / <select name="party"> by id. */
@Configuration
public class MasterConverters implements WebMvcConfigurer {
    private final ElectionRepository elections;
    private final PartyRepository parties;

    public MasterConverters(ElectionRepository elections, PartyRepository parties) {
        this.elections = elections;
        this.parties = parties;
    }

    @Override
    public void addFormatters(FormatterRegistry registry) {
        registry.addConverter(String.class, Election.class, s -> s.isBlank() ? null : elections.findById(Long.valueOf(s)).orElse(null));
        registry.addConverter(String.class, Party.class, s -> s.isBlank() ? null : parties.findById(Long.valueOf(s)).orElse(null));
    }
}
