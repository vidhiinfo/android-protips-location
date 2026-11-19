<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Shared hosting without SSH: create the encryption key on the first request if .env has none.
(function (string $env) {
    if (!is_file($env) || !is_writable($env)) return;
    $text = (string) file_get_contents($env);
    if (preg_match('/^APP_KEY=\S+/m', $text)) return;
    $key = 'APP_KEY=base64:' . base64_encode(random_bytes(32));
    $text = preg_match('/^APP_KEY=.*$/m', $text) ? preg_replace('/^APP_KEY=.*$/m', $key, $text, 1) : $key . PHP_EOL . $text;
    file_put_contents($env, $text, LOCK_EX);
})(dirname(__DIR__) . '/.env');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class, \App\Http\Middleware\SecurityHeaders::class, \App\Http\Middleware\BackupOnVisit::class]);
        $middleware->encryptCookies(except: ['vis-lang']);
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'login.required' => \App\Http\Middleware\RequireLogin::class,
            'perm' => \App\Http\Middleware\RequirePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "419 Page Expired": the form's security token is from an old session (page left open, phone asleep,
        // Safari showing a saved copy). Never show the bare error page: go back to the form, or to the login page.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $r) {
            if ($e->getStatusCode() !== 419) return null;
            if ($r->expectsJson()) return response()->json(['message' => t('session.expired'), 'reload' => true], 419);
            $loggedIn = app(\App\Services\Auth\CurrentUser::class)->check();
            if ($r->is('login') || !$loggedIn) return redirect('/login?error=expired');
            return redirect()->back()->withInput($r->except(['password', '_token']))->with('error', 'session.expired');
        });
    })->create();
