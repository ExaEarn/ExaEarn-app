<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\P2PService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireP2PTradeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $tradeId)
    {
        $this->onQueue('p2p');
    }

    public function handle(P2PService $p2pService): void
    {
        $p2pService->expireTrade($this->tradeId);
    }
}
