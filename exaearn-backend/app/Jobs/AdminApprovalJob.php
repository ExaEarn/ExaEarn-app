<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GiftcardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdminApprovalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $orderId,
        public readonly string $decision,
        public readonly ?int $adminUserId = null,
        public readonly ?string $reason = null
    ) {
    }

    public function handle(GiftcardService $giftcardService): void
    {
        $giftcardService->applyAdminDecision($this->orderId, $this->decision, $this->adminUserId, $this->reason);
    }
}
