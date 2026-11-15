<?php

namespace App\Http\Middleware;

use App\Support\Lang;
use Closure;
use Illuminate\Http\Request;

/** ?lang=hi switches the language and remembers it in the "vis-lang" cookie (first visit: browser language). */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $code = $request->query('lang') ?: $request->cookie('vis-lang') ?: $request->getPreferredLanguage(array_keys(Lang::languages()));
        Lang::setCurrent(Lang::supported($code));
        $response = $next($request);
        if ($request->query('lang') && method_exists($response, 'withCookie')) {
            $response->withCookie(cookie('vis-lang', Lang::current(), 60 * 24 * 365, null, null, null, false, false, 'lax'));
        }
        return $response;
    }
}
