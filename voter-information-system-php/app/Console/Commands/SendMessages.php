<?php

namespace App\Console\Commands;

use App\Services\Messaging\OutboxProcessor;
use Illuminate\Console\Command;

/**
 * Sends queued SMS / WhatsApp messages. Add ONE cron job (cPanel > Cron Jobs), every minute:
 *   php /home/USER/voter-app/artisan messages:send >> /dev/null 2>&1
 */
class SendMessages extends Command
{
    protected $signature = 'messages:send {--ignore-window : ignore the daily send window (testing)} {--seconds= : stop after this many seconds}';
    protected $description = 'Send queued SMS / WhatsApp campaign messages';

    public function handle(OutboxProcessor $p): int
    {
        $n = $p->run((bool) $this->option('ignore-window'), $this->option('seconds') ? (int) $this->option('seconds') : null);
        $this->info("$n messages processed");
        return self::SUCCESS;
    }
}
