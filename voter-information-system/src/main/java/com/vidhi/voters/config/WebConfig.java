package com.vidhi.voters.config;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.servlet.LocaleResolver;
import org.springframework.web.servlet.config.annotation.InterceptorRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;
import org.springframework.web.servlet.i18n.CookieLocaleResolver;
import org.springframework.web.servlet.i18n.LocaleChangeInterceptor;

import java.time.Duration;
import java.util.Locale;

/**
 * Language selection: "?lang=hi" on any URL switches the UI language and remembers it in a cookie for a year.
 * Unsupported codes fall back to English.
 */
@Configuration
@org.springframework.boot.context.properties.EnableConfigurationProperties(BrandingProperties.class)
public class WebConfig implements WebMvcConfigurer {

    @Bean
    public LocaleResolver localeResolver() {
        CookieLocaleResolver r = new CookieLocaleResolver("vis-lang") {
            @Override
            public void setLocale(HttpServletRequest req, HttpServletResponse res, Locale locale) {
                super.setLocale(req, res, locale == null ? null : Languages.of(locale).locale());
            }
        };
        r.setDefaultLocale(Languages.DEFAULT.locale());
        r.setCookieMaxAge(Duration.ofDays(365));
        r.setCookieSameSite("Lax");
        // first visit: use the browser's language if we support it
        r.setDefaultLocaleFunction(req -> Languages.of(req.getLocale()).locale());
        return r;
    }

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        LocaleChangeInterceptor i = new LocaleChangeInterceptor();
        i.setParamName("lang");
        i.setIgnoreInvalidLocale(true);
        registry.addInterceptor(i);
    }
}
