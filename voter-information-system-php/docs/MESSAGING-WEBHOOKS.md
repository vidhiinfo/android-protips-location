# Delivery receipts, replies (inbox) and automatic greetings

## 1. Turn on the callback addresses

Add a long random secret to `.env` (at least 16 characters, letters and digits):

```
WEBHOOK_TOKEN=put-a-long-random-secret-here
```

Every callback address contains this secret. Anyone who does not know it gets "not found".
Replace `https://your-site/upvis` below with your own site address.

## 2. Twilio (SMS and WhatsApp)

* Delivery / read receipts: nothing to set. The application asks Twilio for them on every message it sends
  (`https://your-site/upvis/hooks/<WEBHOOK_TOKEN>/twilio/status`).
* Replies: Twilio Console > Phone Numbers (SMS) and Messaging > WhatsApp senders >
  "A message comes in" = `https://your-site/upvis/hooks/<WEBHOOK_TOKEN>/twilio/inbound` (HTTP POST).

## 3. WhatsApp Cloud API (Meta)

Add to `.env`:

```
WHATSAPP_VERIFY_TOKEN=any-word-you-choose
WHATSAPP_APP_SECRET=app secret from the Meta app (App settings > Basic)
```

Meta for Developers > your app > WhatsApp > Configuration > Webhook:

* Callback URL: `https://your-site/upvis/hooks/<WEBHOOK_TOKEN>/meta`
* Verify token: the same word as `WHATSAPP_VERIFY_TOKEN`
* Subscribe to the **messages** field (this brings both replies and delivered / read statuses).

## 4. Other SMS gateways (DLT providers)

If your gateway can call a URL for delivery reports or incoming SMS:

* Delivery report: `https://your-site/upvis/hooks/<WEBHOOK_TOKEN>/sms/status?id={message id}&status={delivered|failed}`
* Incoming SMS: `https://your-site/upvis/hooks/<WEBHOOK_TOKEN>/sms/inbound?from={mobile}&text={message}&id={message id}`

(`{...}` are the gateway's own placeholders; GET or POST both work.)

## 5. What happens

* Campaign page: Delivered / Read / Not delivered counts and a badge per message.
* Messages > Inbox: one conversation per number, linked to the voter with that mobile.
  A reply of **STOP** (or बंद / रोकें) adds the number to the opt-out list at once.
  WhatsApp allows free-text replies only within 24 hours of the voter's last message; after that send an approved template.

## 6. Automatic birthday / anniversary greetings

Messages > Automatic greetings: choose the occasion, a template (e.g. "Happy birthday {name}!") and the time.
Every day after that time a campaign is queued for the voters whose date of birth / anniversary is today, and the
normal sender delivers it. Without a cron job it is started by the first page visit after that time.
With cron (recommended), add next to the existing `messages:send` line:

```
*/15 * * * * cd /home/USER/public_html/upvis && php artisan vis:greetings >/dev/null 2>&1
```

Worker task reminders (Workers > Worker tasks) work the same way: set `TASK_REMINDER_CHANNEL=sms` or `whatsapp`
and optionally add `0 8 * * * ... php artisan vis:task-reminders`.
