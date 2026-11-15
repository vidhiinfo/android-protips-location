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
    'security' => [
        'user_source' => env('USER_SOURCE', 'app'),
        'external' => [
            // columns: username, password, enabled [, assembly_no] [, full_name]
            'users_query' => env('USERS_QUERY', 'select username, password, enabled, assembly_no, full_name from users where username = ?'),
            // one role name per row
            'roles_query' => env('ROLES_QUERY', 'select r.role_name from user_roles ur join users u on u.id = ur.user_id join roles r on r.id = ur.role_id where u.username = ?'),
            // "SUPERVISOR=ADMIN,FIELD_STAFF=VIEWER"
            'role_map' => env('USERS_ROLE_MAP', ''),
            // optional separate database connection name from config/database.php (empty = default)
            'connection' => env('USERS_DB_CONNECTION', ''),
        ],
    ],

    'branding' => [
        'banner_image' => env('BRAND_BANNER', '/images/login-banner.svg'),
        'organisation' => env('BRAND_ORGANISATION', ''),
        'logo' => env('BRAND_LOGO', ''),
    ],

    'messaging' => [
        'sms_provider' => env('SMS_PROVIDER', 'log'),          // log | twilio | http
        'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'log'), // log | twilio | meta
        'country_code' => env('MSG_COUNTRY_CODE', '+91'),
        'rate_per_second' => (int) env('MSG_RATE', 5),
        'send_from' => env('MSG_SEND_FROM', '09:00'),           // TRAI: promotional SMS 09:00-21:00
        'send_until' => env('MSG_SEND_UNTIL', '21:00'),
        'time_zone' => env('MSG_TIME_ZONE', 'Asia/Kolkata'),
        'enforce_silence_period' => (bool) env('MSG_SILENCE_PERIOD', true),
        'max_attempts' => (int) env('MSG_MAX_ATTEMPTS', 3),
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
