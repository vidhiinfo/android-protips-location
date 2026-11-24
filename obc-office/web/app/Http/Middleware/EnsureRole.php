<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:national') or ->middleware('role:super_admin')
 * "national" is shorthand for super_admin + national_staff.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && $user->active, 403);

        $allowed = collect($roles)->flatMap(fn ($r) => $r === 'national' ? ['super_admin', 'national_staff'] : [$r]);
        abort_unless($allowed->contains($user->role), 403, 'You do not have access to this section.');

        return $next($request);
    }
}
