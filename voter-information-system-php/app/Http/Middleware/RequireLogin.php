<?php

namespace App\Http\Middleware;

use App\Services\Auth\CurrentUser;
use App\Services\Auth\Defaults;
use Closure;
use Illuminate\Http\Request;

class RequireLogin
{
    public function __construct(private CurrentUser $user, private Defaults $defaults) {}

    public function handle(Request $request, Closure $next)
    {
        $this->defaults->ensure();
        if (!$this->user->check()) return $request->expectsJson() ? response()->json(['login' => url('/login')], 401) : redirect('/login');
        // administrators keep the short office timeout; field workers stay logged in for FIELD_SESSION_DAYS
        $limit = (int) config('voters.security.office_session_minutes');
        $seen = (int) session('vis.seen', now()->getTimestamp());
        if ($limit > 0 && $this->user->can('USER_ADMIN') && now()->getTimestamp() - $seen > $limit * 60) {
            $this->user->logout();
            return $request->expectsJson() ? response()->json(['login' => url('/login')], 401) : redirect('/login')->with('error', t('session.expired'));
        }
        session(['vis.seen' => now()->getTimestamp()]);
        return $next($request);
    }
}
