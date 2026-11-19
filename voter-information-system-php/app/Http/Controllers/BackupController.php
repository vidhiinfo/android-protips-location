<?php

namespace App\Http\Controllers;

use App\Services\System\BackupService;

/** Admin > Backups: daily database backups, download, back up now. */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups) {}

    public function index()
    {
        $php = PHP_BINARY && !str_contains(PHP_BINARY, 'fpm') && !str_contains(PHP_BINARY, 'lsphp') ? PHP_BINARY : 'php';
        return view('admin.backups', ['list' => $this->backups->list(), 'due' => $this->backups->due(),
            'cron' => $php . ' ' . base_path('artisan') . ' vis:backup >> /dev/null 2>&1', 'keep' => (int) config('voters.backup.keep', 14),
            'onVisit' => (bool) config('voters.backup.on_visit', true)]);
    }

    public function run()
    {
        $name = $this->backups->run();
        if ($name === null) return redirect('/admin/backups')->with('error', 'backup.busy');
        $this->audit()->log('BACKUP', 'DATABASE', null, $name);
        return redirect('/admin/backups')->with('success', 'backup.done');
    }

    public function file(string $name)
    {
        abort_unless(preg_match('/^db-\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $name) && is_file($path = BackupService::dir() . '/' . $name), 404, t('error.notFound'));
        $this->audit()->log('DOWNLOAD', 'BACKUP', null, $name);
        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }
}
