<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\P2PService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ModerateP2PMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $messageId)
    {
        $this->onQueue('p2p-chat');
    }

    public function handle(P2PService $p2pService): void
    {
        $p2pService->moderateMessage($this->messageId);
    }
}
