package com.vidhi.voters.web;

import com.vidhi.voters.config.Languages;
import org.springframework.context.i18n.LocaleContextHolder;
import org.springframework.web.bind.annotation.ControllerAdvice;
import org.springframework.web.bind.annotation.ModelAttribute;

import java.util.List;

/** Language information available to every template (switcher, <html lang/dir>, RTL stylesheet). */
@ControllerAdvice
public class GlobalModelAdvice {

    private final com.vidhi.voters.config.WorkContext work;

    public GlobalModelAdvice(com.vidhi.voters.config.WorkContext work) { this.work = work; }

    /** Assembly chosen at login (shown in the navbar). */
    @ModelAttribute("work")
    public com.vidhi.voters.config.WorkContext work() { return work; }

    @ModelAttribute("reportIds")
    public List<String> reportIds() { return com.vidhi.voters.report.ReportService.REPORTS; }

    @ModelAttribute("languages")
    public List<Languages.Lang> languages() { return Languages.ALL; }

    @ModelAttribute("currentLang")
    public Languages.Lang currentLang() { return Languages.of(LocaleContextHolder.getLocale()); }
}
