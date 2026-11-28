<?php

/*
| Everything here is a DEFAULT. Admins override these values from Settings in the web
| app (stored in the `settings` table), so normally you never need to edit this file.
*/

$allModules = ['dashboard', 'members', 'biodata', 'directory', 'import', 'schedule', 'visitors', 'petitions', 'tasks',
    'contacts', 'letters', 'greetings', 'circulars', 'programs', 'users', 'masters', 'settings', 'audit'];

$grant = fn (array $map) => array_replace(array_fill_keys($allModules, 'none'), $map);

return [

    /*
    | Module registry: key => label, group, icon (emoji keeps it dependency-free) and route.
    | "levels" says which permission levels make sense for the module.
    */
    'modules' => [
        'dashboard' => ['label' => 'Dashboard', 'group' => 'Overview', 'icon' => '📊', 'route' => 'dashboard', 'levels' => ['none', 'view']],
        'members' => ['label' => 'Bearers & Workers', 'group' => 'Organisation', 'icon' => '👥', 'route' => 'members.index', 'levels' => ['none', 'view', 'manage']],
        'biodata' => ['label' => 'Biodata Register', 'group' => "President's Office", 'icon' => '📄', 'route' => 'biodata.index', 'levels' => ['none', 'view', 'manage']],
        'directory' => ['label' => 'Photo Directory', 'group' => 'Organisation', 'icon' => '🪪', 'route' => 'directory', 'levels' => ['none', 'view']],
        'import' => ['label' => 'Import / Export', 'group' => 'Organisation', 'icon' => '📥', 'route' => 'import.form', 'levels' => ['none', 'manage']],
        'programs' => ['label' => 'Programmes & Reports', 'group' => 'Organisation', 'icon' => '📣', 'route' => 'programs.index', 'levels' => ['none', 'view', 'manage']],
        'circulars' => ['label' => 'Circulars', 'group' => 'Organisation', 'icon' => '📜', 'route' => 'circulars.index', 'levels' => ['none', 'view', 'manage']],
        'schedule' => ['label' => 'Schedule & Tours', 'group' => "President's Office", 'icon' => '📅', 'route' => 'schedule.index', 'levels' => ['none', 'view', 'manage']],
        'visitors' => ['label' => 'Visitors', 'group' => "President's Office", 'icon' => '🚪', 'route' => 'visitors.index', 'levels' => ['none', 'view', 'manage']],
        'petitions' => ['label' => 'Petitions', 'group' => "President's Office", 'icon' => '📨', 'route' => 'petitions.index', 'levels' => ['none', 'view', 'manage']],
        'tasks' => ['label' => 'Tasks', 'group' => "President's Office", 'icon' => '✅', 'route' => 'tasks.index', 'levels' => ['none', 'view', 'manage']],
        'contacts' => ['label' => 'VIP Contacts', 'group' => "President's Office", 'icon' => '📇', 'route' => 'contacts.index', 'levels' => ['none', 'view', 'manage']],
        'letters' => ['label' => 'Letters Register', 'group' => "President's Office", 'icon' => '✉️', 'route' => 'letters.index', 'levels' => ['none', 'view', 'manage']],
        'greetings' => ['label' => 'Greetings', 'group' => "President's Office", 'icon' => '🎉', 'route' => 'greetings', 'levels' => ['none', 'view']],
        'users' => ['label' => 'Users', 'group' => 'Administration', 'icon' => '🔐', 'route' => 'users.index', 'levels' => ['none', 'manage']],
        'masters' => ['label' => 'States, Districts & Posts', 'group' => 'Administration', 'icon' => '🗺️', 'route' => 'masters.index', 'levels' => ['none', 'manage']],
        'settings' => ['label' => 'Settings', 'group' => 'Administration', 'icon' => '⚙️', 'route' => 'settings.index', 'levels' => ['none', 'manage']],
        'audit' => ['label' => 'Audit Log', 'group' => 'Administration', 'icon' => '🧾', 'route' => 'audit.index', 'levels' => ['none', 'view']],
    ],

    'defaults' => [
        // Branding
        'org_name' => env('OFFICE_ORG_NAME', 'OBC Morcha — National Office'),
        'org_short_name' => 'OBC Morcha',
        'president_name' => env('OFFICE_PRESIDENT_NAME', 'National President'),
        'logo_path' => null,
        'theme_color' => '#ea580c',
        'footer_text' => 'Confidential — for official party use only',

        // Leader profile shown on the login page (Settings → General → Leader profile)
        'leader_name' => 'श्री अमरपाल मौर्य',
        'leader_designation' => "सांसद (राज्यसभा)\nराष्ट्रीय अध्यक्ष, भारतीय जनता पार्टी ओबीसी मोर्चा",
        'leader_photo_path' => null,
        'leader_on_login' => true,

        // Messages ({name}, {designation}, {signature}, {org} are replaced)
        'greeting_signature' => 'National President, OBC Morcha',
        'birthday_template' => "Dear {name} ji,\nHeartiest wishes on your birthday! May you be blessed with good health and continue serving the people with the same dedication.\n\n— {signature}",
        'anniversary_template' => "Dear {name} ji,\nWarm wishes on your wedding anniversary!\n\n— {signature}",
        'greeting_days_ahead' => 7,

        // Petitions
        'petition_prefix' => 'PET',
        'petition_default_due_days' => 15,
        'petition_closed_status' => 'closed',

        // Members
        'member_required_fields' => ['name', 'mobile', 'level', 'designation_id'],
        'require_consent' => true,
        // When true, records added by state/district admins start as "proposed" until the national office approves.
        'state_entries_need_approval' => true,
        'photo_max_px' => 600,
        'duplicate_check_mobile' => true,

        // Biodata
        'biodata_prefix' => 'BIO',
        'biodata_required_fields' => ['received_from'],
        'biodata_max_file_mb' => 10,

        // Formats
        'date_format' => 'd M Y',
        'datetime_format' => 'd M Y, h:i A',
        'per_page' => 25,

        // Module on/off switches (off hides it for everyone, including admins)
        'modules_enabled' => array_fill_keys($allModules, true),

        // Role → module → none | view | manage. super_admin always has full access (cannot be locked out).
        'permissions' => [
            'national_staff' => $grant([
                'dashboard' => 'view', 'members' => 'manage', 'biodata' => 'manage',
                'directory' => 'view', 'import' => 'manage', 'programs' => 'manage', 'circulars' => 'manage', 'schedule' => 'manage', 'visitors' => 'manage',
                'petitions' => 'manage', 'tasks' => 'manage', 'contacts' => 'manage', 'letters' => 'manage',
                'greetings' => 'view', 'masters' => 'manage',
            ]),
            'state_admin' => $grant([
                'dashboard' => 'view', 'members' => 'manage', 'directory' => 'view', 'import' => 'manage',
                'programs' => 'view', 'circulars' => 'view', 'tasks' => 'view',
            ]),
            'district_admin' => $grant([
                'dashboard' => 'view', 'members' => 'manage', 'directory' => 'view',
                'programs' => 'view', 'circulars' => 'view', 'tasks' => 'view',
            ]),
        ],
    ],
];
