<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CopyRealtimeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CopyRealtimeService
{
    public function record(int $userId, string $eventType, array $payload, string $stream = 'copy'): CopyRealtimeEvent
    {
        return DB::transaction(function () use ($eventType, $payload, $stream, $userId): CopyRealtimeEvent {
            $last = (int) CopyRealtimeEvent::query()
                ->where('user_id', $userId)
                ->where('stream', $stream)
                ->lockForUpdate()
                ->max('sequence');

            $event = CopyRealtimeEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'user_id' => $userId,
                'stream' => $stream,
                'sequence' => $last + 1,
                'event_type' => $eventType,
                'payload' => $payload,
                'published_at' => now(),
            ]);

            try {
                Redis::publish("private.copy.{$userId}", json_encode([
                    'stream' => $stream,
                    'sequence' => $event->sequence,
                    'event_type' => $eventType,
                    'payload' => $payload,
                    'published_at' => $event->published_at?->toISOString(),
                ], JSON_THROW_ON_ERROR));
            } catch (\Throwable) {
                // Durable database replay remains authoritative if Redis fanout is unavailable.
            }

            return $event;
        });
    }

    public function replay(int $userId, int $afterSequence = 0, string $stream = 'copy', int $limit = 250): array
    {
        return CopyRealtimeEvent::query()
            ->where('user_id', $userId)
            ->where('stream', $stream)
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit(min(max($limit, 1), 1000))
            ->get()
            ->map(fn (CopyRealtimeEvent $event): array => [
                'stream' => $event->stream,
                'sequence' => $event->sequence,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'published_at' => $event->published_at?->toISOString(),
            ])
            ->all();
    }
}
