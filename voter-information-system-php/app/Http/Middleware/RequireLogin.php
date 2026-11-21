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
        return $next($request);
    }
}
