<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExaAiRealtimeEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExaAiRealtimeService
{
    public function publish(?int $userId, string $stream, string $eventType, array $payload): ExaAiRealtimeEvent
    {
        return DB::transaction(function () use ($eventType, $payload, $stream, $userId): ExaAiRealtimeEvent {
            $last = ExaAiRealtimeEvent::query()
                ->where('user_id', $userId)
                ->where('stream', $stream)
                ->lockForUpdate()
                ->max('sequence');

            return ExaAiRealtimeEvent::query()->create([
                'user_id' => $userId,
                'stream' => $stream,
                'sequence' => ((int) $last) + 1,
                'event_type' => $eventType,
                'payload' => $payload,
                'published_at' => now(),
            ]);
        });
    }

    public function replay(int $userId, string $stream, int $afterSequence = 0, int $limit = 100): Collection
    {
        return ExaAiRealtimeEvent::query()
            ->where('user_id', $userId)
            ->where('stream', $stream)
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit(min(max($limit, 1), 500))
            ->get();
    }
}
