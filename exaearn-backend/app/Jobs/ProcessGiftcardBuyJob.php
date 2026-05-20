<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GiftcardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGiftcardBuyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $orderId)
    {
    }

    public function handle(GiftcardService $giftcardService): void
    {
        $giftcardService->processBuyOrder($this->orderId);
    }
}
