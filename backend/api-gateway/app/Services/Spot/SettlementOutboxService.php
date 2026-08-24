<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\SpotSettlementOutbox;
use App\Models\Trade;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Throwable;

class SettlementOutboxService
{
    public function __construct(private readonly SettlementService $settlements)
    {
    }

    public function process(int $limit = 100): array
    {
        $processed = 0;
        $settled = 0;
        $failed = 0;

        SpotSettlementOutbox::query()
            ->whereIn('status', ['pending', 'retryable', 'failed_retryable'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (SpotSettlementOutbox $outbox) use (&$failed, &$processed, &$settled): void {
                $processed++;
                $result = $this->processOne($outbox);
                $settled += $result === 'settled' ? 1 : 0;
                $failed += $result !== 'settled' ? 1 : 0;
            });

        return compact('processed', 'settled', 'failed');
    }

    public function processOne(SpotSettlementOutbox $outbox): string
    {
        return DB::transaction(function () use ($outbox): string {
            $locked = SpotSettlementOutbox::query()->whereKey($outbox->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'settled') {
                return 'settled';
            }

            $locked->status = 'processing';
            $locked->attempts = ((int) $locked->attempts) + 1;
            $locked->save();

            try {
                $this->settlements->spotTrade((array) $locked->payload, (string) $locked->reference);
                $locked->status = 'settled';
                $locked->settled_at = now();
                $locked->last_error = null;
                $locked->save();

                if ($locked->trade_id) {
                    Trade::query()->whereKey($locked->trade_id)->update(['settlement_status' => 'settled']);
                }

                return 'settled';
            } catch (Throwable $exception) {
                $locked->last_error = $exception->getMessage();
                $locked->status = ((int) $locked->attempts) >= (int) config('trading.engine.settlement_max_attempts', 5)
                    ? 'failed_manual_review'
                    : 'retryable';
                $locked->save();

                if ($locked->trade_id) {
                    Trade::query()->whereKey($locked->trade_id)->update(['settlement_status' => $locked->status]);
                }

                return $locked->status;
            }
        });
    }

    public function metrics(): array
    {
        $oldest = SpotSettlementOutbox::query()
            ->whereIn('status', ['pending', 'retryable', 'failed_retryable', 'processing'])
            ->orderBy('created_at')
            ->first();

        return [
            'settlement_outbox_pending' => SpotSettlementOutbox::query()->where('status', 'pending')->count(),
            'settlement_outbox_retrying' => SpotSettlementOutbox::query()->whereIn('status', ['retryable', 'failed_retryable'])->count(),
            'settlement_outbox_failed' => SpotSettlementOutbox::query()->where('status', 'failed_manual_review')->count(),
            'settlement_oldest_pending_seconds' => $oldest ? now()->diffInSeconds($oldest->created_at) : 0,
        ];
    }
}
