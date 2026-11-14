package com.vidhi.voters.messaging;

import org.springframework.boot.context.properties.ConfigurationProperties;

import java.time.LocalTime;

/**
 * Messaging settings (prefix app.messaging). Provider credentials come from environment variables,
 * never from the database or the UI. With provider "log" (the default) nothing leaves the server:
 * messages are recorded as sent in dry-run mode.
 */
@ConfigurationProperties(prefix = "app.messaging")
public class MessagingProperties {

    /** log | twilio | http */
    private String smsProvider = "log";
    /** log | twilio | meta */
    private String whatsappProvider = "log";
    /** Country code added to 10-digit mobile numbers. */
    private String countryCode = "+91";
    /** Maximum messages sent per second (provider limits / DLT throughput). */
    private int ratePerSecond = 5;
    /** Messages are only sent inside this daily window (TRAI: promotional SMS 09:00-21:00). */
    private LocalTime sendFrom = LocalTime.of(9, 0);
    private LocalTime sendUntil = LocalTime.of(21, 0);
    private String timeZone = "Asia/Kolkata";
    /** Block sending from 48 hours before the poll day of the current election until the poll day ends. */
    private boolean enforceSilencePeriod = true;
    /** Retries for temporary provider errors (timeouts, HTTP 429/5xx). */
    private int maxAttempts = 3;
    /** Set to false to stop the background sender (used by tests). */
    private boolean workerEnabled = true;

    private final Twilio twilio = new Twilio();
    private final Meta meta = new Meta();
    private final Http http = new Http();

    public static class Twilio {
        private String accountSid = "";
        private String authToken = "";
        private String smsFrom = "";          // Twilio number or Messaging Service SID (MG...)
        private String whatsappFrom = "";     // e.g. +14155238886 (without the whatsapp: prefix)
        private String baseUrl = "https://api.twilio.com";
        public String getAccountSid() { return accountSid; }
        public void setAccountSid(String v) { accountSid = v; }
        public String getAuthToken() { return authToken; }
        public void setAuthToken(String v) { authToken = v; }
        public String getSmsFrom() { return smsFrom; }
        public void setSmsFrom(String v) { smsFrom = v; }
        public String getWhatsappFrom() { return whatsappFrom; }
        public void setWhatsappFrom(String v) { whatsappFrom = v; }
        public String getBaseUrl() { return baseUrl; }
        public void setBaseUrl(String v) { baseUrl = v; }
    }

    /** WhatsApp Business Cloud API (Meta). */
    public static class Meta {
        private String phoneNumberId = "";
        private String accessToken = "";
        private String apiVersion = "v21.0";
        private String baseUrl = "https://graph.facebook.com";
        public String getPhoneNumberId() { return phoneNumberId; }
        public void setPhoneNumberId(String v) { phoneNumberId = v; }
        public String getAccessToken() { return accessToken; }
        public void setAccessToken(String v) { accessToken = v; }
        public String getApiVersion() { return apiVersion; }
        public void setApiVersion(String v) { apiVersion = v; }
        public String getBaseUrl() { return baseUrl; }
        public void setBaseUrl(String v) { baseUrl = v; }
    }

    /**
     * Generic HTTP SMS gateway (MSG91, Textlocal, Fast2SMS, ...). The url and body may contain
     * {mobile} (10 digits), {e164}, {message}, {templateId}, {senderId}, {entityId}; values are URL-encoded
     * for GET/form bodies and JSON-escaped when contentType is application/json.
     */
    public static class Http {
        private String method = "GET";
        private String url = "";
        private String body = "";
        private String contentType = "application/x-www-form-urlencoded";
        private String authHeader = "";       // e.g. "authkey: xxxx" or "Authorization: Bearer xxxx"
        private String senderId = "";         // DLT header (6 characters)
        private String entityId = "";         // DLT principal entity id
        private String successContains = "";  // optional text the response must contain
        public String getMethod() { return method; }
        public void setMethod(String v) { method = v; }
        public String getUrl() { return url; }
        public void setUrl(String v) { url = v; }
        public String getBody() { return body; }
        public void setBody(String v) { body = v; }
        public String getContentType() { return contentType; }
        public void setContentType(String v) { contentType = v; }
        public String getAuthHeader() { return authHeader; }
        public void setAuthHeader(String v) { authHeader = v; }
        public String getSenderId() { return senderId; }
        public void setSenderId(String v) { senderId = v; }
        public String getEntityId() { return entityId; }
        public void setEntityId(String v) { entityId = v; }
        public String getSuccessContains() { return successContains; }
        public void setSuccessContains(String v) { successContains = v; }
    }

    public String getSmsProvider() { return smsProvider; }
    public void setSmsProvider(String v) { smsProvider = v; }
    public String getWhatsappProvider() { return whatsappProvider; }
    public void setWhatsappProvider(String v) { whatsappProvider = v; }
    public String getCountryCode() { return countryCode; }
    public void setCountryCode(String v) { countryCode = v; }
    public int getRatePerSecond() { return ratePerSecond; }
    public void setRatePerSecond(int v) { ratePerSecond = Math.max(1, v); }
    public LocalTime getSendFrom() { return sendFrom; }
    public void setSendFrom(LocalTime v) { sendFrom = v; }
    public LocalTime getSendUntil() { return sendUntil; }
    public void setSendUntil(LocalTime v) { sendUntil = v; }
    public String getTimeZone() { return timeZone; }
    public void setTimeZone(String v) { timeZone = v; }
    public boolean isEnforceSilencePeriod() { return enforceSilencePeriod; }
    public void setEnforceSilencePeriod(boolean v) { enforceSilencePeriod = v; }
    public int getMaxAttempts() { return maxAttempts; }
    public void setMaxAttempts(int v) { maxAttempts = Math.max(1, v); }
    public boolean isWorkerEnabled() { return workerEnabled; }
    public void setWorkerEnabled(boolean v) { workerEnabled = v; }
    public Twilio getTwilio() { return twilio; }
    public Meta getMeta() { return meta; }
    public Http getHttp() { return http; }

    public String provider(Channel c) { return c == Channel.SMS ? smsProvider : whatsappProvider; }
}
