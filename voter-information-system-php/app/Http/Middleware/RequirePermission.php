<?php

namespace App\Http\Middleware;

use App\Services\Auth\CurrentUser;
use Closure;
use Illuminate\Http\Request;

/** Route middleware perm:VOTER_EDIT (several = any of them). */
class RequirePermission
{
    public function __construct(private CurrentUser $user) {}

    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        foreach ($permissions as $p) if ($this->user->can($p)) return $next($request);
        abort(403);
    }
}
