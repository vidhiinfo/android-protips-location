<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Installed in public_html (or a subfolder such as /vis/) and reached through the root .htaccess:
// the address has no "/public", so tell Laravel the site starts at the folder that holds the app.
foreach (['SCRIPT_NAME', 'PHP_SELF'] as $key) {
    $script = $_SERVER[$key] ?? '';
    $base = substr($script, 0, -strlen('/public/index.php'));
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (str_ends_with($script, '/public/index.php') && !str_starts_with($uri, $base . '/public/') && ($base === '' || $uri === $base || str_starts_with($uri, $base . '/'))) {
        $_SERVER[$key] = $base . '/index.php';
    }
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
