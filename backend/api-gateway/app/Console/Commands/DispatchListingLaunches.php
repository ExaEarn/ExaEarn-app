<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ListingLaunchDispatcherService;
use Illuminate\Console\Command;

class DispatchListingLaunches extends Command
{
    protected $signature = 'listing:launches:dispatch {--limit=100}';
    protected $description = 'Claim and dispatch due durable Listing launch operations';

    public function handle(ListingLaunchDispatcherService $dispatcher): int
    {
        $events = $dispatcher->dispatchDue((int) $this->option('limit'));
        $this->info('Listing launch events dispatched: '.count($events));
        return self::SUCCESS;
    }
}
