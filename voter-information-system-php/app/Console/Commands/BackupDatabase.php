<?php

namespace App\Console\Commands;

use App\Services\System\BackupService;
use Illuminate\Console\Command;

/**
 * Daily database backup. cPanel > Cron Jobs > Once Per Day:
 *   php /home/USER/public_html/upvis/artisan vis:backup >> /dev/null 2>&1
 */
class BackupDatabase extends Command
{
    protected $signature = 'vis:backup {--if-due : only when the newest backup is older than a day}';
    protected $description = 'Back up the database to storage/app/backups (.sql.gz), keeping the newest BACKUP_KEEP files';

    public function handle(BackupService $b): int
    {
        if ($this->option('if-due') && !$b->due()) { $this->info('Backup not due yet'); return self::SUCCESS; }
        $name = $b->run();
        if ($name === null) { $this->warn('Another backup is running'); return self::SUCCESS; }
        $this->info("Backup written: storage/app/backups/$name");
        return self::SUCCESS;
    }
}
