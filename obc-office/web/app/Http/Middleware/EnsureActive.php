<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Logs out (web) or rejects (API) accounts that have been deactivated. */
class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->active) {
            if ($request->is('api/*')) {
                $user->currentAccessToken()?->delete();
                abort(403, 'Account disabled.');
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['username' => 'Your account has been disabled.']);
        }

        return $next($request);
    }
}
