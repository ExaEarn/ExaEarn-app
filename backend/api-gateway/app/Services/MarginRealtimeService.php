<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MarginAccount;
use App\Models\MarginRealtimeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarginRealtimeService
{
    public function __construct(private readonly RealtimeStreamService $stream)
    {
    }

    public function publish(int $userId, string $event, array $data = []): void
    {
        try {
            $stored = DB::transaction(function () use ($data, $event, $userId): MarginRealtimeEvent {
                $lastEvent = MarginRealtimeEvent::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('sequence')
                    ->lockForUpdate()
                    ->first();
                $sequence = ((int) ($lastEvent?->sequence ?? 0)) + 1;

                return MarginRealtimeEvent::query()->create([
                    'event_id' => (string) Str::uuid(),
                    'user_id' => $userId,
                    'margin_account_id' => $data['margin_account_id'] ?? null,
                    'sequence' => $sequence,
                    'event' => $event,
                    'payload' => $data,
                    'published_at' => now(),
                ]);
            });

            $this->stream->publishPayload((string) config('streaming.margin_channel', 'margin_updates'), [
                'event' => $event,
                'user_id' => $userId,
                'sequence' => (int) $stored->sequence,
                'event_id' => $stored->event_id,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Margin realtime publish failed.', [
                'event' => $event,
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function publishAccount(MarginAccount $account, string $event, array $data = []): void
    {
        $this->publish((int) $account->user_id, $event, array_merge([
            'margin_account_id' => $account->id,
            'account_uuid' => $account->account_uuid,
            'mode' => $account->mode,
            'market_symbol' => $account->market_symbol,
            'status' => $account->status,
        ], $data));
    }

    public function snapshot(int $userId, int $afterSequence = 0, int $limit = 100): array
    {
        $limit = max(1, min($limit, 250));
        $events = MarginRealtimeEvent::query()
            ->where('user_id', $userId)
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit($limit)
            ->get();

        return [
            'latest_sequence' => (int) (MarginRealtimeEvent::query()->where('user_id', $userId)->max('sequence') ?? 0),
            'events' => $events->map(fn (MarginRealtimeEvent $event): array => [
                'event_id' => $event->event_id,
                'sequence' => (int) $event->sequence,
                'event' => $event->event,
                'timestamp' => optional($event->published_at)->toIso8601String(),
                'data' => $event->payload,
            ])->values()->all(),
        ];
    }
}
