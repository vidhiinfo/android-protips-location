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
        'MESSAGE_SEND' => 'messages', 'MESSAGE_ADMIN' => 'messages',
        'USER_ADMIN' => 'admin', 'AUDIT_VIEW' => 'admin', 'SYSTEM_VIEW' => 'admin',
    ];

    public const OPERATOR = ['DASHBOARD_VIEW', 'VOTER_VIEW', 'VOTER_EDIT', 'VOTER_DELETE', 'VOTER_IMPORT', 'VOTER_EXPORT', 'MASTER_VIEW',
        'RESULT_VIEW', 'RESULT_ENTRY', 'REPORT_VIEW', 'MESSAGE_SEND'];
    public const VIEWER = ['DASHBOARD_VIEW', 'VOTER_VIEW', 'VOTER_EXPORT', 'MASTER_VIEW', 'RESULT_VIEW', 'REPORT_VIEW'];

    public static function codes(): array { return array_keys(self::ALL); }

    public static function groups(): array
    {
        $g = [];
        foreach (self::ALL as $p => $group) $g[$group][] = $p;
        return $g;
    }
}
