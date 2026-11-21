<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Fallback for hosting without a cron job: after a page has been sent to the browser, checks (at most once an hour)
 * whether today's database backup is missing and writes it. The visitor does not wait for it.
 */
class BackupOnVisit
{
    public function handle(Request $request, Closure $next) { return $next($request); }

    public function terminate(Request $request, $response): void
    {
        app(\App\Services\System\BackupService::class)->runIfDue();
        try { app(\App\Services\Workers\TaskService::class)->remind(); } catch (\Throwable $e) { report($e); }
    }
}
