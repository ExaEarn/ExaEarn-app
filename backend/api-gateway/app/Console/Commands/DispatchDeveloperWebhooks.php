<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeveloperWebhookService;
use Illuminate\Console\Command;

class DispatchDeveloperWebhooks extends Command
{
    protected $signature = 'developer:webhooks:dispatch
        {--limit=50 : Maximum deliveries to claim per pass}
        {--loop : Continue polling until the worker is terminated}
        {--sleep=2 : Seconds between loop passes}';

    protected $description = 'Atomically claim and deliver due Developer webhook events.';

    public function handle(DeveloperWebhookService $webhooks): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $running = true;
        if ($this->option('loop') && method_exists($this, 'trap') && defined('SIGTERM') && defined('SIGINT')) {
            $this->trap([constant('SIGTERM'), constant('SIGINT')], function () use (&$running): void { $running = false; });
        }
        do {
            $this->line(json_encode($webhooks->deliverDue($limit), JSON_THROW_ON_ERROR));
            if (! $this->option('loop') || ! $running) break;
            sleep(max(1, min(30, (int) $this->option('sleep'))));
        } while ($running);

        return self::SUCCESS;
    }
}
