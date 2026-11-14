package com.vidhi.voters.messaging.provider;

/**
 * Outcome of one send attempt.
 * @param retryable true for temporary problems (timeout, HTTP 429 / 5xx) worth another attempt
 */
public record SendResult(boolean success, String providerId, String error, boolean retryable) {
    public static SendResult ok(String id) { return new SendResult(true, id, null, false); }
    public static SendResult fail(String error, boolean retryable) { return new SendResult(false, null, error, retryable); }
}
