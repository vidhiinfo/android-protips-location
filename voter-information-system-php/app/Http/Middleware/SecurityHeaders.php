<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // map tiles (Field survey > Map) are the only images from another server
        $tiles = parse_url((string) config('voters.map.tiles'));
        $tileHost = isset($tiles['scheme'], $tiles['host']) ? ' ' . $tiles['scheme'] . '://' . str_replace(['{s}', '{z}', '{x}', '{y}'], '*', $tiles['host']) : '';
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:$tileHost; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        // pages hold a security token and voter data: never keep a saved copy (stops iPhone Safari showing an old page with an expired token)
        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }
        return $response;
    }
}
