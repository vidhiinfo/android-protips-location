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
    private final com.vidhi.voters.config.BrandingProperties branding;

    public GlobalModelAdvice(com.vidhi.voters.config.WorkContext work, com.vidhi.voters.config.BrandingProperties branding) {
        this.work = work;
        this.branding = branding;
    }

    @ModelAttribute("branding")
    public com.vidhi.voters.config.BrandingProperties branding() { return branding; }

    /** Assembly chosen at login (shown in the navbar). */
    @ModelAttribute("work")
    public com.vidhi.voters.config.WorkContext work() { return work; }

    @ModelAttribute("reportIds")
    public List<String> reportIds() {
        var a = org.springframework.security.core.context.SecurityContextHolder.getContext().getAuthentication();
        boolean admin = a != null && a.getAuthorities().stream().anyMatch(g -> g.getAuthority().equals("REPORT_SENSITIVE"));
        return com.vidhi.voters.report.ReportService.REPORTS.stream()
                .filter(r -> admin || !com.vidhi.voters.report.ReportService.ADMIN_ONLY.contains(r)).toList();
    }

    @ModelAttribute("languages")
    public List<Languages.Lang> languages() { return Languages.ALL; }

    @ModelAttribute("currentLang")
    public Languages.Lang currentLang() { return Languages.of(LocaleContextHolder.getLocale()); }
}
