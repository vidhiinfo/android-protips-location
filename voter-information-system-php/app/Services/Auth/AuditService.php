<?php

namespace App\Services\Auth;

use App\Models\AuditLog;

class AuditService
{
    public function __construct(private CurrentUser $user) {}

    public function log(string $action, ?string $entity = null, $entityId = null, ?string $details = null, ?string $username = null): void
    {
        AuditLog::create([
            'at' => now(),
            'username' => $username ?? $this->user->username() ?? 'system',
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'details' => $details === null ? null : mb_substr($details, 0, 500),
        ]);
    }
}
