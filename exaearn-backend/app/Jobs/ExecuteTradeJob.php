<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Services\TradeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteTradeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly array $payload)
    {
    }

    public function handle(TradeService $tradeService): void
    {
        Log::info('ExecuteTradeJob received.', $this->payload);

        $tradeService->placeOrder(
            (int) $this->payload['user_id'],
            (string) $this->payload['pair'],
            (string) $this->payload['side'],
            (string) $this->payload['type'],
            (string) $this->payload['amount'],
            isset($this->payload['price']) ? (string) $this->payload['price'] : null,
            $this->payload['metadata'] ?? []
        );
    }
}
