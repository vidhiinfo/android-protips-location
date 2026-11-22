<?php

/*
| Voter Information System settings. Values come from .env (see .env.example).
*/
return [

    // UI languages: code => [native name, English name, right-to-left]
    'languages' => [
        'en' => ['English', 'English', false],
        'hi' => ['हिन्दी', 'Hindi', false],
        'bn' => ['বাংলা', 'Bengali', false],
        'ur' => ['اردو', 'Urdu', true],
        'mr' => ['मराठी', 'Marathi', false],
        'gu' => ['ગુજરાતી', 'Gujarati', false],
        'pa' => ['ਪੰਜਾਬੀ', 'Punjabi', false],
        'ta' => ['தமிழ்', 'Tamil', false],
        'te' => ['తెలుగు', 'Telugu', false],
    ],

    // first administrator, created automatically when no user can manage users
    'admin' => [
        'username' => env('ADMIN_USER', 'admin'),
        'password' => env('ADMIN_PASSWORD', 'admin@123'),
    ],

    // users: "app" = tables app_users / roles / role_permissions / user_roles; "external" = your existing tables
    // mobile app store links shown on the /mobile-app page (empty = not published yet)
    'app' => ['play_url' => env('APP_PLAY_URL'), 'app_store_url' => env('APP_STORE_URL')],

    // printed at the bottom of every voter slip (Voters > Slips)
    'slip_note' => env('SLIP_NOTE', 'Poll: 7:00 AM to 6:00 PM. Carry your EPIC card or another approved photo ID.'),

    'security' => [
        'user_source' => env('USER_SOURCE', 'app'),
        // two-step login: none (each user may switch it on) | admins (users who manage users) | all
        'otp_required' => env('OTP_REQUIRED', 'none'),
        'otp_trust_days' => (int) env('OTP_TRUST_DAYS', 30),   // a device can skip the code for this many days
        'external' => [
            // columns: username, password, enabled [, assembly_no] [, full_name]
            'users_query' => env('USERS_QUERY', 'select username, password, enabled, assembly_no, full_name from users where username = ?'),
            // one role name per row
            'roles_query' => env('ROLES_QUERY', 'select r.role_name from user_roles ur join users u on u.id = ur.user_id join roles r on r.id = ur.role_id where u.username = ?'),
            // "SUPERVISOR=ADMIN,FIELD_STAFF=VIEWER"
            'role_map' => env('USERS_ROLE_MAP', ''),
            // optional separate database connection name from config/database.php (empty = default)
            'connection' => env('USERS_DB_CONNECTION', ''),
            // how the password column is stored: bcrypt (also {bcrypt} prefixed) | sha256 | md5 | noop (plain text, not recommended)
            'password_encoding' => env('USERS_PASSWORD_ENCODING', 'bcrypt'),
        ],
    ],

    'export' => [
        // PDF / print of voter lists needs about 4.5 MB of PHP memory per 1,000 rows
        'pdf_max_rows' => (int) env('PDF_MAX_ROWS', 5000),
    ],

    // database backup to storage/app/backups (Admin > Backups): daily cron "php artisan vis:backup", or once a day after a page visit
    'backup' => [
        'keep' => (int) env('BACKUP_KEEP', 14),                 // number of backups kept (one a day = two weeks)
        'every_hours' => (int) env('BACKUP_EVERY_HOURS', 24),
        'on_visit' => (bool) env('BACKUP_ON_VISIT', true),      // fallback when there is no cron job
    ],

    // seconds that drop-down lists and header counts (read from the whole assembly) are kept; 0 = always fresh
    'lookup_cache_seconds' => (int) env('LOOKUP_CACHE_SECONDS', 300),

    // seconds a quick search of the voter list may run before it is stopped (protects shared hosting); 0 = no limit
    'search_seconds' => (int) env('SEARCH_SECONDS', 8),

    // State summary across division installations (see docs: each division serves /state-feed, the state site collects them)
    'state' => [
        'feed_token' => env('STATE_FEED_TOKEN', ''),         // division site: secret the state site must send; empty = no feed
        'feed_name' => env('STATE_FEED_NAME', ''),           // division site: name shown on the state summary (default: product name)
        'feed_seconds' => (int) env('STATE_FEED_SECONDS', 120),
        'sources' => env('STATE_SOURCES', ''),               // state site: "Name|https://url|TOKEN;Name|https://url|TOKEN"
        'refresh_seconds' => (int) env('STATE_REFRESH_SECONDS', 180),
    ],

    // consent and privacy (DPDP Act 2023)
    'privacy' => [
        'consent_required' => (bool) env('PRIVACY_CONSENT_REQUIRED', false),   // true: messages only to voters who agreed to be contacted
        'request_due_days' => (int) env('PRIVACY_REQUEST_DAYS', 30),         // target time to complete a privacy request
        'message_retention_days' => (int) env('MESSAGE_RETENTION_DAYS', 365), // message texts and numbers removed from the outbox after this
    ],

    // false hides Ward No everywhere (lists, forms, filters, exports, access areas), e.g. where the rolls have no wards
    'show_ward_no' => (bool) env('SHOW_WARD_NO', true),

    // map of surveyed houses (Field survey > Map). Tiles are images only; no voter data is sent to the map server.
    'map' => [
        'tiles' => env('MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
    ],

    'survey' => [
        // offline survey: days a downloaded booth may stay on the phone before it must be downloaded again
        'offline_days' => (int) env('OFFLINE_KEEP_DAYS', 3),
    ],

    'tasks' => [
        // daily reminder to workers (SMS / WhatsApp through the messaging gateway) for tasks due today or overdue: none | sms | whatsapp
        'reminder_channel' => env('TASK_REMINDER_CHANNEL', 'none'),
        'reminder_from' => env('TASK_REMINDER_FROM', '08:00'),
    ],

    'grievance' => [
        // told when a grievance is overdue (Grievances > Deadlines names who): none | sms | whatsapp
        'alert_channel' => env('GRIEVANCE_ALERT_CHANNEL', 'none'),
    ],

    'branding' => [
        'banner_image' => env('BRAND_BANNER', '/images/login-banner.svg'),
        'organisation' => env('BRAND_ORGANISATION', ''),
        'logo' => env('BRAND_LOGO', ''),
        // product name on the login page, menu bar, page titles and the installed phone app (empty = "Voter Information System")
        'app_name' => env('BRAND_APP_NAME', ''),
        'app_name_local' => env('BRAND_APP_NAME_LOCAL', ''),   // shown when the screen language is not English
        'short_name' => env('BRAND_SHORT_NAME', ''),           // under the phone app icon (about 12 letters)
        'owner' => env('BRAND_OWNER', ''),                     // company that owns the solution: "Powered by ..." and the copyright line
    ],

    'messaging' => [
        // false hides SMS / WhatsApp everywhere (menu, buttons, greetings, inbox, OTP by SMS, reminders) and sends nothing.
        // Nothing is deleted: set it back to true and everything reappears as it was.
        'enabled' => (bool) env('MESSAGING_ENABLED', true),
        'sms_provider' => env('SMS_PROVIDER', 'log'),          // log | twilio | http
        'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'log'), // log | twilio | meta
        'country_code' => env('MSG_COUNTRY_CODE', '+91'),
        'rate_per_second' => (int) env('MSG_RATE', 5),
        'send_from' => env('MSG_SEND_FROM', '09:00'),           // TRAI: promotional SMS 09:00-21:00
        'send_until' => env('MSG_SEND_UNTIL', '21:00'),
        'time_zone' => env('MSG_TIME_ZONE', 'Asia/Kolkata'),
        'enforce_silence_period' => (bool) env('MSG_SILENCE_PERIOD', true),
        'max_attempts' => (int) env('MSG_MAX_ATTEMPTS', 3),
        // secret part of the provider callback URLs (delivery receipts, replies): /hooks/<WEBHOOK_TOKEN>/... (at least 16 characters; empty = off)
        'webhook_token' => env('WEBHOOK_TOKEN', ''),
        // seconds one cron run may spend sending (cron runs every minute)
        'run_seconds' => (int) env('MSG_RUN_SECONDS', 50),
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
            'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
            'sms_from' => env('TWILIO_SMS_FROM', ''),
            'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', ''),
            'base_url' => env('TWILIO_BASE_URL', 'https://api.twilio.com'),
        ],
        'meta' => [
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),
            'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
            'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),   // typed in Meta's webhook settings
            'app_secret' => env('WHATSAPP_APP_SECRET', ''),       // checks the signature of Meta's calls
        ],
        'http' => [
            'method' => env('SMS_HTTP_METHOD', 'GET'),
            'url' => env('SMS_HTTP_URL', ''),
            'body' => env('SMS_HTTP_BODY', ''),
            'content_type' => env('SMS_HTTP_CONTENT_TYPE', 'application/x-www-form-urlencoded'),
            'auth_header' => env('SMS_HTTP_AUTH_HEADER', ''),
            'sender_id' => env('SMS_SENDER_ID', ''),
            'entity_id' => env('SMS_DLT_ENTITY_ID', ''),
            'success_contains' => env('SMS_HTTP_SUCCESS', ''),
        ],
    ],
];
