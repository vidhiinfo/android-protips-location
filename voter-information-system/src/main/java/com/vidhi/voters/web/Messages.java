package com.vidhi.voters.web;

import org.springframework.context.MessageSource;
import org.springframework.context.i18n.LocaleContextHolder;
import org.springframework.stereotype.Component;

import java.util.Locale;

/** Small helper around MessageSource for Java code (exports, titles). */
@Component
public class Messages {

    private final MessageSource source;

    public Messages(MessageSource source) { this.source = source; }

    public String get(String key, Object... args) { return get(LocaleContextHolder.getLocale(), key, args); }

    public String get(Locale locale, String key, Object... args) {
        return source.getMessage(key, args.length == 0 ? null : args, key, locale);
    }
}
