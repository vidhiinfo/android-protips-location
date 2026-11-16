<?php

namespace App\Services\Auth;

use App\Models\AuditLog;

class AuditService
{
    private static ?bool $hasIp = null;

    public function __construct(private CurrentUser $user) {}

    public function log(string $action, ?string $entity = null, $entityId = null, ?string $details = null, ?string $username = null): void
    {
        $row = [
            'at' => now(),
            'username' => $username ?? $this->user->username() ?? 'system',
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'details' => $details === null ? null : mb_substr($details, 0, 500),
        ];
        self::$hasIp ??= \Illuminate\Support\Facades\Schema::hasColumn('audit_log', 'ip_address');   // until the upgrade has run
        if (self::$hasIp && !app()->runningInConsole() || self::$hasIp && app()->runningUnitTests()) {
            $req = request();
            $row['ip_address'] = $req->ip();
            $row['user_agent'] = mb_substr((string) $req->userAgent(), 0, 255) ?: null;
        }
        AuditLog::create($row);
    }
}
