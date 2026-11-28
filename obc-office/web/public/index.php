<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// When the whole app sits inside public_html (e.g. public_html/obc-office) and the
// app's root .htaccess rewrites requests into public/, make Laravel treat
// /obc-office (not /obc-office/public) as its base address. No effect otherwise.
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
    $publicDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (str_ends_with($publicDir, '/public') && ! str_starts_with($_SERVER['REQUEST_URI'], $publicDir.'/') && $_SERVER['REQUEST_URI'] !== $publicDir) {
        $_SERVER['SCRIPT_NAME'] = substr($publicDir, 0, -strlen('/public')).'/index.php';
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
