<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CopyLeadTradeEvent;
use App\Models\CopyRelationship;
use App\Services\CopyTradingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCopyFollowerDecision implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $copyRelationshipId,
        public readonly int $leadTradeEventId,
    ) {
    }

    public function handle(CopyTradingService $copyTrading): void
    {
        $relationship = CopyRelationship::query()->findOrFail($this->copyRelationshipId);
        $event = CopyLeadTradeEvent::query()->findOrFail($this->leadTradeEventId);

        $copyTrading->processFollowerCopy($relationship, $event);
    }
}
