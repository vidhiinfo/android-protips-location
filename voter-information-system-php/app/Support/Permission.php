<?php

namespace App\Support;

/** Fine-grained permissions (role_permissions.permission). Same codes as the Java version. */
class Permission
{
    public const ALL = [
        'DASHBOARD_VIEW' => 'general',
        'VOTER_VIEW' => 'voters', 'VOTER_EDIT' => 'voters', 'VOTER_DELETE' => 'voters', 'VOTER_IMPORT' => 'voters', 'VOTER_EXPORT' => 'voters',
        'MASTER_VIEW' => 'masters', 'MASTER_EDIT' => 'masters',
        'RESULT_VIEW' => 'results', 'RESULT_ENTRY' => 'results',
        'REPORT_VIEW' => 'reports', 'REPORT_SENSITIVE' => 'reports',
        'MESSAGE_SEND' => 'messages', 'MESSAGE_ADMIN' => 'messages', 'PHONE_BANK' => 'messages',
        'WORKER_MANAGE' => 'workers', 'POLL_DAY' => 'pollday', 'GRIEVANCE' => 'grievances', 'ROLL_UPDATE' => 'voters', 'STATE_VIEW' => 'reports',
        'USER_ADMIN' => 'admin', 'AUDIT_VIEW' => 'admin', 'SYSTEM_VIEW' => 'admin', 'PRIVACY_ADMIN' => 'admin',
    ];

    public const OPERATOR = ['DASHBOARD_VIEW', 'VOTER_VIEW', 'VOTER_EDIT', 'VOTER_DELETE', 'VOTER_IMPORT', 'VOTER_EXPORT', 'MASTER_VIEW',
        'RESULT_VIEW', 'RESULT_ENTRY', 'REPORT_VIEW', 'MESSAGE_SEND', 'WORKER_MANAGE', 'POLL_DAY', 'GRIEVANCE', 'PHONE_BANK'];
    /** Added in a later version: given to the built-in roles of existing installations once. */
    public const ADDED = ['ADMIN' => ['WORKER_MANAGE', 'POLL_DAY', 'GRIEVANCE', 'ROLL_UPDATE', 'STATE_VIEW', 'PRIVACY_ADMIN', 'PHONE_BANK'], 'OPERATOR' => ['WORKER_MANAGE', 'POLL_DAY', 'GRIEVANCE', 'PHONE_BANK']];
    public const VIEWER = ['DASHBOARD_VIEW', 'VOTER_VIEW', 'VOTER_EXPORT', 'MASTER_VIEW', 'RESULT_VIEW', 'REPORT_VIEW'];

    /** Permissions that belong to SMS / WhatsApp messaging (hidden while MESSAGING_ENABLED=false). */
    public const MESSAGING = ['MESSAGE_SEND', 'MESSAGE_ADMIN'];

    /** SMS / WhatsApp on or off: the administrator's choice (Admin > Settings), else MESSAGING_ENABLED in .env. */
    public static function messagingOn(): bool { return (bool) config('voters.messaging.enabled', true); }   // Admin > Settings applied at start-up

    public static function codes(): array { return array_keys(self::ALL); }

    /** Permissions shown in the role editor: messaging ones only while messaging is switched on. */
    public static function visibleGroups(): array
    {
        $g = self::groups();
        if (!self::messagingOn()) {
            foreach ($g as $k => $ps) { $g[$k] = array_values(array_diff($ps, self::MESSAGING)); if (!$g[$k]) unset($g[$k]); }
        }
        return $g;
    }

    public static function groups(): array
    {
        $g = [];
        foreach (self::ALL as $p => $group) $g[$group][] = $p;
        return $g;
    }
}
