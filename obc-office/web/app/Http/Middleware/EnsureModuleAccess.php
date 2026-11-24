<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('module:petitions') for view access, 'module:petitions,manage' for write access. */
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, string $level = 'view'): Response
    {
        $user = $request->user();
        abort_unless($user, 401);
        $ok = $level === 'manage' ? $user->canManage($module) : $user->canView($module);
        abort_unless($ok, 403, 'You do not have access to this section.');

        return $next($request);
    }
}
