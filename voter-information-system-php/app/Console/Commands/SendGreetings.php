<?php

namespace App\Console\Commands;

use App\Services\Messaging\GreetingService;
use Illuminate\Console\Command;

/** Queues today's birthday / anniversary greetings (Messages > Automatic greetings). Run from cron every 15-30 minutes (messages:send delivers it). */
class SendGreetings extends Command
{
    protected $signature = 'vis:greetings {--force : run now, even before the rule\'s time}';
    protected $description = 'Queue today\'s automatic birthday / anniversary greetings';

    public function handle(GreetingService $g): int
    {
        foreach ($g->run((bool) $this->option('force')) as $rule => $res) $this->line("rule $rule: " . (is_int($res) ? "campaign $res queued" : $res));
        return self::SUCCESS;
    }
}
