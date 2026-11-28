<?php
/*
 | OBC Office — server check
 | -------------------------------------------------------------------------
 | 1. Upload this file to public_html/.
 | 2. Open https://electionsoft.in/obc-check.php in a browser.
 | 3. Send the result to your developer, then DELETE this file.
 |
 | It shows no passwords or keys — only whether things are set up.
 | If the app is not in ~/obc-office/web, set $app below.
 */

// Find the app: via the public_html/obc link if present, else the usual locations next to public_html.
$app = getenv('OBC_APP_PATH') ?: '';
if ($app === '') {
    $candidates = [];
    if (is_file(__DIR__.'/obc/index.php') && ($real = realpath(__DIR__.'/obc/..'))) {
        $candidates[] = $real;
    }
    foreach (['/obc-office', '/obc-office/web', '/obc-app'] as $sub) {
        $candidates[] = dirname(__DIR__).$sub;
    }
    $candidates[] = __DIR__.'/obc-office';   // whole app inside public_html (protected by its .htaccess)
    foreach ($candidates as $c) {
        if (is_file($c.'/artisan')) {
            $app = $c;
            break;
        }
    }
    $app = $app ?: dirname(__DIR__).'/obc-office';
}
$insidePublic = str_starts_with(realpath($app) ?: $app, (realpath(__DIR__) ?: __DIR__).'/');
$guarded = $insidePublic && is_file($app.'/.htaccess') && str_contains((string) @file_get_contents($app.'/.htaccess'), 'RewriteRule ^(.*)$ public/$1');
$exposed = $insidePublic && ! $guarded;
$urlPath = $insidePublic ? '/'.basename($app) : '/obc';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

$checks = [];
$add = function (string $label, bool $ok, string $detail = '', string $fix = '') use (&$checks) {
    $checks[] = compact('label', 'ok', 'detail', 'fix');
};

// Security: the app (and its .env) must not be inside public_html
$add('App files protected from the web', ! $exposed,
    $insidePublic ? ($guarded ? 'inside public_html, protected by obc-office/.htaccess' : 'inside public_html WITHOUT protection — .env may be downloadable!') : 'outside public_html',
    'Upload the .htaccess file from the update into '.$app.'/ (next to artisan), then change the database password');

// PHP
$add('PHP version 8.2 or newer', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION,
    'cPanel → MultiPHP Manager → PHP 8.2 or newer');
foreach (['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'gd', 'intl', 'curl', 'zip'] as $ext) {
    $add("PHP extension: $ext", extension_loaded($ext), '', 'cPanel → Select PHP Version → Extensions → tick '.$ext);
}
$add('upload_max_filesize ≥ 10M', (int) ini_get('upload_max_filesize') >= 10, ini_get('upload_max_filesize'),
    'cPanel → Select PHP Version → Options → upload_max_filesize = 20M');
$add('post_max_size ≥ 50M', (int) ini_get('post_max_size') >= 50, ini_get('post_max_size'),
    'cPanel → Select PHP Version → Options → post_max_size = 120M');

// Files
$add('App folder found', is_dir($app), $app, 'Extract obc-office-web.zip in your home folder (next to public_html), or set $app at the top of this file');
$add('Libraries present (vendor/autoload.php)', is_file("$app/vendor/autoload.php"), '', 'Re-upload the full zip (it contains the vendor folder)');
$add('Built CSS/JS present (public/build)', is_file("$app/public/build/manifest.json"), '', 'Re-upload the full zip (it contains public/build)');

$obc = $insidePublic ? $app.'/public' : __DIR__.'/obc';
$isLink = is_link($obc);
$target = $isLink ? readlink($obc) : '';
if (! $insidePublic) {
    $add('public_html/obc exists', file_exists($obc), $isLink ? "link → $target" : (is_dir($obc) ? 'folder' : 'missing'),
        'Run in cPanel Terminal: ln -s '.$app.'/public '.$obc);
}
if ($isLink) {
    $add('public_html/obc points to the app\'s public folder', realpath($obc) === realpath("$app/public"), (string) realpath($obc),
        'Remove it and recreate: rm '.$obc.' && ln -s '.$app.'/public '.$obc);
}
$add('public/index.php present', is_file("$obc/index.php"), '', 'Re-upload the public folder');
$add('public/.htaccess present', is_file("$obc/.htaccess"), '', 'The .htaccess file is hidden in File Manager — enable "Show hidden files" and re-upload public/.htaccess');

// .env (values are never printed)
$envFile = "$app/.env";
$env = [];
$dupes = [];
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
            if (array_key_exists($m[1], $env)) {
                $dupes[] = $m[1]; // Laravel uses the FIRST occurrence

                continue;
            }
            $env[$m[1]] = trim($m[2], " \t\"'");
        }
    }
}
$add('.env file exists', is_file($envFile), '', 'Copy .env.example to .env in '.$app.' and fill it in (see DEPLOY.md)');
$add('No setting written twice in .env', ! $dupes, $dupes ? implode(', ', array_unique($dupes)).' (only the first one counts)' : '', 'Delete the extra lines so each setting appears once');
$add('.env file location', true, $envFile);
$add('APP_KEY is set', str_starts_with($env['APP_KEY'] ?? '', 'base64:'), '', 'Run: cd '.$app.' && php artisan key:generate');
$add("APP_URL = https://electionsoft.in$urlPath", rtrim($env['APP_URL'] ?? '', '/') === "https://electionsoft.in$urlPath", $env['APP_URL'] ?? '(not set)', "Set APP_URL=https://electionsoft.in$urlPath in .env");
$add('APP_DEBUG is false', in_array(strtolower($env['APP_DEBUG'] ?? 'false'), ['false', '0'], true), $env['APP_DEBUG'] ?? '', 'Set APP_DEBUG=false (temporarily true only to see an error, then back to false)');
$add("SESSION_PATH = $urlPath", ($env['SESSION_PATH'] ?? '') === $urlPath, $env['SESSION_PATH'] ?? '(not set)', "Set SESSION_PATH=$urlPath in .env");
$add('Laravel 12 libraries (PHP 8.2 compatible)', ! is_file("$app/vendor/laravel/framework/composer.json") || str_contains((string) @file_get_contents("$app/vendor/composer/installed.json"), '"version": "v12.'), '', 'Replace the whole vendor folder with the one from the update');
$add('DB_CONNECTION = mysql', ($env['DB_CONNECTION'] ?? '') === 'mysql', $env['DB_CONNECTION'] ?? '(not set)', 'Set DB_CONNECTION=mysql and the DB_* values from cPanel → MySQL Databases');

// Database
if (($env['DB_CONNECTION'] ?? '') === 'mysql' && extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? 'localhost', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? ''),
            $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $add('Database connection', true, $env['DB_DATABASE'] ?? '');
        $tables = $pdo->query("SHOW TABLES LIKE 'members'")->fetchColumn();
        $add('Tables created', (bool) $tables, '', 'Import obc_office.sql in phpMyAdmin (or run: cd '.$app.' && php artisan migrate --force)');
        if ($tables) {
            $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'")->fetchColumn();
            $add('Admin user exists', $admins > 0, "$admins admin(s)", 'Import obc_office.sql in phpMyAdmin (or run: cd '.$app.' && php artisan db:seed --force)');
        }
    } catch (Throwable $e) {
        $msg = preg_replace('/(password|using password: \w+)/i', '[hidden]', $e->getMessage());
        $add('Database connection', false, $msg, 'Check DB_HOST (usually localhost), DB_DATABASE, DB_USERNAME (with the cPanel prefix, e.g. user_obc) and DB_PASSWORD; the user must have ALL PRIVILEGES on the database');
    }
}

// Writable folders
foreach (['storage', 'storage/logs', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'bootstrap/cache'] as $dir) {
    $add("Writable: $dir", is_dir("$app/$dir") && is_writable("$app/$dir"), '', "File Manager → $app/$dir → Permissions 775");
}

// Last error from the Laravel log (message line only)
$logTail = '';
$log = "$app/storage/logs/laravel.log";
if (is_file($log) && is_readable($log)) {
    $fh = fopen($log, 'r');
    fseek($fh, max(0, filesize($log) - 20000));
    $chunk = stream_get_contents($fh);
    fclose($fh);
    if (preg_match_all('/^\[[^\]]+\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): .*$/m', $chunk, $m)) {
        $logTail = preg_replace('/(password|secret|key)=\S+/i', '$1=[hidden]', mb_substr(end($m[0]), 0, 600));
    }
}

$failed = array_filter($checks, fn ($c) => ! $c['ok']);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>OBC Office — server check</title>
<style>
 body{font-family:system-ui,sans-serif;max-width:860px;margin:24px auto;padding:0 16px;color:#1e293b;background:#f8fafc}
 h1{font-size:20px} table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:8px}
 td{padding:8px 10px;border-top:1px solid #f1f5f9;vertical-align:top;font-size:14px} .ok{color:#15803d} .bad{color:#b91c1c;font-weight:600}
 .fix{font-size:12px;color:#92400e;margin-top:3px} code,.d{font-family:ui-monospace,monospace;font-size:12px;color:#475569;word-break:break-all}
 .box{padding:12px 14px;border-radius:8px;margin:12px 0} .warn{background:#fef3c7} .good{background:#dcfce7} .log{background:#fee2e2;font-family:ui-monospace,monospace;font-size:12px;white-space:pre-wrap}
</style></head><body>
<h1>OBC Office — server check</h1>
<div class="box <?= $failed ? 'warn' : 'good' ?>">
  <?= $failed ? count($failed).' problem(s) found — fix the red items from the top down.' : 'Everything looks good. Open https://electionsoft.in/obc' ?>
  <br><b>Delete this file (obc-check.php) when you are done.</b>
</div>
<table>
<?php foreach ($checks as $c) { ?>
 <tr>
  <td style="width:28px" class="<?= $c['ok'] ? 'ok' : 'bad' ?>"><?= $c['ok'] ? '✔' : '✘' ?></td>
  <td><span class="<?= $c['ok'] ? '' : 'bad' ?>"><?= htmlspecialchars($c['label']) ?></span>
   <?php if ($c['detail'] !== '') { ?> <span class="d">— <?= htmlspecialchars($c['detail']) ?></span><?php } ?>
   <?php if (! $c['ok'] && $c['fix']) { ?><div class="fix">Fix: <?= htmlspecialchars($c['fix']) ?></div><?php } ?>
  </td>
 </tr>
<?php } ?>
</table>
<?php if ($logTail) { ?><h3>Last error in the app log</h3><div class="box log"><?= htmlspecialchars($logTail) ?></div><?php } ?>
<p class="d">Checked <?= date('Y-m-d H:i:s') ?> · app path: <?= htmlspecialchars($app) ?> · this page: <?= htmlspecialchars(__FILE__) ?></p>
</body></html>
